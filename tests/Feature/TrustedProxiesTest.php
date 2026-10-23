<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
 * In production the forum runs behind Caddy (compose.prod.yaml): the client
 * address and the HTTPS scheme arrive in X-Forwarded-* headers, which only
 * the proxies listed in TRUSTED_PROXIES may set.
 */

beforeEach(function () {
    // Symfony keeps the trusted proxies in a static: start every test clean.
    Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);

    Route::get('/_test/client', fn (Request $request) => [
        'ip' => $request->ip(),
        'secure' => $request->isSecure(),
        'host' => $request->getHost(),
        'url' => $request->url(),
    ]);
});

/**
 * A request as a trusted proxy forwards it, with a forwarded host and port
 * of its choosing: Caddy passes a client's X-Forwarded-Port through as it
 * came.
 */
function requestThroughProxy(): TestResponse
{
    return test()
        ->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
        ->withHeaders([
            'X-Forwarded-For' => '203.0.113.9',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'evil.example',
            'X-Forwarded-Port' => '666',
        ])
        ->getJson('/_test/client');
}

it('ignores forwarded headers when no proxy is trusted', function () {
    config(['trustedproxy.proxies' => null]);

    requestThroughProxy()->assertExactJson([
        'ip' => '172.18.0.5',
        'secure' => false,
        'host' => 'localhost',
        'url' => 'http://localhost/_test/client',
    ]);
});

it('takes the client and the scheme from a trusted proxy but never its host or port', function () {
    config(['trustedproxy.proxies' => '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16']);

    // The port would end up in every link the forum writes: password reset
    // emails, the sitemap…
    requestThroughProxy()->assertExactJson([
        'ip' => '203.0.113.9',
        'secure' => true,
        'host' => 'localhost',
        'url' => 'https://localhost/_test/client',
    ]);
});

it('reads the trusted proxies from TRUSTED_PROXIES', function () {
    $_SERVER['TRUSTED_PROXIES'] = '172.16.0.0/12';

    try {
        $config = require base_path('config/trustedproxy.php');
    } finally {
        unset($_SERVER['TRUSTED_PROXIES']);
    }

    expect($config['proxies'])->toBe('172.16.0.0/12')
        ->and((require base_path('config/trustedproxy.php'))['proxies'])->toBeNull();
});
