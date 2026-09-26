<?php

namespace App\Services\Localization;

use App\Models\Localization\GlossaryTerm;
use Illuminate\Support\Collection;

/**
 * Glossary terms for a target language: which ones apply to a string (for the prompt)
 * and whether a translation follows them (for validation).
 */
class TranslationGlossaryService
{
    /** @var array<string, Collection> */
    private array $cache = [];

    public function terms(string $locale): Collection
    {
        return $this->cache[$locale] ??= GlossaryTerm::forLocale($locale)
            ->orderByRaw('char_length(term) desc')
            ->limit(500)
            ->get();
    }

    public function forget(): void
    {
        $this->cache = [];
    }

    /**
     * Terms that occur in the source text (context terms only where their context matches).
     *
     * @param string $contextText key, file, module and screens of the string, used for "context" terms
     */
    public function relevant(string $locale, string $source, string $contextText = ''): Collection
    {
        return $this->terms($locale)->filter(function (GlossaryTerm $term) use ($source, $contextText) {
            if (!$this->contains($source, $term->term)) {
                return false;
            }

            if ($term->type === GlossaryTerm::TYPE_CONTEXT and trim((string)$term->context) !== '') {
                return $this->contextMatches($term->context, $contextText);
            }

            return true;
        })->values();
    }

    /** Prompt line for one term. */
    public function describe(GlossaryTerm $term): string
    {
        $line = match ($term->type) {
            GlossaryTerm::TYPE_DO_NOT_TRANSLATE => '"' . $term->term . '" → keep exactly as written (do not translate)',
            GlossaryTerm::TYPE_BRAND => '"' . $term->term . '" → brand name, never translate or transliterate',
            GlossaryTerm::TYPE_FORBIDDEN => '"' . $term->term . '" → never render it as "' . $term->translation . '"',
            GlossaryTerm::TYPE_CONTEXT => '"' . $term->term . '" → "' . $term->translation . '" when the context is: ' . $term->context,
            default => '"' . $term->term . '" → "' . $term->translation . '"' . ($term->type === GlossaryTerm::TYPE_TECHNICAL ? ' (technical term)' : ''),
        };

        if ($term->keepsOriginal() and !in_array($term->type, [GlossaryTerm::TYPE_DO_NOT_TRANSLATE, GlossaryTerm::TYPE_BRAND])) {
            $line = '"' . $term->term . '" → keep exactly as written';
        }

        $extra = array_filter([trim((string)$term->rule), trim((string)$term->note)]);

        return $line . ($extra ? ' — ' . implode(' ', $extra) : '');
    }

    /**
     * Glossary problems in a translation (review issues, never hard failures:
     * inflection can legitimately change the surface form of a term).
     *
     * @return string[]
     */
    public function violations(string $locale, string $source, string $target, string $contextText = ''): array
    {
        $issues = [];

        foreach ($this->relevant($locale, $source, $contextText) as $term) {
            if ($term->keepsOriginal()) {
                if (!str_contains(mb_strtolower($target), mb_strtolower($term->term))) {
                    $issues[] = 'Glossary: "' . $term->term . '" must stay as written';
                }
                continue;
            }

            if ($term->type === GlossaryTerm::TYPE_FORBIDDEN) {
                if ($this->containsTranslation($target, (string)$term->translation)) {
                    $issues[] = 'Glossary: "' . $term->translation . '" must not be used for "' . $term->term . '"';
                }
                continue;
            }

            if (!$this->containsTranslation($target, (string)$term->translation)) {
                $issues[] = 'Glossary: "' . $term->term . '" should be translated as "' . $term->translation . '"';
            }
        }

        return $issues;
    }

    private function contains(string $text, string $term): bool
    {
        $term = trim($term);
        if ($term === '') {
            return false;
        }

        return (bool)preg_match('/(?<![\p{L}\p{N}])' . preg_quote($term, '/') . '(?![\p{L}\p{N}])/iu', $text);
    }

    /**
     * Loose match for the target wording: case, Arabic diacritics / letter variants and a final
     * ة/ه (plural and construct forms) are ignored so "الدورات" still counts for "دورة".
     */
    private function containsTranslation(string $text, string $translation): bool
    {
        $text = $this->normalize($text);
        $translation = $this->normalize($translation);

        if ($translation === '') {
            return true;
        }
        if (str_contains($text, $translation)) {
            return true;
        }

        if (preg_match('/\p{Arabic}/u', $translation) and mb_strlen($translation) >= 4) {
            return str_contains($text, preg_replace('/[هة]$/u', '', $translation));
        }

        return false;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $text);          // harakat, tatweel
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه']);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private function contextMatches(string $context, string $contextText): bool
    {
        $contextText = mb_strtolower($contextText);

        foreach (preg_split('/[\s,;\/]+/u', mb_strtolower($context), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            if (mb_strlen($word) >= 3 and str_contains($contextText, $word)) {
                return true;
            }
        }

        return false;
    }
}
