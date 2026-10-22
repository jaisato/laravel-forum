<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Markdown\MarkdownRenderer;
use Illuminate\Support\Facades\DB;

function markdown(string $markdown): string
{
    return app(MarkdownRenderer::class)->render($markdown);
}

it('escapes raw HTML instead of rendering it', function (string $payload, string $tag) {
    $html = markdown($payload);

    expect($html)->not->toContain("<{$tag}")
        ->and($html)->toContain("&lt;{$tag}");
})->with([
    'script' => ['<script>alert(1)</script>', 'script'],
    'inline script' => ['Hola <script>alert(1)</script>', 'script'],
    'img onerror' => ['<img src=x onerror=alert(1)>', 'img'],
    'svg onload' => ['<svg onload=alert(1)>', 'svg'],
    'iframe' => ['<iframe src="https://evil.test"></iframe>', 'iframe'],
]);

it('never links to dangerous URL schemes', function (string $payload) {
    $html = markdown($payload);

    expect($html)->not->toContain('href=')
        ->and($html)->not->toContain('src=');
})->with([
    'javascript link' => ['[click](javascript:alert(1))'],
    'mixed case' => ['[click](JaVaScRiPt:alert(1))'],
    'entity encoded colon' => ['[click](javascript&#58;alert(1))'],
    'tab inside scheme' => ['[click](java&#09;script:alert(1))'],
    'autolink' => ['<javascript:alert(1)>'],
    'vbscript' => ['[click](vbscript:msgbox(1))'],
    'data html' => ['[click](data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==)'],
    'unknown scheme' => ['[click](ms-msdt:/id%20PCWDiagnostic)'],
    'javascript image' => ['![x](javascript:alert(1))'],
    'data image' => ['![x](data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=)'],
]);

it('keeps the text of a neutralised link', function () {
    expect(markdown('[click me](javascript:alert(1))'))->toBe('<p>click me</p>');
});

it('escapes quotes in link titles', function () {
    $html = markdown('[x](https://example.org "a\" onmouseover=\"alert(1)")');

    expect($html)->not->toContain('onmouseover="')
        ->and($html)->toContain('title="a&quot; onmouseover=&quot;alert(1)"');
});

it('marks external links as user generated and opens them apart', function () {
    expect(markdown('[docs](https://example.org/page)'))
        ->toBe('<p><a rel="nofollow ugc noopener noreferrer" target="_blank" href="https://example.org/page">docs</a></p>');
});

it('leaves internal links alone', function (string $url) {
    $html = markdown("[thread]({$url})");

    expect($html)->toContain("href=\"{$url}\"")
        ->and($html)->not->toContain('nofollow')
        ->and($html)->not->toContain('target=');
})->with([
    'relative' => ['/t/some-thread'],
    'same host' => ['http://localhost/t/some-thread'],
    'fragment' => ['#post-12'],
]);

it('loads images lazily without leaking the referrer', function () {
    expect(markdown('![diagram](https://example.org/a.png)'))
        ->toContain('src="https://example.org/a.png"')
        ->toContain('loading="lazy"')
        ->toContain('referrerpolicy="no-referrer"');
});

it('links mentions of existing members to their profile', function () {
    User::factory()->create(['username' => 'juan']);

    expect(markdown('Hola @Juan'))->toBe('<p>Hola <a class="mention" href="/u/juan">@juan</a></p>');
});

it('keeps mentions of unknown or erased members as plain text', function () {
    User::factory()->create(['username' => 'gone'])->delete();

    expect(markdown('Hola @ghost y @gone'))->toBe('<p>Hola @ghost y @gone</p>');
});

it('does not link mentions inside code or inside another link', function () {
    User::factory()->create(['username' => 'juan']);

    $html = markdown('`@juan` y [hola @juan](https://example.org)');

    expect($html)->toContain('<code>@juan</code>')
        ->and($html)->not->toContain('class="mention"')
        ->and(substr_count($html, '<a '))->toBe(1);
});

it('resolves every mention of a post with a single query', function () {
    User::factory()->create(['username' => 'juan']);
    User::factory()->create(['username' => 'ana']);

    DB::enableQueryLog();
    $html = markdown('@juan @ana @luis @juan');

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and(substr_count($html, 'class="mention"'))->toBe(3);
});

it('renders GitHub flavoured tables, task lists and strikethrough', function () {
    $html = markdown("| a | b |\n|---|---|\n| 1 | 2 |\n\n- [x] hecho\n- [ ] pendiente\n\n~~tachado~~");

    expect($html)->toContain('<table>')
        ->toContain('<td>1</td>')
        ->toContain('<input checked="" disabled="" type="checkbox"')
        ->toContain('<del>tachado</del>');
});

it('turns single line breaks into breaks as members expect', function () {
    expect(markdown("primera línea\nsegunda línea"))->toBe("<p>primera línea<br>\nsegunda línea</p>");
});

it('caps nesting so hostile input stays cheap to render', function () {
    $html = markdown(str_repeat('>', 500).' profundo');

    expect(substr_count($html, '<blockquote>'))->toBeLessThanOrEqual(20)
        ->and($html)->toContain('profundo');
});

it('builds a plain text excerpt', function () {
    $excerpt = app(MarkdownRenderer::class)->excerpt("# Título\n\nAlgo de **negrita** con [un enlace](https://example.org), `código` y <b>HTML</b>.");

    expect($excerpt)->toBe('Título Algo de negrita con un enlace, código y HTML.');
});

it('cuts the excerpt at a word boundary', function () {
    $excerpt = app(MarkdownRenderer::class)->excerpt(str_repeat('palabra ', 50), 30);

    expect($excerpt)->toBe('palabra palabra palabra…')
        ->and(mb_strlen($excerpt))->toBeLessThanOrEqual(31);
});

it('keeps angle brackets that are plain text in the excerpt', function () {
    expect(app(MarkdownRenderer::class)->excerpt('si a<b entonces `x<y`'))->toBe('si a<b entonces x<y');
});
