<?php

declare(strict_types=1);

use App\Actions\Moderation\HidePost;
use App\Enums\ModerationActionType;
use App\Enums\PostStatus;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\PostResource;
use App\Models\ModerationAction;
use App\Models\Post;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('approves a post held for review', function () {
    $reply = forumReply(forumThread());
    app(HidePost::class)->handle($reply, null, 'Posible spam');
    $moderator = forumUser('moderator');
    $this->actingAs($moderator);

    Livewire::test(ListPosts::class)
        ->callAction(TestAction::make('approve')->table($reply));

    expect($reply->fresh()->status)->toBe(PostStatus::Published)
        ->and(ModerationAction::query()
            ->where('action', ModerationActionType::PostApproved)
            ->where('moderator_id', $moderator->id)
            ->exists())->toBeTrue();
});

it('hides a post with a reason', function () {
    $reply = forumReply(forumThread());
    $this->actingAs(forumUser('moderator'));

    Livewire::test(ListPosts::class)
        ->callAction(TestAction::make('hide')->table($reply), data: ['reason' => 'Fuera de tono'])
        ->assertHasNoFormErrors();

    expect($reply->fresh()->status)->toBe(PostStatus::Hidden)
        ->and(ModerationAction::query()->where('action', ModerationActionType::PostHidden)->first()?->reason)
        ->toBe('Fuera de tono');
});

it('deletes a post and restores it', function () {
    $reply = forumReply(forumThread());
    $this->actingAs(forumUser('moderator'));

    Livewire::test(ListPosts::class)
        ->callAction(TestAction::make('delete')->table($reply), data: ['reason' => 'Duplicado'])
        ->assertHasNoFormErrors();

    expect(Post::withTrashed()->find($reply->id)?->trashed())->toBeTrue();

    Livewire::test(ListPosts::class)
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$reply])
        ->assertActionHidden(TestAction::make('delete')->table($reply))
        ->callAction(TestAction::make('restore')->table($reply));

    expect($reply->fresh()?->trashed())->toBeFalse();
});

it('offers each decision only where it applies', function () {
    $published = forumReply(forumThread());
    $held = forumReply(forumThread());
    app(HidePost::class)->handle($held, null);
    $this->actingAs(forumUser('moderator'));

    Livewire::test(ListPosts::class)
        ->assertActionHidden(TestAction::make('approve')->table($published))
        ->assertActionVisible(TestAction::make('hide')->table($published))
        ->assertActionHidden(TestAction::make('restore')->table($published))
        ->assertActionVisible(TestAction::make('approve')->table($held));
});

it('filters the posts by status', function () {
    $published = forumReply(forumThread());
    $held = forumReply(forumThread());
    app(HidePost::class)->handle($held, null);
    $this->actingAs(forumUser('moderator'));

    Livewire::test(ListPosts::class)
        ->filterTable('status', PostStatus::PendingReview->value)
        ->assertCanSeeTableRecords([$held])
        ->assertCanNotSeeTableRecords([$published]);
});

it('counts in the navigation the posts held for review', function () {
    $held = forumReply(forumThread());
    app(HidePost::class)->handle($held, null, 'Posible spam');
    forumReply(forumThread());
    $this->actingAs(forumUser('moderator'));

    expect(PostResource::getNavigationBadge())->toBe('1');

    app(HidePost::class)->handle($held->fresh(), forumUser('moderator'), 'Spam.');

    expect(PostResource::getNavigationBadge())->toBeNull();
});
