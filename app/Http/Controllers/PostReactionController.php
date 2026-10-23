<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Forum\ReactToPost;
use App\Http\Requests\ReactionRequest;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Gives or takes back a reaction. No toast: the counter itself is the
 * feedback.
 */
final class PostReactionController extends Controller
{
    public function __invoke(ReactionRequest $request, Post $post, ReactToPost $reactToPost): RedirectResponse
    {
        /** @var User $member */
        $member = $request->user();

        $reactToPost->handle($post, $member, $request->type());

        return back();
    }
}
