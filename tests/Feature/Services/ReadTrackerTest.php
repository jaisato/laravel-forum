<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use App\Services\ReadTracker;
use Carbon\CarbonImmutable;

it('remembers when and how far a member read a thread', function () {
    $thread = forumThread();
    $member = User::factory()->create();
    $tracker = app(ReadTracker::class);
    $this->freezeSecond();

    expect($tracker->lastReadAt($thread, $member))->toBeNull();

    $tracker->markRead($thread, $member);

    expect($tracker->lastReadAt($thread, $member))->toBeInstanceOf(CarbonImmutable::class)
        ->and($tracker->lastReadAt($thread, $member)->equalTo(now()))->toBeTrue();
});

it('finds the first post a member has not read yet', function () {
    $thread = forumThread();
    $member = User::factory()->create();
    $tracker = app(ReadTracker::class);
    forumReply($thread);
    $tracker->markRead($thread->fresh(), $member);
    Post::factory()->for($thread)->hidden()->create();
    $unread = forumReply($thread);
    forumReply($thread);

    expect($tracker->firstUnreadPost($thread, $member)->is($unread))->toBeTrue();
});

it('starts from the opening post for a thread never read and finds nothing once all is read', function () {
    $thread = forumThread();
    $member = User::factory()->create();
    $tracker = app(ReadTracker::class);

    expect($tracker->firstUnreadPost($thread, $member)->is($thread->firstPost))->toBeTrue();

    $tracker->markRead($thread, $member);

    expect($tracker->firstUnreadPost($thread, $member))->toBeNull();
});

it('adds the read state of a member to thread listings', function () {
    $read = forumThread();
    $unread = forumThread();
    $member = User::factory()->create();
    app(ReadTracker::class)->markRead($read, $member);

    $threads = Thread::query()->withReadState($member)->get()->keyBy('id');
    $asGuest = Thread::query()->withReadState(null)->get()->keyBy('id');

    expect($threads[$read->id]->last_read_post_id)->toBe($read->last_post_id)
        ->and($threads[$read->id]->last_read_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($threads[$unread->id]->last_read_post_id)->toBeNull()
        ->and($threads[$unread->id]->last_read_at)->toBeNull()
        ->and($asGuest[$read->id]->last_read_at)->toBeNull()
        ->and($threads[$read->id]->title)->toBe($read->title);
});
