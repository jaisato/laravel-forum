<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

class TestingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (!$this->app->runningUnitTests()) {
            return;
        }

        AssertableInertia::macro('hasResource', function (string $key, JsonResource $resource) {
            $this->has($key);

            expect($this->prop($key))
                ->toEqual($resource->response()->getData(true));

            return $this;
        });

        AssertableInertia::macro('hasPaginatedResource', function (string $key, ResourceCollection $resource) {
            $this->hasResource("{$key}.data", $resource);

            expect($this->prop($key))
                ->toHaveKeys(['data', 'links', 'meta']);

            return $this;
        });

        TestResponse::macro('assertComponent', function (string $component) {
            return $this->assertInertia(fn (AssertableInertia $inertia) => $inertia->component($component, true));
        });
    }
}
