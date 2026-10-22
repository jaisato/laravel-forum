<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case binding
|--------------------------------------------------------------------------
|
| Feature tests boot the application against a fresh database for every
| test. Unit tests boot the application too (models and resources reach for
| config and the container) but never touch a connection, so they skip the
| per-test migration cost.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');
