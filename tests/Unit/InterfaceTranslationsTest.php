<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

/**
 * The literal keys passed to the translation functions in the files of a
 * directory, as "relative/path: key" pairs. Keys held in variables (option
 * lists) are out of its reach.
 *
 * @param  list<string>  $extensions
 * @return list<array{0: string, 1: string}>
 */
function literalTranslationKeys(string $directory, array $extensions, string $pattern, string $skip = '#^$#'): array
{
    $keys = [];

    foreach (File::allFiles($directory) as $file) {
        $path = str_replace('\\', '/', $file->getRelativePathname());

        if (! in_array($file->getExtension(), $extensions, true) || preg_match($skip, $path) === 1) {
            continue;
        }

        preg_match_all($pattern, $file->getContents(), $matches);

        foreach ($matches[1] as $key) {
            $keys[] = [$path, str_replace("\\'", "'", $key)];
        }
    }

    return $keys;
}

it('translates every string of the Vue interface into Spanish', function () {
    /** @var array<string, string> $spanish */
    $spanish = json_decode(File::get(lang_path('es.json')), true, flags: JSON_THROW_ON_ERROR);

    $keys = literalTranslationKeys(
        resource_path('js'),
        ['vue', 'ts'],
        <<<'REGEX'
        /(?:\$t|\btrans|\bwTrans|\btransChoice)\(\s*'((?:[^'\\]|\\.)*)'/
        REGEX,
        // Generated route helpers and the untouched component library.
        '#^(actions|routes|wayfinder|components/ui)/#',
    );

    $missing = array_values(array_map(
        fn (array $entry): string => "{$entry[0]}: {$entry[1]}",
        array_filter($keys, fn (array $entry): bool => ! array_key_exists($entry[1], $spanish)),
    ));

    expect($keys)->not->toBeEmpty()
        ->and($missing)->toBe([]);
});

it('translates every string of the application into Spanish', function () {
    $keys = [
        ...literalTranslationKeys(app_path(), ['php'], <<<'REGEX'
            /(?:\b__|\btrans|\btrans_choice|->toast)\(\s*'((?:[^'\\]|\\.)*)'/
            REGEX),
        ...literalTranslationKeys(resource_path('views'), ['php'], <<<'REGEX'
            /(?:\b__|\btrans|\btrans_choice|@lang)\(\s*'((?:[^'\\]|\\.)*)'/
            REGEX),
    ];

    $missing = array_values(array_map(
        fn (array $entry): string => "{$entry[0]}: {$entry[1]}",
        array_filter($keys, fn (array $entry): bool => ! Lang::has($entry[1], 'es', false)),
    ));

    expect($keys)->not->toBeEmpty()
        ->and($missing)->toBe([]);
});
