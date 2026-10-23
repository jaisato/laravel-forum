<?php

declare(strict_types=1);

it('points crawlers to the sitemap by its absolute address', function () {
    config(['app.url' => 'https://foro.example.com']);
    app()['env'] = 'production';

    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSeeText('User-agent: *')
        ->assertSeeText('Disallow: /admin')
        ->assertSeeText('Disallow: /search')
        ->assertSeeText('Sitemap: https://foro.example.com/sitemap.xml');
});

it('keeps crawlers out of every environment but production', function () {
    $response = $this->get('/robots.txt')->assertOk();

    expect($response->getContent())->toBe("User-agent: *\nDisallow: /\n");
});

it('is no static file that would shadow the route', function () {
    expect(public_path('robots.txt'))->not->toBeFile();
});
