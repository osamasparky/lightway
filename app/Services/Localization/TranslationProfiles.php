<?php

namespace App\Services\Localization;

use App\Models\Localization\TranslationJob;
use App\Models\Localization\TranslationProfile;

/**
 * Resolves how a target language is translated: its profile (if any) over the global
 * Translation Settings, plus built-in language rules as a starting point.
 */
class TranslationProfiles
{
    /** Built-in language rules; a profile's own rules are added after these. */
    public const LANGUAGE_RULES = [
        'ar' => 'Write natural Modern Standard Arabic as a professional Arabic copywriter would, not translated-sounding Arabic. Use Arabic punctuation (، ؛ ؟) and keep Western digits as in the source. Keep Qur’anic and Islamic terms in their established Arabic form. Button labels use the imperative or a short verbal noun (e.g. "حفظ", "إضافة إلى السلة").',
        'fr' => 'Use standard French with the formal "vous". Use French punctuation spacing (a narrow space before : ? ! ;).',
        'de' => 'Use the formal "Sie". Keep German compound nouns natural; avoid anglicisms when a common German word exists.',
        'es' => 'Use neutral Spanish and the formal "usted" unless the source is casual. Use opening ¿ and ¡.',
        'ur' => 'Write natural Urdu; keep Islamic terms in their established Urdu form.',
        'id' => 'Use standard Bahasa Indonesia with a polite, neutral register.',
        'tr' => 'Use standard Turkish with the polite "siz" form.',
        'pt' => 'Use neutral Portuguese; keep a professional, friendly register.',
        'bn' => 'Write natural standard Bengali (cholito bhasha).',
        'ne' => 'Write natural standard Nepali with a polite register.',
        'af' => 'Write natural standard Afrikaans.',
    ];

    public function __construct(private TranslationSettings $settings, private LanguageRegistry $languages)
    {
    }

    public function find(string $locale): ?TranslationProfile
    {
        return TranslationProfile::where('target_locale', $locale)->where('active', true)->first();
    }

    /**
     * @return array{profile_id:?int, name:string, locale:string, locale_code:string, region:?string, dir:string,
     *   language:string, native:string, tone:string, audience:string, product_context:string,
     *   language_rules:?string, rules:?string, cultural_notes:?string, quality_mode:string,
     *   translation_model:string, qa_model:string}
     */
    public function resolve(string $locale, ?string $mode = null): array
    {
        $profile = $this->find($locale);
        $language = $this->languages->find($locale) ?? ['locale' => $locale, 'name' => $this->languages->label($locale), 'native' => $locale, 'dir' => 'ltr'];
        $settings = $this->settings->all();

        $mode = in_array($mode, TranslationJob::MODES) ? $mode
            : (in_array($profile?->quality_mode, TranslationJob::MODES) ? $profile->quality_mode
                : (in_array($settings['default_quality_mode'], TranslationJob::MODES) ? $settings['default_quality_mode'] : TranslationJob::MODE_ECONOMY));

        $model = $this->filled($profile?->translation_model) ?? (string)$settings['model'];
        if ($mode === TranslationJob::MODE_PREMIUM and $this->filled($settings['premium_model']) and !$this->filled($profile?->translation_model)) {
            $model = $settings['premium_model'];
        }

        return [
            'profile_id' => $profile?->id,
            'name' => $profile?->name ?: ($language['name'] . ' — ' . trans('localization.profile_default')),
            'locale' => $locale,
            'locale_code' => $this->filled($profile?->locale_code) ?? $locale,
            'region' => $this->filled($profile?->region),
            'dir' => $language['dir'],
            'language' => $language['name'],
            'native' => $language['native'],
            'tone' => $this->filled($profile?->tone) ?? (string)$settings['tone'],
            'audience' => $this->filled($profile?->audience) ?? (string)$settings['audience'],
            'product_context' => $this->filled($profile?->product_context) ?? (string)$settings['product_context'],
            'language_rules' => self::LANGUAGE_RULES[strtolower(substr($locale, 0, 2))] ?? null,
            'rules' => $this->filled($profile?->rules),
            'cultural_notes' => $this->filled($profile?->cultural_notes),
            'quality_mode' => $mode,
            'translation_model' => $model,
            'qa_model' => $this->filled($profile?->qa_model) ?? $this->filled($settings['qa_model']) ?? $model,
        ];
    }

    private function filled($value): ?string
    {
        return trim((string)$value) !== '' ? trim((string)$value) : null;
    }
}
