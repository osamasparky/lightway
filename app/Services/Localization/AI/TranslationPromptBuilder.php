<?php

namespace App\Services\Localization\AI;

use App\Models\Localization\TranslationEntry;
use Illuminate\Support\Facades\DB;

/**
 * Every prompt of the translation pipeline lives here: the translation prompt, the
 * AI QA prompt and their JSON schemas. Nothing else builds prompt text.
 */
class TranslationPromptBuilder
{
    /** Base localization instructions (always sent; the admin's own rules are added after them). */
    public const BASE_INSTRUCTIONS = <<<'TXT'
You are a professional localization translator and editor.

Translate according to meaning, context, culture, audience, product terminology and UI conventions.
Do NOT perform literal word-for-word translation.
The result must read as if it was originally written by a native professional writer in the target language.
Preserve meaning and intent exactly.

Do not:
- summarize, omit information, add information or change the meaning
- translate brand names unless the glossary says so
- translate or modify variables, placeholders, HTML, URLs, IDs or codes
- change numbers unnecessarily

Use the glossary and terminology rules exactly.
Prefer natural native phrasing over literal translation.
TXT;

    /**
     * @param array $profile from TranslationProfiles::resolve()
     * @param string[] $glossaryLines relevant glossary rules for this batch
     * @param array<int, array{source:string,target:string}> $terminology approved wording already used in this language
     */
    public function translationSystem(array $source, array $profile, string $extraRules, array $glossaryLines, array $terminology): string
    {
        $target = $profile['language'] . ($profile['native'] !== $profile['language'] ? ' (' . $profile['native'] . ')' : '');
        $variant = $profile['locale_code'] . ($profile['region'] ? ', ' . $profile['region'] : '');

        $lines = [self::BASE_INSTRUCTIONS, ''];
        $lines[] = 'Product: ' . trim($profile['product_context']);
        $lines[] = 'Audience: ' . trim($profile['audience']) . '. Tone: ' . trim($profile['tone']) . '.';
        $lines[] = "Source language: {$source['name']}. Target language: {$target} — locale {$variant}.";
        $lines[] = '';
        $lines[] = 'How the input works:';
        $lines[] = '- Each string has an "id", its "key" in the application and the "text" to translate.';
        $lines[] = '- "context" says where the string appears: the language file, the module, where it is used in the code ("used_in"), the strings next to it ("nearby") and a guess of the kind of UI element ("ui_kind", guessed from the key name). Use it to choose the right meaning and register; do not translate it.';
        $lines[] = '- Markers like ⟦1⟧ stand for placeholders, HTML tags, links or codes. Copy every marker exactly once and unchanged. You may move a marker to where it belongs in the target grammar; text that was between an opening and a closing tag marker must stay between them. Never add, drop, merge or renumber markers.';
        $lines[] = '- Text without markers may still contain Laravel :placeholders, {placeholders} or ICU {count, plural, ...} syntax: keep those tokens exactly and translate only the words (inside ICU branches too).';
        $lines[] = '- Strings with "|" are plural forms: keep the same number of "|" segments.';
        $lines[] = '- "approved_elsewhere" lists how the same text is already translated in other places; stay consistent with it unless the context clearly needs another wording.';
        $lines[] = '- "previous_problem" means an earlier translation of this string was rejected for that reason: fix it.';
        $lines[] = '- UI labels, buttons, menu items and table headers stay short. Messages and help texts read as natural sentences. Keep the capitalization style of the source where the language has case.';
        $lines[] = '- If a text is only a code, a URL, a marker or a proper noun, return it unchanged.';
        $lines[] = '- Return exactly one translation for every id you receive and no other ids. Return only the JSON.';

        if ($profile['dir'] === 'rtl') {
            $lines[] = '- The target language is written right-to-left. Do not add direction control characters.';
        }

        foreach ([
            'Language rules' => $profile['language_rules'],
            'Rules for ' . $profile['name'] => $profile['rules'],
            'Cultural / localization notes' => $profile['cultural_notes'],
            'Additional rules' => trim($extraRules) !== '' ? trim($extraRules) : null,
        ] as $title => $text) {
            if ($text) {
                $lines[] = '';
                $lines[] = $title . ': ' . $text;
            }
        }

        if ($glossaryLines) {
            $lines[] = '';
            $lines[] = 'Glossary (mandatory):';
            foreach ($glossaryLines as $line) {
                $lines[] = '- ' . $line;
            }
        }

        if ($terminology) {
            $lines[] = '';
            $lines[] = "Terminology already approved in {$profile['language']} (stay consistent):";
            foreach ($terminology as $pair) {
                $lines[] = '- "' . $pair['source'] . '" → "' . $pair['target'] . '"';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<int, array{id:string, key:string, text:string, context?:array, approved_elsewhere?:array, previous_problem?:?string, current?:?string, qa_issues?:array}> $items
     */
    public function translationUser(array $items, string $mode = 'translate'): string
    {
        $strings = [];

        foreach ($items as $item) {
            $entry = ['id' => $item['id'], 'key' => $item['key'], 'text' => $item['text']];

            foreach (['context', 'approved_elsewhere', 'previous_problem'] as $field) {
                if (!empty($item[$field])) {
                    $entry[$field] = $item[$field];
                }
            }
            if ($mode !== 'translate' and !empty($item['current'])) {
                $entry['current_translation'] = $item['current'];
            }
            if ($mode === 'revise' and !empty($item['qa_issues'])) {
                $entry['reviewer_issues'] = $item['qa_issues'];
            }

            $strings[] = $entry;
        }

        $intro = match ($mode) {
            'improve' => 'Improve these translations. Each string has its current translation; write a better, more natural alternative that follows all rules.',
            'revise' => 'A reviewer rejected these translations. Each string has its current translation and the reviewer\'s issues; write a corrected translation that fixes them and follows all rules.',
            default => 'Translate these strings.',
        };

        return $intro . "\n\n" . json_encode(['strings' => $strings], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /** Answer schema; ids are an enum so the model cannot invent or rename keys. */
    public function translationSchema(array $ids): array
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

    public function qaSystem(array $source, array $profile, string $extraRules, array $glossaryLines): string
    {
        $lines = [];
        $lines[] = "You are a senior {$profile['language']} localization reviewer ({$profile['locale_code']}" . ($profile['region'] ? ', ' . $profile['region'] : '') . ') for this product: ' . trim($profile['product_context']);
        $lines[] = 'Audience: ' . trim($profile['audience']) . '. Tone: ' . trim($profile['tone']) . '.';
        $lines[] = '';
        $lines[] = "Review each {$source['name']} → {$profile['language']} translation. Approve it only if it is accurate, complete, natural for a native reader, consistent with the glossary and suitable for its UI context.";
        $lines[] = 'Report concrete issues: mistranslation, omission, addition, literal / unnatural phrasing, wrong register, terminology or glossary violations, grammar, punctuation, wrong length for a UI element.';
        $lines[] = 'When not approved, give a corrected translation in "suggested_translation". It must keep every placeholder, HTML tag, URL and number of the source exactly. When approved, "suggested_translation" is null.';
        $lines[] = 'Do not reject a translation for preferring natural wording over a literal one.';

        foreach ([
            'Language rules' => $profile['language_rules'],
            'Rules for ' . $profile['name'] => $profile['rules'],
            'Cultural / localization notes' => $profile['cultural_notes'],
            'Additional rules' => trim($extraRules) !== '' ? trim($extraRules) : null,
        ] as $title => $text) {
            if ($text) {
                $lines[] = $title . ': ' . $text;
            }
        }

        if ($glossaryLines) {
            $lines[] = '';
            $lines[] = 'Glossary (mandatory):';
            foreach ($glossaryLines as $line) {
                $lines[] = '- ' . $line;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<int, array{id:string, key:string, source:string, translation:string, context?:array}> $items
     */
    public function qaUser(array $items): string
    {
        $strings = array_map(fn($item) => array_filter([
            'id' => $item['id'],
            'key' => $item['key'],
            'source' => $item['source'],
            'translation' => $item['translation'],
            'context' => $item['context'] ?? null,
        ], fn($v) => $v !== null and $v !== []), $items);

        return "Review these translations.\n\n" . json_encode(['strings' => array_values($strings)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public function qaSchema(array $ids): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reviews' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string', 'enum' => array_values($ids)],
                            'approved' => ['type' => 'boolean'],
                            'issues' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'suggested_translation' => ['type' => ['string', 'null']],
                        ],
                        'required' => ['id', 'approved', 'issues', 'suggested_translation'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['reviews'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Short strings already translated in this language (approved first, the batch's files first),
     * used as reference terminology.
     */
    public function terminology(string $source, string $target, array $groups, int $limit = 40): array
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
            ->where(fn($q) => $q->whereNull('t.source_hash')->orWhereRaw('t.source_hash = sha1(s.value)'))
            ->orderByRaw('t.review_status = ? desc', [TranslationEntry::REVIEW_REVIEWED])
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
