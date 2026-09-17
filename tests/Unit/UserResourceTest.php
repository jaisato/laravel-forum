<?php

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

/*
 * UserResource is the only place in the app that decides who may read a member's
 * email address: every other field it exposes is public forum content. The rule
 * is that the address goes out to its own owner and to nobody else, so a post
 * listing tells a visitor who wrote each post without also handing them the
 * address book. Dropping the `when()` would still render every page correctly -
 * the leak is invisible from the UI - which is exactly why it is pinned here.
 */

/**
 * Build the resource exactly as a request from $viewer would put it on the wire.
 *
 * Note `resolve()` rather than `toArray()`: a field hidden by `when()` is still
 * present in toArray()'s return as a MissingValue placeholder, and only resolve()
 * filters those out. Asserting on toArray() would therefore report the address as
 * exposed to everybody and never fail when it really was.
 */
function renderUserFor(User $subject, ?User $viewer): array
{
    $request = Request::create('/posts');
    $request->setUserResolver(fn () => $viewer);

    return UserResource::make($subject)->resolve($request);
}

function forumUser(int $id, string $name, string $email): User
{
    return tap(new User(['name' => $name, 'email' => $email]), function (User $user) use ($id) {
        $user->id = $id;
    });
}

it('shows a member their own email address', function () {
    $leo = forumUser(1, 'Leo', 'leo@example.com');

    expect(renderUserFor($leo, $leo))->toHaveKey('email', 'leo@example.com');
});

it('hides a member email address from another signed-in member', function () {
    $leo = forumUser(1, 'Leo', 'leo@example.com');
    $ada = forumUser(2, 'Ada', 'ada@example.com');

    expect(renderUserFor($leo, $ada))->not->toHaveKey('email');
});

it('hides a member email address from a guest', function () {
    $leo = forumUser(1, 'Leo', 'leo@example.com');

    expect(renderUserFor($leo, null))->not->toHaveKey('email');
});

it('still exposes the public identity fields to a guest', function () {
    $leo = forumUser(1, 'Leo', 'leo@example.com');

    expect(renderUserFor($leo, null))
        ->toHaveKey('id', 1)
        ->toHaveKey('name', 'Leo')
        ->toHaveKey('profile_photo_url');
});
