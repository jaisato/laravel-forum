<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostSummaryResource;
use App\Http\Resources\ThreadResource;
use App\Http\Resources\UserProfileResource;
use App\Models\User;
use App\Queries\MemberReplies;
use App\Queries\ThreadListing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public profiles by username, with the threads the member started and
 * their replies, as the viewer may see them. Never an email: only /me
 * shows one. Erased accounts are not found.
 */
final class UserController extends Controller
{
    public function show(User $user): UserProfileResource
    {
        return new UserProfileResource($user);
    }

    public function threads(Request $request, User $user, ThreadListing $listing): AnonymousResourceCollection
    {
        return ThreadResource::collection($listing->byAuthor($user, $request->user()));
    }

    public function posts(Request $request, User $user, MemberReplies $replies): AnonymousResourceCollection
    {
        return PostSummaryResource::collection($replies->paginate($user, $request->user()));
    }
}
