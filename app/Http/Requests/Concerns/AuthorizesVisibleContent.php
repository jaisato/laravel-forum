<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Acting on content the member may not see answers 404 before any other
 * rule is asked (Review Focus 2): a 403 would tell them it exists. The
 * policies' view abilities give that 404.
 */
trait AuthorizesVisibleContent
{
    protected function inspectVisible(string $ability, Model $subject): Response
    {
        $view = Gate::inspect('view', $subject);

        return $view->denied() ? $view : Gate::inspect($ability, $subject);
    }
}
