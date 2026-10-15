<?php

namespace App\Providers;

use App\Notifications\PushTransport;
use App\Notifications\WebPushTransport;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PushTransport::class, WebPushTransport::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
