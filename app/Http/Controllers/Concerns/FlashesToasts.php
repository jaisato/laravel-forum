<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Inertia\Inertia;

/**
 * One-off messages shown as toasts on the next page (Inertia flash data).
 */
trait FlashesToasts
{
    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => __($message)]);
    }
}
