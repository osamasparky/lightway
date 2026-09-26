<?php

namespace App\Services\Localization;

/**
 * The site's languages, read from Settings > General (user_languages / rtl_languages /
 * site_language). This is the only list of languages: the Translation Manager never
 * keeps its own copy.
 */
class LanguageRegistry
{
    private const NATIVE_NAMES = [
        'ar' => 'العربية', 'en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch', 'es' => 'Español',
        'it' => 'Italiano', 'pt' => 'Português', 'tr' => 'Türkçe', 'ur' => 'اردو', 'fa' => 'فارسی',
        'id' => 'Bahasa Indonesia', 'ms' => 'Bahasa Melayu', 'bn' => 'বাংলা', 'hi' => 'हिन्दी',
        'ru' => 'Русский', 'zh' => '中文', 'ja' => '日本語', 'ko' => '한국어', 'nl' => 'Nederlands',
        'sw' => 'Kiswahili', 'so' => 'Soomaali', 'ha' => 'Hausa', 'ne' => 'नेपाली', 'af' => 'Afrikaans',
        'he' => 'עברית', 'ps' => 'پښتو', 'ku' => 'Kurdî', 'uz' => 'Oʻzbek', 'kk' => 'Қазақ', 'az' => 'Azərbaycan',
    ];

    private ?array $languages = null;

    /**
     * @return array<string, array{code:string, locale:string, name:string, native:string, dir:string, is_default:bool}>
     */
    public function all(): array
    {
        if ($this->languages !== null) {
            return $this->languages;
        }

        $codes = getGeneralSettings('user_languages');
        $codes = is_array($codes) && count($codes) ? $codes : [getDefaultLocale()];
        $rtl = array_map('strtoupper', (array)getGeneralSettings('rtl_languages'));
        $default = strtoupper(getDefaultLocale());
        $names = getLanguages();

        $languages = [];
        foreach ($codes as $code) {
            $code = strtoupper($code);
            $locale = strtolower($code);

            $languages[$locale] = [
                'code' => $code,
                'locale' => $locale,
                'name' => $names[$code] ?? $code,
                'native' => self::NATIVE_NAMES[$locale] ?? ($names[$code] ?? $code),
                'dir' => in_array($code, $rtl) ? 'rtl' : 'ltr',
                'is_default' => $code == $default,
            ];
        }

        // Default language first.
        uasort($languages, fn($a, $b) => $b['is_default'] <=> $a['is_default']);

        return $this->languages = $languages;
    }

    public function locales(): array
    {
        return array_keys($this->all());
    }

    public function find(?string $locale): ?array
    {
        return $this->all()[strtolower((string)$locale)] ?? null;
    }

    public function has(?string $locale): bool
    {
        return $this->find($locale) !== null;
    }

    /** The language translations are made from (the site's default language). */
    public function sourceLocale(): string
    {
        foreach ($this->all() as $locale => $language) {
            if ($language['is_default']) {
                return $locale;
            }
        }

        return array_key_first($this->all()) ?: config('app.fallback_locale', 'en');
    }

    /** The language's own name (e.g. "العربية" for ar), falling back to the English name. */
    public static function nativeName(string $locale): string
    {
        $locale = strtolower($locale);
        $names = getLanguages();

        return self::NATIVE_NAMES[$locale] ?? ($names[strtoupper($locale)] ?? strtoupper($locale));
    }

    /** Flag image for a language (via its country), or null when there is none. */
    public static function flagUrl(string $locale): ?string
    {
        $country = strtolower((string)localeToCountryCode(strtoupper($locale)));

        return ($country !== '' and is_file(public_path('assets/lightway/flags/' . $country . '.svg')))
            ? '/assets/lightway/flags/' . $country . '.svg'
            : null;
    }

    /** Human label for any locale, including ones only present in the files. */
    public function label(string $locale): string
    {
        $language = $this->find($locale);
        if ($language) {
            return $language['name'];
        }

        $names = getLanguages();

        return $names[strtoupper($locale)] ?? strtoupper($locale);
    }
}
