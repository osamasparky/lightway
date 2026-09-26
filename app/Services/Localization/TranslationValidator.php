<?php

namespace App\Services\Localization;

use App\Models\Localization\TranslationEntry;

/**
 * Rule-based checks on a translation. Structural problems (placeholders, HTML, URLs...)
 * make it FAILED: it must never be saved. Quality signals (glossary, numbers, length,
 * language, untranslated text) make it NEEDS_REVIEW: saved, but flagged for a person.
 */
class TranslationValidator
{
    private const SCRIPTS = [
        'ar' => 'Arabic', 'ur' => 'Arabic', 'fa' => 'Arabic', 'ps' => 'Arabic', 'ckb' => 'Arabic',
        'he' => 'Hebrew', 'ru' => 'Cyrillic', 'uk' => 'Cyrillic', 'bg' => 'Cyrillic', 'kk' => 'Cyrillic',
        'hi' => 'Devanagari', 'ne' => 'Devanagari', 'mr' => 'Devanagari', 'bn' => 'Bengali',
        'zh' => 'Han', 'ja' => 'Han|Hiragana|Katakana', 'ko' => 'Hangul', 'th' => 'Thai', 'el' => 'Greek',
        'ta' => 'Tamil', 'te' => 'Telugu', 'am' => 'Ethiopic',
    ];

    private const VOID_TAGS = ['br', 'hr', 'img', 'input', 'meta', 'link', 'wbr', 'source', 'col', 'area', 'base', 'embed', 'param', 'track'];

    public function __construct(
        private PlaceholderGuard $guard,
        private TokenProtector $tokens,
        private TranslationGlossaryService $glossary,
        private TranslationSettings $settings
    ) {
    }

    /**
     * @param array $context from TranslationContextBuilder (ui_kind, file, module, used_in...)
     * @return array{status:string, issues:string[], metrics:array{source_length:int, target_length:int, expansion:?float}}
     */
    public function validate(string $source, ?string $target, string $locale, array $context = []): array
    {
        $failed = [];
        $review = [];
        $target = (string)$target;

        $sourceText = $this->tokens->visibleText($source);
        $targetText = $this->tokens->visibleText($target);
        $metrics = $this->metrics($sourceText, $targetText);

        if (trim($target) === '') {
            return ['status' => TranslationEntry::QA_FAILED, 'issues' => ['Empty translation'], 'metrics' => $metrics];
        }

        // 1-3. Placeholders, HTML tags, URLs, Markdown and plural segments (same set as the source).
        if ($problem = $this->guard->check($source, $target)) {
            $failed[] = 'Structure: ' . $problem;
        }

        if (preg_match('/[⟦⟧]/u', $target)) {
            $failed[] = 'Structure: protection markers left in the text';
        }

        if ($this->emails($source) !== $this->emails($target)) {
            $failed[] = 'Structure: e-mail addresses changed';
        }

        if ($this->wellNested($source) and !$this->wellNested($target)) {
            $failed[] = 'HTML: tags are not properly nested';
        }

        // 4. Numbers (Arabic-Indic digits count as the same number).
        if ($this->numbers($sourceText) !== $this->numbers($targetText)) {
            $review[] = 'Numbers differ from the source';
        }

        // 6. Glossary.
        $contextText = implode(' ', array_filter([
            $context['file'] ?? null, $context['key'] ?? null, $context['module'] ?? null,
            is_array($context['used_in'] ?? null) ? implode(' ', $context['used_in']) : null,
        ]));
        foreach ($this->glossary->violations($locale, $source, $target, $contextText) as $issue) {
            $review[] = $issue;
        }

        // 8. Untranslated.
        if ($this->looksUntranslated($sourceText, $targetText, $locale)) {
            $review[] = 'Looks untranslated (same as the source)';
        }

        // 11. Language / script.
        if ($issue = $this->scriptIssue($targetText, $locale)) {
            $review[] = $issue;
        }

        // 9. Length and UI expansion.
        foreach ($this->lengthIssues($metrics, $context['ui_kind'] ?? null) as $issue) {
            $review[] = $issue;
        }

        // 10. Suspicious output.
        foreach ($this->suspicious($source, $target) as $issue) {
            $review[] = $issue;
        }

        $status = $failed ? TranslationEntry::QA_FAILED : ($review ? TranslationEntry::QA_NEEDS_REVIEW : TranslationEntry::QA_PASSED);

        return ['status' => $status, 'issues' => array_values(array_unique(array_merge($failed, $review))), 'metrics' => $metrics];
    }

    public function metrics(string $sourceText, string $targetText): array
    {
        $s = mb_strlen($sourceText);
        $t = mb_strlen($targetText);

        return [
            'source_length' => $s,
            'target_length' => $t,
            'expansion' => $s > 0 ? round(($t - $s) / $s * 100, 1) : null,
        ];
    }

    private function lengthIssues(array $metrics, ?string $kind): array
    {
        $issues = [];
        $s = $metrics['source_length'];
        $expansion = $metrics['expansion'];

        if ($expansion === null or $s === 0) {
            return $issues;
        }

        $setting = TranslationContextBuilder::EXPANSION_SETTING[$kind] ?? null;
        $limit = $setting ? $this->settings->get($setting) : null;

        if ($setting and is_numeric($limit) and (float)$limit > 0 and $s <= 60 and $expansion > (float)$limit) {
            $issues[] = sprintf('UI expansion warning: %+.0f%% longer than the source (recommended max %d%% for a %s)', $expansion, (int)$limit, $kind);
        }

        if ($s >= 25 and $metrics['target_length'] < $s * 0.35) {
            $issues[] = 'Much shorter than the source — may be incomplete';
        } elseif ($s >= 25 and $expansion > 200) {
            $issues[] = sprintf('Much longer than the source (%+.0f%%)', $expansion);
        }

        return $issues;
    }

    private function looksUntranslated(string $sourceText, string $targetText, string $locale): bool
    {
        if (mb_strtolower($sourceText) !== mb_strtolower($targetText)) {
            return false;
        }

        $letters = preg_match_all('/\p{L}/u', $sourceText);
        if ($letters < 4) {
            return false;
        }

        // Same-script languages often share single words ("Email", "Status"): only flag phrases there.
        $words = count(preg_split('/\s+/u', $sourceText, -1, PREG_SPLIT_NO_EMPTY));

        return isset(self::SCRIPTS[$this->baseLocale($locale)]) or $words >= 2;
    }

    private function scriptIssue(string $targetText, string $locale): ?string
    {
        $script = self::SCRIPTS[$this->baseLocale($locale)] ?? 'Latin';

        // Keep-as-is glossary terms (brands) are allowed in any script.
        foreach ($this->glossary->terms($locale) as $term) {
            if ($term->keepsOriginal()) {
                $targetText = str_ireplace($term->term, ' ', $targetText);
            }
        }

        $letters = preg_match_all('/\p{L}/u', $targetText);
        if ($letters < 4) {
            return null;
        }

        $expected = preg_match_all('/\p{' . implode('}|\p{', explode('|', $script)) . '}/u', $targetText);

        return $expected / $letters < 0.5 ? 'Does not look like ' . $this->languageName($locale) . ' text' : null;
    }

    private function suspicious(string $source, string $target): array
    {
        $issues = [];

        if (preg_match('/^\s*(translation|translated text|here is|here\'s|sure[,!])\b/i', $target) and !preg_match('/^\s*(translation|here|sure)/i', $source)) {
            $issues[] = 'Contains text that is not part of the translation';
        }

        if (preg_match('/^["“«].*["”»]$/su', trim($target)) and !preg_match('/^["“«].*["”»]$/su', trim($source))) {
            $issues[] = 'Wrapped in quotation marks';
        }

        if (substr_count($source, "\n") !== substr_count($target, "\n")) {
            $issues[] = 'Line breaks changed';
        }

        return $issues;
    }

    private function numbers(string $text): array
    {
        $text = strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        preg_match_all('/\d+(?:[.,]\d+)?/', $text, $m);
        $numbers = array_map(fn($n) => str_replace(',', '.', $n), $m[0]);
        sort($numbers);

        return $numbers;
    }

    private function emails(string $text): array
    {
        preg_match_all('/\b[\w.+\-]+@[\w\-]+(?:\.[\w\-]+)+\b/u', $text, $m);
        $emails = array_map('strtolower', $m[0]);
        sort($emails);

        return $emails;
    }

    private function wellNested(string $html): bool
    {
        if (!preg_match_all('/<\s*(\/?)\s*([a-z][a-z0-9]*)\b[^>]*?(\/?)>/i', $html, $m, PREG_SET_ORDER)) {
            return true;
        }

        $stack = [];
        foreach ($m as $tag) {
            $name = strtolower($tag[2]);
            if (in_array($name, self::VOID_TAGS) or $tag[3] === '/') {
                continue;
            }
            if ($tag[1] === '') {
                $stack[] = $name;
            } elseif (array_pop($stack) !== $name) {
                return false;
            }
        }

        return empty($stack);
    }

    private function baseLocale(string $locale): string
    {
        return strtolower(preg_split('/[-_]/', $locale)[0]);
    }

    private function languageName(string $locale): string
    {
        return app(LanguageRegistry::class)->label($locale);
    }
}
