<?php

declare(strict_types=1);

use App\Support\Markdown\MentionParser;

function mentionsIn(string $markdown): array
{
    return app(MentionParser::class)->extract($markdown);
}

it('extracts unique lowercase usernames', function () {
    expect(mentionsIn('Hola @Juan y @ana_88, otra vez @JUAN'))->toBe(['juan', 'ana_88']);
});

it('reads mentions next to punctuation', function () {
    expect(mentionsIn('(@juan), @ana. ¿@luis-p?'))->toBe(['juan', 'ana', 'luis-p']);
});

it('ignores email addresses', function () {
    expect(mentionsIn('Escribe a juan@example.com o a soporte@foro.test'))->toBe([]);
});

it('ignores mentions in code', function () {
    $markdown = "Mira `@juan`\n\n```\n@ana\n```\n\n    @luis";

    expect(mentionsIn($markdown))->toBe([]);
});

it('ignores mentions inside links and image descriptions', function () {
    expect(mentionsIn('[hola @juan](https://example.org) ![@ana](https://example.org/a.png)'))->toBe([]);
});

it('ignores names that cannot be usernames', function () {
    expect(mentionsIn('@ab @'.str_repeat('a', 31).' @ @!'))->toBe([]);
});
