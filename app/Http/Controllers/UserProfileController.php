<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PostSummaryResource;
use App\Http\Resources\ThreadResource;
use App\Http\Resources\UserProfileResource;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\MemberReplies;
use App\Queries\ThreadListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public profiles (/u/{username}, spec §4.1) with a tab for the threads the
 * member started and one for their replies. Erased accounts are soft
 * deleted, so their profile is not found.
 */
final class UserProfileController extends Controller
{
    public const array TABS = ['threads', 'posts'];

    private const int DESCRIPTION_LENGTH = 160;

    public function show(Request $request, User $user, ThreadListing $threads, MemberReplies $replies): Response
    {
        /** @var User|null $viewer */
        $viewer = $request->user();
        // An unknown tab shows the threads, like a listing's unknown filter.
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'threads';

        return Inertia::render('users/Show', [
            'profile' => (new UserProfileResource($user))->resolve($request),
            'tab' => $tab,
            'can' => [
                // "Send a message" (ConversationPolicy::message).
                'message' => $viewer !== null && Gate::forUser($viewer)->allows('message', [Conversation::class, $user]),
            ],
            'meta' => [
                'title' => "{$user->name} (@{$user->username})",
                'description' => $user->bio !== null && trim($user->bio) !== ''
                    ? Str::limit(Str::squish($user->bio), self::DESCRIPTION_LENGTH)
                    : __(':name is a member of :forum.', ['name' => $user->name, 'forum' => config('app.name')]),
            ],
            ...($tab === 'threads'
                ? ['threads' => ThreadResource::collection($threads->byAuthor($user, $viewer))]
                : ['posts' => PostSummaryResource::collection($replies->paginate($user, $viewer)->appends(['tab' => 'posts']))]),
        ]);
    }
}
