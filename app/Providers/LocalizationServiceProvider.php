<?php

namespace App\Providers;

use App\Services\Localization\AI\AITranslationProvider;
use App\Services\Localization\AI\OpenAITranslationProvider;
use App\Services\Localization\LanguageRegistry;
use App\Services\Localization\TranslationCatalog;
use App\Services\Localization\TranslationSettings;
use Illuminate\Support\ServiceProvider;

class LocalizationServiceProvider extends ServiceProvider
{
    /** Settings "provider" value => implementation. Add new AI providers here. */
    public const PROVIDERS = [
        'openai' => OpenAITranslationProvider::class,
    ];

    public function register()
    {
        $this->app->scoped(LanguageRegistry::class);
        $this->app->scoped(TranslationSettings::class);
        $this->app->scoped(TranslationCatalog::class);

        $this->app->bind(AITranslationProvider::class, function ($app) {
            $name = $app->make(TranslationSettings::class)->get('provider');
            $class = self::PROVIDERS[$name] ?? self::PROVIDERS['openai'];

            return $app->make($class);
        });
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\ScanTranslationUsage::class,
            ]);
        }
    }
}
