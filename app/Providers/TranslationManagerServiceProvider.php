<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class TranslationManagerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(
            \Barryvdh\TranslationManager\Manager::class,
            \App\TranslationManager\Manager::class
        );
    }
}