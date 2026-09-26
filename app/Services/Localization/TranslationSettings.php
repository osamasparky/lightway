<?php

namespace App\Services\Localization;

use App\Models\Localization\TranslationSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * AI translation settings. The API key is encrypted at rest (APP_KEY) and is only ever
 * decrypted server-side for the provider call; the UI gets a masked form.
 */
class TranslationSettings
{
    private const CACHE_KEY = 'localization.settings';
    private const SECRET = 'api_key';

    public const DEFAULTS = [
        'provider' => 'openai',
        'model' => '',
        'batch_size' => 40,
        'timeout' => 90,
        'max_strings_per_job' => 5000,
        'tone' => 'professional',
        'audience' => 'Students and teachers of an online Islamic learning platform',
        'product_context' => 'A learning management system (LMS) website: courses, bundles, live classes, store, certificates, instructors and a student panel.',
        'price_input_per_million' => null,
        'price_output_per_million' => null,
        // JSON list from the last successful "Test connection" (fills the model dropdown).
        'available_models' => null,

        // Models: "model" translates; the QA model reviews (professional / premium); premium translates in premium mode.
        'qa_model' => '',
        'premium_model' => '',
        // JSON {"model": {"in": 0.4, "out": 1.6}} — USD per million tokens, per model.
        'model_prices' => null,
        'default_quality_mode' => 'professional',

        // Translation rules (added to every prompt; the base localization rules are always included).
        'translation_rules' => '',

        // Quality & validation
        'use_translation_memory' => 1,
        // Save strings that passed every check (and AI QA) as approved instead of "to review".
        'auto_approve_passed' => 0,
        // Recommended maximum length growth (%) for short UI strings, by kind.
        'expansion_button' => 40,
        'expansion_navigation' => 50,
        'expansion_label' => 60,
        'expansion_title' => 80,

        // Cost controls: refuse to start a job whose estimate is above this (USD, empty = no cap).
        'max_cost_per_job' => null,
    ];

    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            return TranslationSetting::query()
                ->where('name', '!=', self::SECRET)
                ->pluck('value', 'name')
                ->all();
        });

        $settings = array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));

        $settings['batch_size'] = max(5, min(100, (int)$settings['batch_size']));
        $settings['timeout'] = max(15, min(300, (int)$settings['timeout']));
        $settings['max_strings_per_job'] = max(1, (int)$settings['max_strings_per_job']);

        return $settings;
    }

    public function get(string $name)
    {
        return $this->all()[$name] ?? null;
    }

    public function update(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $name => $value) {
            TranslationSetting::updateOrCreate(['name' => $name], ['value' => $value === null ? null : (string)$value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    public function setApiKey(?string $key): void
    {
        $key = trim((string)$key);

        if ($key === '') {
            TranslationSetting::where('name', self::SECRET)->delete();
            return;
        }

        TranslationSetting::updateOrCreate(['name' => self::SECRET], ['value' => Crypt::encryptString($key)]);
    }

    /** Decrypted key for the provider call only. Never return this to a view or JSON. */
    public function apiKey(): ?string
    {
        $value = TranslationSetting::where('name', self::SECRET)->value('value');

        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            return null;
        }
    }

    public function hasApiKey(): bool
    {
        return !empty($this->apiKey());
    }

    /** "sk-…a1b2" style hint so the admin can tell which key is saved. */
    public function maskedApiKey(): ?string
    {
        $key = $this->apiKey();

        if (empty($key)) {
            return null;
        }

        return substr($key, 0, 3) . '…' . substr($key, -4);
    }

    /** USD per million tokens for a model: the per-model price list, else the general prices. */
    public function pricesFor(?string $model): array
    {
        $prices = json_decode((string)$this->get('model_prices'), true);
        $row = is_array($prices) && $model !== null ? ($prices[$model] ?? null) : null;

        if (is_array($row) and is_numeric($row['in'] ?? null) and is_numeric($row['out'] ?? null)) {
            return ['in' => (float)$row['in'], 'out' => (float)$row['out']];
        }

        $in = $this->get('price_input_per_million');
        $out = $this->get('price_output_per_million');

        return (is_numeric($in) and is_numeric($out)) ? ['in' => (float)$in, 'out' => (float)$out] : ['in' => null, 'out' => null];
    }

    public function modelPrices(): array
    {
        $prices = json_decode((string)$this->get('model_prices'), true);

        return is_array($prices) ? $prices : [];
    }

    /** Models the saved/tested key can use, from the last successful connection test. */
    public function availableModels(): array
    {
        $models = json_decode((string)$this->get('available_models'), true);

        return is_array($models) ? $models : [];
    }

    public function isReady(): bool
    {
        return $this->hasApiKey() and trim((string)$this->get('model')) !== '';
    }
}
