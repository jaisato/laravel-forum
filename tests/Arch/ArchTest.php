<?php

declare(strict_types=1);

arch('debugging helpers are never committed')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();
