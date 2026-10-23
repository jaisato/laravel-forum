<?php

declare(strict_types=1);

use function Pest\Laravel\get;

it('reports the application as healthy for container probes', function () {
    get('/up')->assertOk();
});
