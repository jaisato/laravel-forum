<?php

declare(strict_types=1);
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

arch('debugging helpers are never committed')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

// pest-plugin-arch fails on a namespace that only holds sub-namespaces,
// so the rule lists them from the folders (new ones are picked up).
arch('actions are final classes')
    ->expect(array_map(
        fn (string $directory): string => 'App\\Actions\\'.basename($directory),
        glob(dirname(__DIR__, 2).'/app/Actions/*', GLOB_ONLYDIR) ?: [],
    ))
    ->toBeFinal();

arch('domain events are dispatched only once the transaction commits')
    ->expect('App\Events')
    ->classes()
    ->toImplement(ShouldDispatchAfterCommit::class);
