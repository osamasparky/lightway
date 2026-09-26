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
