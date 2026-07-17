<?php

namespace App\Providers;

use App\Contracts\IdentityProvider;
use App\Services\Identity\JbluSsoIdentityProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(IdentityProvider::class, JbluSsoIdentityProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
