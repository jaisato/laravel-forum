<?php

declare(strict_types=1);

use App\Actions\Forum\DeleteThread;
use App\Actions\Moderation\HidePost;
use App\Models\User;
use App\Services\Ai\KeywordSimilarThreadFinder;
use App\Services\Ai\SimilarThreadFinder;

it('is the similar thread finder the forum uses', function () {
    expect(app(SimilarThreadFinder::class))->toBeInstanceOf(KeywordSimilarThreadFinder::class);
});

it('finds public threads that share the words, leaving one out on request', function () {
    $first = forumThread();
    $second = forumThread();

    $finder = app(SimilarThreadFinder::class);

    expect($finder->forText('testing the forum')->modelKeys())->toEqualCanonicalizing([$first->id, $second->id])
        ->and($finder->forText('testing the forum', exceptThreadId: $first->id)->modelKeys())->toBe([$second->id])
        ->and($finder->forText('testing the forum', limit: 1))->toHaveCount(1);
});

it('never suggests hidden or deleted threads', function () {
    $moderator = User::factory()->moderator()->create();
    $hidden = forumThread();
    app(HidePost::class)->handle($hidden->firstPost, $moderator, 'Spam');
    $deleted = forumThread();
    app(DeleteThread::class)->handle($deleted, $moderator);
    $public = forumThread();

    expect(app(SimilarThreadFinder::class)->forText('testing the forum')->modelKeys())->toBe([$public->id]);
});

it('has nothing to say about an empty text', function () {
    forumThread();

    expect(app(SimilarThreadFinder::class)->forText('   '))->toBeEmpty();
});
