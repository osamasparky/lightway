<?php

namespace App\Services\Localization;

use App\Models\Localization\TranslationJob;

/**
 * Token and cost estimates before a job, per quality mode, at each model's own price.
 * It is an upper bound: strings reused from translation memory cost nothing.
 */
class TranslationCostService
{
    // Rough sizes in tokens (system prompt with rules + glossary, per-string context and JSON).
    private const SYSTEM_TOKENS = 1500;
    private const QA_SYSTEM_TOKENS = 900;
    private const PER_STRING_CONTEXT = 60;
    private const PER_STRING_OUTPUT = 12;
    private const QA_PER_STRING_OUTPUT = 30;
    private const PREMIUM_REVISION_SHARE = 0.2;

    public function __construct(private TranslationSettings $settings)
    {
    }

    /**
     * @return array{strings:int, batches:int, requests:int, input_tokens:int, output_tokens:int,
     *   qa_input_tokens:int, qa_output_tokens:int, cost:?float, translation_cost:?float, qa_cost:?float}
     */
    public function estimate(int $strings, int $sourceChars, int $batchSize, string $mode, string $model, ?string $qaModel): array
    {
        $batches = $strings > 0 ? (int)ceil($strings / max(1, $batchSize)) : 0;
        $textTokens = (int)ceil($sourceChars / 3.5);

        $input = $batches * self::SYSTEM_TOKENS + $textTokens + $strings * self::PER_STRING_CONTEXT;
        $output = (int)ceil($textTokens * 1.6) + $strings * self::PER_STRING_OUTPUT;
        $requests = $batches;

        $qaInput = 0;
        $qaOutput = 0;

        if (in_array($mode, [TranslationJob::MODE_PROFESSIONAL, TranslationJob::MODE_PREMIUM])) {
            // The QA pass reads source + translation + context and answers briefly.
            $qaInput = $batches * self::QA_SYSTEM_TOKENS + (int)ceil($textTokens * 2.6) + $strings * self::PER_STRING_CONTEXT;
            $qaOutput = $strings * self::QA_PER_STRING_OUTPUT;
            $requests += $batches;
        }

        if ($mode === TranslationJob::MODE_PREMIUM) {
            // Strings the QA rejects are revised once by the translation model.
            $share = self::PREMIUM_REVISION_SHARE;
            $input += (int)ceil(($batches * self::SYSTEM_TOKENS + $textTokens * 2.6 + $strings * self::PER_STRING_CONTEXT) * $share);
            $output += (int)ceil($output * $share);
            $requests += (int)ceil($batches * $share);
        }

        $translationCost = $this->price($model, $input, $output);
        $qaCost = $qaInput > 0 ? $this->price($qaModel ?: $model, $qaInput, $qaOutput) : 0.0;

        return [
            'strings' => $strings,
            'batches' => $batches,
            'requests' => $requests,
            'input_tokens' => $input,
            'output_tokens' => $output,
            'qa_input_tokens' => $qaInput,
            'qa_output_tokens' => $qaOutput,
            'translation_cost' => $translationCost,
            'qa_cost' => $qaCost,
            'cost' => ($translationCost === null or $qaCost === null) ? null : round($translationCost + $qaCost, 2),
        ];
    }

    /** USD for a number of tokens on a model, or null when no price is set for it. */
    public function price(string $model, int $input, int $output): ?float
    {
        $prices = $this->settings->pricesFor($model);

        if ($prices['in'] === null or $prices['out'] === null) {
            return null;
        }

        return round($input / 1_000_000 * $prices['in'] + $output / 1_000_000 * $prices['out'], 4);
    }
}
