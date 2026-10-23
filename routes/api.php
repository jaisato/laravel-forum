<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 (spec §4.7)
|--------------------------------------------------------------------------
|
| Sanctum bearer tokens with abilities (read, write, messages and, for
| staff, moderate). Every route counts against the "api" limiter: 120
| requests a minute per member, 60 per address for guests.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    // Signing in: five tries a minute per login and address, besides the
    // general limit.
    Route::post('auth/token', [AuthTokenController::class, 'store'])
        ->middleware('throttle:api-token')
        ->name('auth.token.store');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::delete('auth/token', [AuthTokenController::class, 'destroy'])->name('auth.token.destroy');

        Route::get('me', [MeController::class, 'show'])->middleware('abilities:read')->name('me.show');
        Route::patch('me', [MeController::class, 'update'])->middleware('abilities:write')->name('me.update');
    });
});
