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
    ]);
});

function requestThroughProxy(): TestResponse
{
    return test()
        ->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
        ->withHeaders([
            'X-Forwarded-For' => '203.0.113.9',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'evil.example',
        ])
        ->getJson('/_test/client');
}

it('ignores forwarded headers when no proxy is trusted', function () {
    config(['trustedproxy.proxies' => null]);

    requestThroughProxy()->assertExactJson([
        'ip' => '172.18.0.5',
        'secure' => false,
        'host' => 'localhost',
    ]);
});

it('takes the client and the scheme from a trusted proxy but never its host', function () {
    config(['trustedproxy.proxies' => '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16']);

    requestThroughProxy()->assertExactJson([
        'ip' => '203.0.113.9',
        'secure' => true,
        'host' => 'localhost',
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
