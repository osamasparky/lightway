<?php

namespace App\Services\Localization\AI;

use App\Services\Localization\TranslationSettings;

/**
 * Second AI pass (professional / premium modes): a reviewer model reads source,
 * translation, context, glossary and rules and answers per string with
 * {approved, issues, suggested_translation}. Its answer is advice only: the
 * pipeline validates any suggestion like every other AI output.
 */
class TranslationQAService
{
    public function __construct(
        private AITranslationProvider $provider,
        private TranslationPromptBuilder $prompts,
        private TranslationSettings $settings
    ) {
    }

    /**
     * @param array<int, array{hash:string, key:string, source:string, translation:string, context?:array}> $items
     * @return array{reviews: array<string, array{approved:bool, issues:string[], suggestion:?string}>, prompt_tokens:int, completion_tokens:int}
     *
     * @throws AITranslationException
     */
    public function review(array $items, array $source, array $profile, array $glossaryLines): array
    {
        $byId = [];
        $promptItems = [];
        foreach (array_values($items) as $index => $item) {
            $id = 'q' . ($index + 1);
            $byId[$id] = $item['hash'];
            $promptItems[] = ['id' => $id] + $item;
        }

        $result = $this->provider->complete(
            $this->prompts->qaSystem($source, $profile, (string)$this->settings->get('translation_rules'), $glossaryLines),
            $this->prompts->qaUser($promptItems),
            $this->prompts->qaSchema(array_keys($byId)),
            $profile['qa_model'],
            'translation_review'
        );

        $reviews = [];
        foreach ($result['data']['reviews'] ?? [] as $row) {
            $hash = is_array($row) ? ($byId[$row['id'] ?? ''] ?? null) : null;
            if (!$hash or isset($reviews[$hash]) or !is_bool($row['approved'] ?? null)) {
                continue;
            }

            $suggestion = is_string($row['suggested_translation'] ?? null) ? trim($row['suggested_translation']) : null;

            $reviews[$hash] = [
                'approved' => $row['approved'],
                'issues' => array_values(array_filter(array_map(fn($i) => is_string($i) ? mb_substr(trim($i), 0, 300) : null, (array)($row['issues'] ?? [])))),
                'suggestion' => $suggestion !== '' ? $suggestion : null,
            ];
        }

        return [
            'reviews' => $reviews,
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
        ];
    }
}
