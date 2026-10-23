<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use SensitiveParameter;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The PHP suite asserts on Inertia responses, not on compiled assets,
        // so it must not depend on `npm run build` having been run first.
        $this->withoutVite();
    }

    /**
     * A request made with a token is signed in by that token alone, as in
     * production, where every request starts with new guards: here the
     * application outlives the request, and Sanctum's guard would keep the
     * member the previous token belonged to.
     */
    public function withToken(#[SensitiveParameter] string $token, string $type = 'Bearer'): static
    {
        $this->app['auth']->forgetGuards();

        return parent::withToken($token, $type);
    }

    public function withoutToken(): static
    {
        $this->app['auth']->forgetGuards();

        return parent::withoutToken();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
