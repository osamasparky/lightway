<?php

namespace App\Services\Localization;

/**
 * Finds the parts of a string that must survive translation unchanged
 * (Laravel :placeholders, {vars}, {{ expressions }}, printf tokens, plural pipes,
 * HTML tags, URLs, Markdown markers) and checks a translation kept them.
 */
class PlaceholderGuard
{
    private const PATTERNS = [
        'blade' => '/\{\{.*?\}\}/s',
        'icu' => '/\{\s*[A-Za-z_][A-Za-z0-9_]*\s*,\s*(plural|select|selectordinal|number|date|time)\b/',
        'brace' => '/\{\s*[A-Za-z_][A-Za-z0-9_.]*\s*\}/',
        'laravel' => '/(?<![\w:\/]):[A-Za-z_][A-Za-z0-9_]*/',
        'printf' => '/%(?:\d+\$)?[-+ 0#]*\d*(?:\.\d+)?[bcdeEfFgGosuxX]/',
        'plural_range' => '/(?:^|\|)\s*(\{\d+\}|\[\d+,\s*(?:\d+|\*)\])/',
        'url' => '/\bhttps?:\/\/[^\s"\'<>)]+/i',
    ];

    /**
     * Protected tokens of a string, as a sorted list (order may change in a translation).
     */
    public function extract(?string $text): array
    {
        $text = (string)$text;
        $tokens = [];

        // Blade expressions first, and hide them so their inside isn't matched again.
        preg_match_all(self::PATTERNS['blade'], $text, $m);
        foreach ($m[0] as $token) {
            $tokens[] = preg_replace('/\s+/', ' ', $token);
        }
        $rest = preg_replace(self::PATTERNS['blade'], ' ', $text);

        preg_match_all(self::PATTERNS['icu'], $rest, $m);
        foreach ($m[0] as $token) {
            $tokens[] = preg_replace('/\s+/', '', $token);
        }

        foreach (['brace', 'laravel', 'printf', 'url'] as $type) {
            preg_match_all(self::PATTERNS[$type], $rest, $m);
            foreach ($m[0] as $token) {
                $tokens[] = trim($token);
            }
        }

        preg_match_all(self::PATTERNS['plural_range'], $rest, $m);
        foreach ($m[1] as $token) {
            $tokens[] = preg_replace('/\s+/', '', $token);
        }

        foreach ($this->htmlTags($rest) as $tag) {
            $tokens[] = $tag;
        }

        sort($tokens);

        return $tokens;
    }

    /**
     * Human-readable list for the UI / prompt (unique, in order of appearance).
     */
    public function describe(?string $text): array
    {
        return array_values(array_unique($this->extract($text)));
    }

    /**
     * null when the translation is safe, otherwise a short reason.
     */
    public function check(?string $source, ?string $translation): ?string
    {
        $source = (string)$source;
        $translation = (string)$translation;

        if (trim($translation) === '' and trim($source) !== '') {
            return 'empty translation';
        }

        $expected = $this->extract($source);
        $actual = $this->extract($translation);

        if ($expected !== $actual) {
            $missing = array_values(array_diff($expected, $actual));
            $extra = array_values(array_diff($actual, $expected));

            if (empty($missing) and empty($extra)) {
                return 'placeholder count changed';
            }

            $parts = [];
            if ($missing) {
                $parts[] = 'missing ' . implode(' ', array_slice($missing, 0, 5));
            }
            if ($extra) {
                $parts[] = 'unexpected ' . implode(' ', array_slice($extra, 0, 5));
            }

            return implode('; ', $parts);
        }

        // Laravel plural strings: same number of | segments.
        if (substr_count($source, '|') !== substr_count($translation, '|') and preg_match('/\|/', $source) and !preg_match('/<[^>]*\|[^>]*>/', $source)) {
            return 'plural segments changed';
        }

        foreach (['**', '__', '`'] as $marker) {
            if (substr_count($source, $marker) !== substr_count($translation, $marker)) {
                return 'markdown changed (' . $marker . ')';
            }
        }

        return null;
    }

    /**
     * Tag names with their direction plus href/src values, e.g. "<a href=/x>", "</a>", "<br>".
     */
    private function htmlTags(string $text): array
    {
        $tags = [];

        if (!preg_match_all('/<\s*(\/?)\s*([a-z][a-z0-9]*)\b([^>]*)>/i', $text, $m, PREG_SET_ORDER)) {
            return $tags;
        }

        foreach ($m as $match) {
            $name = strtolower($match[2]);
            $tag = '<' . $match[1] . $name;

            if (preg_match_all('/\b(href|src)\s*=\s*["\']([^"\']*)["\']/i', $match[3], $attrs, PREG_SET_ORDER)) {
                foreach ($attrs as $attr) {
                    $tag .= ' ' . strtolower($attr[1]) . '=' . $attr[2];
                }
            }

            $tags[] = $tag . '>';
        }

        return $tags;
    }
}
