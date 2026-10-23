<?php

declare(strict_types=1);

namespace App\Http\Controllers\Moderation;

use App\Actions\Forum\RestorePost;
use App\Actions\Moderation\ApprovePost;
use App\Actions\Moderation\HidePost;
use App\Http\Controllers\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Http\Requests\HidePostRequest;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Inline post moderation from the thread page (staff only, see PostPolicy).
 */
final class PostModerationController extends Controller
{
    use FlashesToasts;

    public function hide(HidePostRequest $request, Post $post, HidePost $hidePost): RedirectResponse
    {
        $hidePost->handle($post, $this->moderator($request), $request->reason());

        $this->toast('Post hidden.');

        return back();
    }

    public function approve(Request $request, Post $post, ApprovePost $approvePost): RedirectResponse
    {
        Gate::authorize('approve', $post);

        $approvePost->handle($post, $this->moderator($request));

        $this->toast('Post approved.');

        return back();
    }

    public function restore(Request $request, Post $post, RestorePost $restorePost): RedirectResponse
    {
        Gate::authorize('restore', $post);

        $restorePost->handle($post, $this->moderator($request));

        $this->toast('Post restored.');

        return back();
    }

    private function moderator(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
