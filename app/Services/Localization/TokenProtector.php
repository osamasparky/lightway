<?php

namespace App\Services\Localization;

/**
 * Replaces everything that must not be translated (placeholders, Blade, HTML tags and
 * entities, URLs, e-mails, Markdown link targets, plural ranges) with numbered markers
 * like ⟦1⟧ before the text goes to the AI, and puts the originals back afterwards.
 *
 * The AI then only sees human-readable text. restore() refuses a translation in which
 * any marker is missing, duplicated or invented.
 */
class TokenProtector
{
    private const OPEN = '⟦';
    private const CLOSE = '⟧';

    /** Order matters: the first alternatives win (Blade before braces, tags before anything inside them). */
    private const PATTERN = '/'
        . '\{\{.*?\}\}'                                   // {{ $blade }} / {{name}}
        . '|\{!!.*?!!\}'                                  // {!! raw !!}
        . '|<\/?[A-Za-z][^<>]*>'                          // HTML tags with their attributes
        . '|&(?:[A-Za-z]+|#\d+|#x[0-9A-Fa-f]+);'          // HTML entities
        . '|\]\([^)\s]+\)'                                // Markdown link target ](url)
        . '|\bhttps?:\/\/[^\s"\'<>)\]]+'                  // URLs
        . '|\b[\w.+\-]+@[\w\-]+(?:\.[\w\-]+)+\b'          // e-mail addresses
        . '|\{\s*[A-Za-z_][A-Za-z0-9_.]*\s*\}'            // {name}
        . '|\{\d+\}|\[\d+,\s*(?:\d+|\*)\]'                // Laravel plural ranges {0} [2,*]
        . '|(?<![\w:\/]):[A-Za-z_][A-Za-z0-9_]*'          // :name
        . '|%(?:\d+\$)?[-+0#]*\d*(?:\.\d+)?[bcdeEfFgGosuxX]' // printf %s %1$d
        . '/su';

    private const ICU = '/\{\s*[A-Za-z_][A-Za-z0-9_]*\s*,\s*(plural|select|selectordinal)\b/';

    /**
     * @return array{text:string, tokens:array<string,string>, protected:bool}
     */
    public function protect(string $text): array
    {
        // ICU messages have translatable text inside their braces: leave them to the prompt rules + validation.
        if (preg_match(self::ICU, $text)) {
            return ['text' => $text, 'tokens' => [], 'protected' => false];
        }

        $tokens = [];
        $masked = preg_replace_callback(self::PATTERN, function ($m) use (&$tokens) {
            $marker = self::OPEN . (count($tokens) + 1) . self::CLOSE;
            $tokens[$marker] = $m[0];

            return $marker;
        }, $text);

        return ['text' => $masked ?? $text, 'tokens' => $tokens, 'protected' => count($tokens) > 0];
    }

    /**
     * @param array<string,string> $tokens from protect()
     * @return array{text:?string, error:?string}
     */
    public function restore(string $translated, array $tokens): array
    {
        preg_match_all('/' . preg_quote(self::OPEN, '/') . '\s*(\d+)\s*' . preg_quote(self::CLOSE, '/') . '/u', $translated, $m);
        $found = array_map(fn($n) => self::OPEN . $n . self::CLOSE, $m[1]);

        $missing = array_diff(array_keys($tokens), $found);
        $unknown = array_diff($found, array_keys($tokens));
        $counts = array_count_values($found);
        $duplicated = array_keys(array_filter($counts, fn($c) => $c > 1));

        if ($missing or $unknown or $duplicated) {
            $parts = [];
            if ($missing) {
                $parts[] = 'missing ' . implode(' ', array_map(fn($k) => $tokens[$k], array_slice(array_values($missing), 0, 5)));
            }
            if ($unknown) {
                $parts[] = 'invented markers ' . implode(' ', array_slice(array_values($unknown), 0, 5));
            }
            if ($duplicated) {
                $parts[] = 'repeated ' . implode(' ', array_map(fn($k) => $tokens[$k] ?? $k, array_slice($duplicated, 0, 5)));
            }

            return ['text' => null, 'error' => 'Protected tokens changed: ' . implode('; ', $parts)];
        }

        $text = preg_replace_callback('/' . preg_quote(self::OPEN, '/') . '\s*(\d+)\s*' . preg_quote(self::CLOSE, '/') . '/u', function ($m) use ($tokens) {
            return $tokens[self::OPEN . $m[1] . self::CLOSE];
        }, $translated);

        return ['text' => $text, 'error' => null];
    }

    /**
     * Masks an existing translation with the source's markers (so ⟦1⟧ means the same token
     * in both), for "improve" and "revise" prompts. Tokens not found stay as they are.
     */
    public function applyTo(string $translation, array $tokens): string
    {
        foreach ($tokens as $marker => $value) {
            $position = strpos($translation, $value);
            if ($position !== false) {
                $translation = substr_replace($translation, $marker, $position, strlen($value));
            }
        }

        return $translation;
    }

    /** Readable text only (markers removed), for length and language checks. */
    public function visibleText(string $text): string
    {
        $plain = preg_replace(self::PATTERN, ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', (string)$plain));
    }
}
