<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::defaultView('pagination::default');
        Relation::morphMap([
        'webinar' => \App\Models\Webinar::class,
        'bundle' => \App\Models\Bundle::class,
        'product' => \App\Models\Product::class,
        'subscribe' => \App\Models\Subscribe::class,
      ]);
    }
}
