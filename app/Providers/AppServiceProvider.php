<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope is a require-dev package, so a production install
        // (`composer install --no-dev`) does not ship
        // Laravel\Telescope\TelescopeApplicationServiceProvider - the class
        // App\Providers\TelescopeServiceProvider extends. Listing that provider
        // unconditionally in bootstrap/providers.php therefore took down every
        // request and every artisan command on a deployed build, with a
        // class-not-found error raised while the container was still booting.
        //
        // Registering it here instead is what Laravel documents for a
        // development-only install. The class_exists() check is the load-bearing
        // half: environment() alone still breaks a local checkout installed with
        // --no-dev, and it is what makes the app boot on any install where the
        // package is simply not there.
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\Telescope::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();
        Model::preventLazyLoading();
    }
}
