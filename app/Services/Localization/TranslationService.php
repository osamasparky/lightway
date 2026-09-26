<?php

namespace App\Services\Localization;

use App\Models\Localization\TranslationEntry;
use App\Models\Localization\TranslationJob;
use App\Services\Localization\AI\AITranslationException;
use App\Services\Localization\AI\AITranslationProvider;
use App\Services\Localization\AI\TranslationPromptBuilder;
use App\Services\Localization\AI\TranslationQAService;

/**
 * The translation pipeline for a batch of strings:
 *
 *   context → glossary → translation memory → token protection → AI translation →
 *   restore tokens → validation (structure, placeholders, HTML, glossary, numbers, length, language) →
 *   AI QA (professional / premium) → revision (premium) → result per string
 *
 * Nothing here writes to the database: the caller saves the results. A FAILED result
 * never carries text to save.
 */
class TranslationService
{
    public const MODE_TRANSLATE = 'translate';
    public const MODE_IMPROVE = 'improve';

    public function __construct(
        private AITranslationProvider $provider,
        private TranslationPromptBuilder $prompts,
        private TranslationQAService $qa,
        private TokenProtector $tokens,
        private TranslationValidator $validator,
        private TranslationContextBuilder $contexts,
        private TranslationGlossaryService $glossary,
        private TranslationMemoryService $memory,
        private TranslationProfiles $profiles,
        private LanguageRegistry $languages,
        private TranslationSettings $settings
    ) {
    }

    /**
     * @param array<int, array{hash:string, group:string, key:string, source:string, current?:?string, previous_problem?:?string}> $items
     * @return array{
     *   results: array<string, array{text:?string, status:string, issues:string[], outcome:string, error:?string}>,
     *   prompt_tokens:int, completion_tokens:int, qa_prompt_tokens:int, qa_completion_tokens:int, requests:int, memory_hits:int
     * }
     *
     * @throws AITranslationException when the translation call itself fails (the caller retries the batch)
     */
    public function process(array $items, string $sourceLocale, string $targetLocale, ?string $qualityMode = null, string $mode = self::MODE_TRANSLATE, array $models = []): array
    {
        if (!$this->settings->isReady()) {
            throw new AITranslationException(AITranslationException::NOT_CONFIGURED, 'AI translation is not configured (API key and model).');
        }
        if (!$this->languages->find($targetLocale)) {
            throw new AITranslationException(AITranslationException::REQUEST, 'The target language is not enabled in the site settings.');
        }

        $source = $this->languages->find($sourceLocale) ?? ['locale' => $sourceLocale, 'name' => $this->languages->label($sourceLocale)];
        $profile = $this->profiles->resolve($targetLocale, $qualityMode);
        // A job uses the models saved on it (shown on its page), not whatever the settings say now.
        foreach (['translation_model', 'qa_model'] as $field) {
            if (!empty($models[$field])) {
                $profile[$field] = $models[$field];
            }
        }
        $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'qa_prompt_tokens' => 0, 'qa_completion_tokens' => 0, 'requests' => 0, 'memory_hits' => 0, 'qa_error' => null];
        $results = [];

        $items = array_values(array_filter($items, fn($i) => trim((string)$i['source']) !== ''));
        $contexts = $this->contexts->build($items, $sourceLocale);
        foreach ($items as &$item) {
            $contexts[$item['hash']]['key'] = $item['group'] . '.' . $item['key'];
            $item['context'] = $contexts[$item['hash']];
        }
        unset($item);

        // Translation memory: reuse unambiguous approved wording, without an API call.
        $hints = [];
        if ($mode === self::MODE_TRANSLATE and (int)$this->settings->get('use_translation_memory') === 1) {
            $memory = $this->memory->lookup($targetLocale, $items);
            $hints = $memory['hints'];

            foreach ($items as $index => $item) {
                if (!isset($memory['reuse'][$item['hash']])) {
                    continue;
                }

                $check = $this->validator->validate($item['source'], $memory['reuse'][$item['hash']], $targetLocale, $item['context']);
                if ($check['status'] === TranslationEntry::QA_PASSED) {
                    $results[$item['hash']] = $this->result($memory['reuse'][$item['hash']], $check, 'memory');
                    $usage['memory_hits']++;
                    unset($items[$index]);
                }
            }
            $items = array_values($items);
        }

        if (empty($items)) {
            return ['results' => $results] + $usage;
        }

        $glossaryLines = $this->glossaryLines($targetLocale, $items);

        // AI translation on protected text.
        $translated = $this->translate($items, $source, $profile, $glossaryLines, $hints, $mode, $usage);

        foreach ($items as $item) {
            $results[$item['hash']] = $translated[$item['hash']];
        }

        // AI QA (and revision in premium) on everything that passed the structural checks.
        if (in_array($profile['quality_mode'], [TranslationJob::MODE_PROFESSIONAL, TranslationJob::MODE_PREMIUM])) {
            $this->aiQa($items, $results, $source, $profile, $glossaryLines, $usage);
        }

        return ['results' => $results] + $usage;
    }

    /**
     * One call to the translation model; returns a validated result per hash.
     */
    private function translate(array $items, array $source, array $profile, array $glossaryLines, array $hints, string $mode, array &$usage, array $qaIssues = []): array
    {
        $byId = [];
        $promptItems = [];
        foreach ($items as $index => $item) {
            $id = 's' . ($index + 1);
            $protected = $this->tokens->protect($item['source']);
            $byId[$id] = ['item' => $item, 'tokens' => $protected['tokens']];

            $current = $item['current'] ?? null;
            $promptItems[] = [
                'id' => $id,
                'key' => $item['group'] . '.' . $item['key'],
                'text' => $protected['text'],
                'context' => array_diff_key($item['context'], ['key' => 1]),
                'approved_elsewhere' => $hints[$item['hash']] ?? null,
                'previous_problem' => $item['previous_problem'] ?? null,
                'current' => $current !== null ? $this->tokens->applyTo($current, $protected['tokens']) : null,
                'qa_issues' => $qaIssues[$item['hash']] ?? null,
            ];
        }

        $promptMode = $qaIssues ? 'revise' : ($mode === self::MODE_IMPROVE ? 'improve' : 'translate');

        $response = $this->provider->complete(
            $this->prompts->translationSystem(
                $source, $profile, (string)$this->settings->get('translation_rules'), $glossaryLines,
                $this->prompts->terminology($source['locale'], $profile['locale'], array_values(array_unique(array_column($items, 'group'))))
            ),
            $this->prompts->translationUser($promptItems, $promptMode),
            $this->prompts->translationSchema(array_keys($byId)),
            $profile['translation_model']
        );

        $usage['prompt_tokens'] += $response['prompt_tokens'];
        $usage['completion_tokens'] += $response['completion_tokens'];
        $usage['requests']++;

        $returned = $response['data']['translations'] ?? null;
        if (!is_array($returned)) {
            throw new AITranslationException(AITranslationException::INVALID_RESPONSE, 'The response has no "translations" list.');
        }

        $out = [];
        foreach ($returned as $row) {
            $id = is_array($row) ? ($row['id'] ?? null) : null;
            $text = is_array($row) ? ($row['text'] ?? null) : null;

            if (!is_string($id) or !isset($byId[$id]) or !is_string($text)) {
                continue; // unknown / malformed entries are ignored; the string is reported missing below
            }

            $item = $byId[$id]['item'];
            if (isset($out[$item['hash']])) {
                continue;
            }

            $restored = $this->tokens->restore(trim($text), $byId[$id]['tokens']);
            if ($restored['error']) {
                $out[$item['hash']] = $this->failed($restored['error']);
                continue;
            }

            $check = $this->validator->validate($item['source'], $restored['text'], $profile['locale'], $item['context']);
            $out[$item['hash']] = $check['status'] === TranslationEntry::QA_FAILED
                ? $this->failed(implode('; ', $check['issues']))
                : $this->result($restored['text'], $check, 'ai');
        }

        foreach ($byId as $entry) {
            $out[$entry['item']['hash']] ??= $this->failed('Missing from the AI response');
        }

        return $out;
    }

    private function aiQa(array $items, array &$results, array $source, array $profile, array $glossaryLines, array &$usage): void
    {
        $toReview = [];
        foreach ($items as $item) {
            $result = $results[$item['hash']];
            if ($result['text'] !== null) {
                $toReview[] = [
                    'hash' => $item['hash'],
                    'key' => $item['group'] . '.' . $item['key'],
                    'source' => $item['source'],
                    'translation' => $result['text'],
                    'context' => array_diff_key($item['context'], ['key' => 1, 'nearby' => 1]),
                ];
            }
        }

        if (!$toReview) {
            return;
        }

        try {
            $qa = $this->qa->review($toReview, $source, $profile, $glossaryLines);
        } catch (AITranslationException $e) {
            // The translations are valid; without a QA verdict they go to a person.
            foreach ($toReview as $row) {
                $this->flag($results[$row['hash']], 'AI QA unavailable: ' . $e->getMessage());
            }
            // A setup problem (wrong QA model, bad key) will fail every batch: tell the caller so it can stop.
            if (!$e->isRetryable()) {
                $usage['qa_error'] = 'AI QA (' . $profile['qa_model'] . '): ' . $e->getMessage();
            }
            return;
        }

        $usage['qa_prompt_tokens'] += $qa['prompt_tokens'];
        $usage['qa_completion_tokens'] += $qa['completion_tokens'];
        $usage['requests']++;

        $rejected = [];
        foreach ($toReview as $row) {
            $review = $qa['reviews'][$row['hash']] ?? null;

            if (!$review) {
                $this->flag($results[$row['hash']], 'AI QA gave no verdict');
                continue;
            }
            if ($review['approved']) {
                continue;
            }

            $rejected[$row['hash']] = $review;
        }

        if (!$rejected) {
            return;
        }

        $byHash = array_column($items, null, 'hash');

        if ($profile['quality_mode'] === TranslationJob::MODE_PREMIUM) {
            // Premium: the translation model rewrites the rejected strings using the reviewer's issues.
            $revise = array_values(array_intersect_key($byHash, $rejected));
            foreach ($revise as &$item) {
                $item['current'] = $results[$item['hash']]['text'];
            }
            unset($item);

            try {
                $revised = $this->translate($revise, $source, $profile, $glossaryLines, [], self::MODE_TRANSLATE, $usage, array_map(fn($r) => $r['issues'], $rejected));
            } catch (AITranslationException $e) {
                $revised = [];
            }

            foreach ($rejected as $hash => $review) {
                $new = $revised[$hash] ?? null;
                if ($new and $new['text'] !== null) {
                    $results[$hash] = $new;
                    $this->flag($results[$hash], 'Revised after AI QA: ' . $this->summary($review['issues']));
                } else {
                    $this->flag($results[$hash], 'AI QA: ' . $this->summary($review['issues']));
                }
            }

            return;
        }

        // Professional: use the reviewer's correction only if it passes every structural check.
        foreach ($rejected as $hash => $review) {
            $item = $byHash[$hash];

            if ($review['suggestion'] !== null) {
                $check = $this->validator->validate($item['source'], $review['suggestion'], $profile['locale'], $item['context']);
                if ($check['status'] !== TranslationEntry::QA_FAILED) {
                    $results[$hash] = $this->result($review['suggestion'], $check, 'ai');
                    $this->flag($results[$hash], 'Corrected by AI QA: ' . $this->summary($review['issues']));
                    continue;
                }
            }

            $this->flag($results[$hash], 'AI QA: ' . $this->summary($review['issues']));
        }
    }

    /** Glossary rules that apply to at least one string of the batch, plus every keep-as-is term. */
    private function glossaryLines(string $locale, array $items): array
    {
        $terms = collect();
        foreach ($items as $item) {
            $contextText = implode(' ', array_filter([$item['group'], $item['key'], $item['context']['module'] ?? null, implode(' ', $item['context']['used_in'] ?? [])]));
            $terms = $terms->merge($this->glossary->relevant($locale, $item['source'], $contextText));
        }

        $terms = $terms->merge($this->glossary->terms($locale)->filter(fn($t) => $t->keepsOriginal())->take(50));

        return $terms->unique('id')->take(150)->map(fn($t) => $this->glossary->describe($t))->values()->all();
    }

    private function result(string $text, array $check, string $outcome): array
    {
        return [
            'text' => $text,
            'status' => $check['status'],
            'issues' => $check['issues'],
            'outcome' => $outcome,
            'error' => null,
        ];
    }

    private function failed(string $reason): array
    {
        return [
            'text' => null,
            'status' => TranslationEntry::QA_FAILED,
            'issues' => [$reason],
            'outcome' => 'ai',
            'error' => mb_substr('Rejected: ' . $reason, 0, 500),
        ];
    }

    private function flag(array &$result, string $issue): void
    {
        if ($result['text'] === null) {
            return;
        }

        $result['status'] = TranslationEntry::QA_NEEDS_REVIEW;
        $result['issues'][] = mb_substr($issue, 0, 400);
    }

    private function summary(array $issues): string
    {
        return $issues ? implode('; ', array_slice($issues, 0, 3)) : 'not approved';
    }
}
