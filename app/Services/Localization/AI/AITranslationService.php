<?php

namespace App\Services\Localization\AI;

use App\Services\Localization\LanguageRegistry;
use App\Services\Localization\PlaceholderGuard;
use App\Services\Localization\TranslationSettings;

/**
 * Provider-agnostic AI translation: builds the prompt, calls the provider with a strict
 * JSON schema and validates every returned string before it can be saved.
 */
class AITranslationService
{
    public function __construct(
        private AITranslationProvider $provider,
        private PromptBuilder $prompts,
        private PlaceholderGuard $guard,
        private LanguageRegistry $languages,
        private TranslationSettings $settings
    ) {
    }

    public function provider(): AITranslationProvider
    {
        return $this->provider;
    }

    /**
     * @param array<int, array{hash:string, group:string, key:string, source:string, current?:?string}> $items
     * @return array{translations: array<string,string>, errors: array<string,string>, prompt_tokens:int, completion_tokens:int}
     *
     * @throws AITranslationException when the provider call itself fails
     */
    public function translate(array $items, string $sourceLocale, string $targetLocale, bool $regenerate = false): array
    {
        if (!$this->settings->isReady()) {
            throw new AITranslationException(AITranslationException::NOT_CONFIGURED, 'AI translation is not configured (API key and model).');
        }

        $source = $this->languages->find($sourceLocale) ?? ['locale' => $sourceLocale, 'name' => $this->languages->label($sourceLocale), 'native' => $sourceLocale, 'dir' => 'ltr'];
        $target = $this->languages->find($targetLocale);

        if (!$target) {
            throw new AITranslationException(AITranslationException::REQUEST, 'The target language is not enabled in the site settings.');
        }

        // Short ids keep the prompt small and stop the model from touching real keys.
        $byId = [];
        $promptItems = [];
        foreach (array_values($items) as $index => $item) {
            $id = 's' . ($index + 1);
            $byId[$id] = $item;
            $promptItems[] = [
                'id' => $id,
                'key' => $item['group'] . '.' . $item['key'],
                'source' => $item['source'],
                'current' => $item['current'] ?? null,
            ];
        }

        $groups = array_values(array_unique(array_column($items, 'group')));

        $result = $this->provider->complete(
            $this->prompts->system($source, $target, $this->settings->all(), $groups),
            $this->prompts->user($promptItems, $regenerate),
            $this->prompts->schema(array_keys($byId)),
            (string)$this->settings->get('model')
        );

        $translations = [];
        $errors = [];
        $returned = $result['data']['translations'] ?? null;

        if (!is_array($returned)) {
            throw new AITranslationException(AITranslationException::INVALID_RESPONSE, 'The response has no "translations" list.');
        }

        foreach ($returned as $row) {
            $id = is_array($row) ? ($row['id'] ?? null) : null;
            $text = is_array($row) ? ($row['text'] ?? null) : null;

            if (!is_string($id) or !isset($byId[$id]) or !is_string($text)) {
                continue; // unknown / malformed entries are ignored; the item is reported missing below
            }

            $item = $byId[$id];
            $text = trim($text);

            if (isset($translations[$item['hash']])) {
                continue;
            }

            if ($problem = $this->guard->check($item['source'], $text)) {
                $errors[$item['hash']] = 'Rejected: ' . $problem;
                continue;
            }

            $translations[$item['hash']] = $text;
            unset($errors[$item['hash']]);
        }

        foreach ($byId as $item) {
            if (!isset($translations[$item['hash']]) and !isset($errors[$item['hash']])) {
                $errors[$item['hash']] = 'Missing from the AI response';
            }
        }

        return [
            'translations' => $translations,
            'errors' => $errors,
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
        ];
    }

    /**
     * Rough token/cost estimate for a set of source strings.
     *
     * @return array{strings:int, input_tokens:int, output_tokens:int, batches:int, cost:?float}
     */
    public function estimate(int $strings, int $sourceChars, int $batchSize): array
    {
        $batches = $strings > 0 ? (int)ceil($strings / max(1, $batchSize)) : 0;

        // ~4 chars per token for Latin text; JSON wrapping + per-string overhead; ~1,200-token system prompt per batch.
        $textTokens = (int)ceil($sourceChars / 3.5);
        $input = $textTokens + ($strings * 18) + ($batches * 1200);
        $output = (int)ceil($textTokens * 1.6) + ($strings * 10);

        $priceIn = $this->settings->get('price_input_per_million');
        $priceOut = $this->settings->get('price_output_per_million');
        $cost = (is_numeric($priceIn) and is_numeric($priceOut))
            ? round(($input / 1_000_000) * (float)$priceIn + ($output / 1_000_000) * (float)$priceOut, 2)
            : null;

        return ['strings' => $strings, 'input_tokens' => $input, 'output_tokens' => $output, 'batches' => $batches, 'cost' => $cost];
    }
}
