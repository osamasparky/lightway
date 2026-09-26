<?php

namespace App\Services\Localization\AI;

use App\Models\Localization\GlossaryTerm;
use App\Models\Localization\TranslationEntry;
use App\Services\Localization\PlaceholderGuard;
use Illuminate\Support\Facades\DB;

/**
 * Builds the localization prompt: product context, rules, glossary and the
 * terminology already approved in the target language.
 */
class PromptBuilder
{
    private const LANGUAGE_NOTES = [
        'ar' => 'Write Modern Standard Arabic suitable for an Islamic education audience. Use Arabic punctuation (، ؛ ؟) and Western digits as in the source. Keep Qur’anic and Islamic terms in their established Arabic form.',
        'fr' => 'Use standard French with the formal "vous". Use French punctuation spacing (e.g. a space before : ? !).',
        'de' => 'Use the formal "Sie". Keep German compound nouns natural.',
        'es' => 'Use neutral international Spanish and the formal "usted" unless the source is casual.',
        'ur' => 'Write natural Urdu in Nastaliq conventions; keep Islamic terms in their established Urdu form.',
        'id' => 'Use standard Bahasa Indonesia with a polite, neutral register.',
        'tr' => 'Use standard Turkish with the polite "siz" form.',
    ];

    public function __construct(private PlaceholderGuard $guard)
    {
    }

    public function system(array $source, array $target, array $settings, array $groups = []): string
    {
        $targetName = $target['name'] . ($target['native'] !== $target['name'] ? ' (' . $target['native'] . ')' : '');
        $lines = [];

        $lines[] = "You are a senior native {$targetName} localization specialist. You translate the user interface of this product:";
        $lines[] = trim((string)$settings['product_context']);
        $lines[] = 'Audience: ' . trim((string)$settings['audience']) . '. Tone: ' . trim((string)$settings['tone']) . '.';
        $lines[] = '';
        $lines[] = "Translate every string from {$source['name']} to {$targetName} the way a professional native speaker would write it for this product: natural, idiomatic and consistent, never word-for-word.";
        $lines[] = '';
        $lines[] = 'Rules:';
        $lines[] = '1. Keep the meaning, intent and formality. UI labels (buttons, menus, table headers, badges) stay short; sentences read naturally.';
        $lines[] = '2. Keep every protected token exactly as written, character for character. Each string lists its tokens in "placeholders". This covers Laravel :name, {name}, {{ expressions }}, %s / %d, ICU {count, plural, ...} (translate only the text inside the ICU branches), HTML tags and URLs. You may move a token inside the sentence to fit the grammar.';
        $lines[] = '3. Strings with "|" are Laravel plural forms: keep the same number of "|" segments and any {0} or [1,*] prefixes, and translate each segment.';
        $lines[] = '4. Keep HTML tags and their attributes; translate only visible text (and title/alt text). Keep Markdown markers (**, _, `), URLs, e-mail addresses and numbers unchanged.';
        $lines[] = '5. Never translate brand or product names or glossary terms marked "keep as is". When a glossary term appears, use its glossary translation.';
        $lines[] = '6. Follow the terminology already used in this language (listed below) so the interface stays consistent.';
        $lines[] = '7. Use the punctuation and quotation conventions of the target language. Keep the capitalization style of the source where the language has case.';
        $lines[] = '8. If a string is only a token, code, a URL or a proper noun, return it unchanged.';
        $lines[] = '9. Return exactly one translation for every id you receive and no other ids. Return only the JSON — no notes, quotes or explanations.';

        if (!empty(self::LANGUAGE_NOTES[$target['locale']])) {
            $lines[] = '';
            $lines[] = 'Language notes: ' . self::LANGUAGE_NOTES[$target['locale']];
        }

        if ($target['dir'] === 'rtl') {
            $lines[] = 'The target language is written right-to-left. Do not add direction control characters.';
        }

        $glossary = $this->glossary($target['locale']);
        if (count($glossary)) {
            $lines[] = '';
            $lines[] = 'Glossary:';
            foreach ($glossary as $term) {
                $lines[] = '- "' . $term['term'] . '" → ' . ($term['keep'] ? 'keep as is' : '"' . $term['translation'] . '"') . ($term['note'] ? ' (' . $term['note'] . ')' : '');
            }
        }

        $terminology = $this->terminology($source['locale'], $target['locale'], $groups);
        if (count($terminology)) {
            $lines[] = '';
            $lines[] = "Terminology already used in {$target['name']}:";
            foreach ($terminology as $pair) {
                $lines[] = '- "' . $pair['source'] . '" → "' . $pair['target'] . '"';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<int, array{id:string, key:string, source:string, current?:?string}> $items
     */
    public function user(array $items, bool $regenerate = false): string
    {
        $strings = [];

        foreach ($items as $item) {
            $entry = [
                'id' => $item['id'],
                'key' => $item['key'],
                'text' => $item['source'],
            ];

            $placeholders = $this->guard->describe($item['source']);
            if (count($placeholders)) {
                $entry['placeholders'] = $placeholders;
            }

            if ($regenerate and !empty($item['current'])) {
                $entry['current_translation'] = $item['current'];
            }

            $strings[] = $entry;
        }

        $intro = $regenerate
            ? 'Improve these translations. Each string has its current translation; write a better, more natural alternative that follows all rules.'
            : 'Translate these strings.';

        return $intro . "\n\n" . json_encode(['strings' => $strings], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /** JSON schema for the answer; ids are an enum so the model cannot invent or rename keys. */
    public function schema(array $ids): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'translations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string', 'enum' => array_values($ids)],
                            'text' => ['type' => 'string'],
                        ],
                        'required' => ['id', 'text'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['translations'],
            'additionalProperties' => false,
        ];
    }

    private function glossary(string $locale): array
    {
        return GlossaryTerm::forLocale($locale)
            ->orderBy('term')
            ->limit(200)
            ->get()
            ->map(fn(GlossaryTerm $term) => [
                'term' => $term->term,
                'translation' => $term->translation,
                'keep' => $term->keepsOriginal(),
                'note' => $term->note,
            ])
            ->all();
    }

    /**
     * Short strings already translated (reviewed first), preferring the batch's groups,
     * used as reference terminology.
     */
    private function terminology(string $source, string $target, array $groups, int $limit = 40): array
    {
        $query = DB::table('ltm_translations as t')
            ->join('ltm_translations as s', function ($join) use ($source) {
                $join->on('s.key_hash', '=', 't.key_hash')->where('s.locale', '=', $source);
            })
            ->where('t.locale', $target)
            ->whereNotNull('t.value')->where('t.value', '<>', '')
            ->whereNotNull('s.value')
            ->whereRaw('char_length(s.value) between 3 and 40')
            ->whereRaw("s.value not like '%<%'")
            ->orderByRaw("t.review_status = ? desc", [TranslationEntry::REVIEW_REVIEWED])
            ->limit($limit)
            ->select(['s.value as source', 't.value as target']);

        if (count($groups)) {
            $query->orderByRaw('s.group in (' . implode(',', array_fill(0, count($groups), '?')) . ') desc', $groups);
        }

        return $query->get()
            ->unique('source')
            ->map(fn($row) => ['source' => $row->source, 'target' => $row->target])
            ->values()
            ->all();
    }
}
