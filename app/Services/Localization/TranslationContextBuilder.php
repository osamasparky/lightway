<?php

namespace App\Services\Localization;

use App\Models\Localization\TranslationKeyUsage;
use Illuminate\Support\Facades\DB;

/**
 * Context for each string sent to the AI, built only from facts in the application:
 * the language file (module), the key name, the shape of the text, where the key is
 * used in the code, and the strings next to it. Nothing is invented; the kind of UI
 * element is a guess and is labelled as such in the prompt.
 */
class TranslationContextBuilder
{
    /** Language file => what it covers in this LMS. Unknown files get no module (never guessed). */
    private const MODULES = [
        'admin' => 'Admin panel',
        'api' => 'Mobile app / API messages',
        'auth' => 'Authentication (login, register, verification)',
        'passwords' => 'Password reset',
        'validation' => 'Form validation error messages',
        'cart' => 'Shopping cart and checkout',
        'financial' => 'Financial: wallet, payments, payouts, sales',
        'panel' => 'User panel (student / instructor dashboard)',
        'webinars' => 'Courses and live classes',
        'quiz' => 'Quizzes and exams',
        'meeting' => 'Meetings and consultations',
        'product' => 'Store and products',
        'navbar' => 'Top navigation',
        'footer' => 'Site footer',
        'notification' => 'Notifications',
        'pagination' => 'Pagination controls',
        'home' => 'Home page and public pages',
        'pages_title' => 'Page titles (browser tab / headings)',
        'discounts' => 'Discounts and coupons',
        'categories' => 'Course categories',
        'forms' => 'Form builder',
        'localization' => 'Translation Manager (admin)',
        'site' => 'Public website',
        'public' => 'Shared public texts',
        'file' => 'Course files and downloads',
    ];

    /** Key-name words => kind of UI element (checked in this order). */
    private const KIND_WORDS = [
        'button' => ['btn', 'button', 'submit', 'cta'],
        'placeholder' => ['placeholder'],
        'title' => ['title', 'heading', 'header', 'headline'],
        'hint' => ['hint', 'help', 'description', 'desc', 'tooltip', 'subtitle', 'caption'],
        'error' => ['error', 'err', 'invalid', 'failed', 'fail', 'not_found', 'required'],
        'success' => ['success', 'saved', 'done', 'successfully'],
        'confirmation' => ['confirm', 'are_you_sure'],
        'navigation' => ['menu', 'nav', 'navbar', 'sidebar', 'breadcrumb', 'tab'],
        'email' => ['email_subject', 'mail', 'email_body'],
        'label' => ['label', 'lbl', 'field', 'column', 'status', 'badge'],
    ];

    /** UI kind => settings key of the recommended maximum expansion (%). */
    public const EXPANSION_SETTING = [
        'button' => 'expansion_button',
        'navigation' => 'expansion_navigation',
        'label' => 'expansion_label',
        'placeholder' => 'expansion_label',
        'title' => 'expansion_title',
    ];

    /**
     * @param array<int, array{hash:string, group:string, key:string, source:string}> $items
     * @return array<string, array> context per hash
     */
    public function build(array $items, string $sourceLocale): array
    {
        $hashes = array_column($items, 'hash');
        $usages = $this->usages($hashes);
        $neighbours = $this->neighbours($items, $sourceLocale);

        $contexts = [];
        foreach ($items as $item) {
            $contexts[$item['hash']] = array_filter([
                'file' => $item['group'],
                'module' => $this->module($item['group']),
                'ui_kind' => $this->kind($item['key'], $item['source']),
                'used_in' => $usages[$item['hash']] ?? null,
                'nearby' => $neighbours[$item['hash']] ?? null,
            ], fn($v) => $v !== null and $v !== []);
        }

        return $contexts;
    }

    public function module(string $group): ?string
    {
        $first = explode('/', $group)[0];

        return self::MODULES[$group] ?? self::MODULES[$first] ?? null;
    }

    /**
     * Guess of the kind of UI element, from the key name first, then from the text shape.
     */
    public function kind(string $key, string $source): ?string
    {
        $words = preg_split('/[^a-z0-9]+/', strtolower($key));
        $last = end($words) ?: '';

        foreach (self::KIND_WORDS as $kind => $markers) {
            foreach ($markers as $marker) {
                if (str_contains($marker, '_') ? str_contains(strtolower($key), $marker) : in_array($marker, $words, true)) {
                    return $kind;
                }
            }
        }

        $plain = trim(strip_tags($source));
        $wordCount = count(preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY));

        if ($plain !== $source and preg_match('/<(p|div|br|ul|li|strong|b|a)\b/i', $source)) {
            return 'rich text';
        }
        if ($wordCount >= 12 or preg_match('/[.!?]\s+\S/u', $plain)) {
            return 'message';
        }
        if ($wordCount <= 4 and !preg_match('/[.:!?]$/u', $plain)) {
            return in_array($last, ['add', 'save', 'delete', 'edit', 'apply', 'send', 'cancel', 'update', 'create', 'remove', 'buy', 'join', 'submit', 'next', 'back', 'close'], true)
                ? 'button' : 'label';
        }

        return null;
    }

    /** Up to three code locations per key, as readable screen paths ("web: cart / payment"). */
    private function usages(array $hashes): array
    {
        if (empty($hashes)) {
            return [];
        }

        $out = [];
        foreach (TranslationKeyUsage::whereIn('key_hash', $hashes)->orderBy('file')->get(['key_hash', 'file']) as $usage) {
            $screen = $this->screen($usage->file);
            if ($screen and count($out[$usage->key_hash] ?? []) < 3 and !in_array($screen, $out[$usage->key_hash] ?? [], true)) {
                $out[$usage->key_hash][] = $screen;
            }
        }

        return $out;
    }

    private function screen(string $file): ?string
    {
        $file = str_replace('\\', '/', $file);

        if (preg_match('#resources/views/(.+)\.blade\.php$#', $file, $m)) {
            $parts = explode('/', $m[1]);
            $area = $parts[0] === 'admin' ? 'admin' : 'website';
            $parts = array_values(array_filter($parts, fn($p) => !in_array($p, ['web', 'default', 'admin', 'includes', 'partials', 'layouts', 'components'], true)));

            return $area . ': ' . implode(' / ', array_slice($parts, -3));
        }

        if (preg_match('#app/Http/Controllers/(.+)\.php$#', $file, $m)) {
            return 'message from ' . str_replace('/', ' / ', $m[1]);
        }

        return null;
    }

    /**
     * Two strings before and after each key in the same file (short ones only, not already in the batch).
     */
    private function neighbours(array $items, string $sourceLocale): array
    {
        $inBatch = array_flip(array_column($items, 'hash'));
        $out = [];

        foreach (array_unique(array_column($items, 'group')) as $group) {
            $rows = DB::table('ltm_translations')
                ->where('locale', $sourceLocale)->where('group', $group)
                ->whereNotNull('value')->where('value', '<>', '')
                ->orderBy('key')
                ->get(['key', 'key_hash', 'value'])
                ->values();

            $position = [];
            foreach ($rows as $index => $row) {
                $position[$row->key_hash] = $index;
            }

            foreach ($items as $item) {
                if ($item['group'] !== $group or !isset($position[$item['hash']])) {
                    continue;
                }

                $i = $position[$item['hash']];
                foreach ([$i - 2, $i - 1, $i + 1, $i + 2] as $j) {
                    $row = $rows[$j] ?? null;
                    if ($row and !isset($inBatch[$row->key_hash]) and mb_strlen($row->value) <= 80) {
                        $out[$item['hash']][] = $row->key . ' = ' . $row->value;
                    }
                }
            }
        }

        return $out;
    }
}
