<?php

declare(strict_types=1);

use App\Enums\ModerationActionType;
use App\Enums\ReactionType;
use App\Enums\ReportReason;
use App\Enums\ReportSource;
use App\Enums\ReportStatus;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Report;
use App\Models\Thread;
use App\Models\ThreadRead;
use App\Models\ThreadSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

// A failed statement aborts the surrounding PostgreSQL transaction, so every
// write expected to fail runs inside DB::transaction(), which opens a savepoint
// within the test transaction and rolls back only to it.

it('accepts one reaction of each type per member and post', function () {
    $reaction = Reaction::factory()->create(['type' => ReactionType::Like]);

    Reaction::factory()->for($reaction->post)->for($reaction->user)->create(['type' => ReactionType::Love]);

    expect(fn () => DB::transaction(fn () => Reaction::factory()
        ->for($reaction->post)
        ->for($reaction->user)
        ->create(['type' => ReactionType::Like])))
        ->toThrow(UniqueConstraintViolationException::class)
        ->and($reaction->post->reactions()->count())->toBe(2);
});

it('subscribes a member to a thread only once', function () {
    $subscription = ThreadSubscription::factory()->create();

    expect(fn () => DB::transaction(fn () => ThreadSubscription::factory()
        ->for($subscription->thread)
        ->for($subscription->user)
        ->create()))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('keeps a single read marker per member and thread', function () {
    $read = ThreadRead::factory()->create();

    expect(fn () => DB::transaction(fn () => ThreadRead::factory()
        ->for($read->thread)
        ->for($read->user)
        ->create()))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows a single open report per member and post', function () {
    $report = Report::factory()->create();
    $reportAgain = fn () => DB::transaction(fn () => Report::factory()
        ->for($report->post)
        ->for($report->reporter, 'reporter')
        ->create());

    expect($reportAgain)->toThrow(UniqueConstraintViolationException::class);

    $report->forceFill(['status' => ReportStatus::Resolved])->save();

    // Once the first report is closed the member may report the post again.
    expect($reportAgain()->status)->toBe(ReportStatus::Open);
});

it('allows a single open AI report per post next to member reports', function () {
    $post = Post::factory()->create();
    Report::factory()->for($post)->create();
    Report::factory()->for($post)->ai()->create();

    expect(fn () => DB::transaction(fn () => Report::factory()->for($post)->ai()->create()))
        ->toThrow(UniqueConstraintViolationException::class)
        ->and($post->reports()->count())->toBe(2);
});

it('adds each member to a conversation only once', function () {
    $participant = ConversationParticipant::factory()->create();

    expect(fn () => DB::transaction(fn () => ConversationParticipant::factory()
        ->for($participant->conversation)
        ->for($participant->user)
        ->create()))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('links reactions and reports with their post and their authors', function () {
    $post = Post::factory()->create();
    $reaction = Reaction::factory()->for($post)->create(['type' => ReactionType::Insightful]);
    $report = Report::factory()->for($post)->create(['reason' => ReportReason::OffTopic]);

    $reaction->refresh();
    $report->refresh();

    expect($post->reactions->modelKeys())->toBe([$reaction->id])
        ->and($post->reports->modelKeys())->toBe([$report->id])
        ->and($reaction->type)->toBe(ReactionType::Insightful)
        ->and($reaction->post->is($post))->toBeTrue()
        ->and($reaction->user)->toBeInstanceOf(User::class)
        ->and($report->reason)->toBe(ReportReason::OffTopic)
        ->and($report->source)->toBe(ReportSource::User)
        ->and($report->status)->toBe(ReportStatus::Open)
        ->and($report->reporter)->toBeInstanceOf(User::class)
        ->and($report->post->is($post))->toBeTrue();
});

it('keeps the reported post available to moderators after its deletion', function () {
    $report = Report::factory()->create();

    $report->post->delete();

    expect($report->fresh()->post)->not->toBeNull()
        ->and($report->fresh()->post->trashed())->toBeTrue();
});

it('records AI reports without a reporter', function () {
    $report = Report::factory()->ai(0.93)->create()->refresh();

    expect($report->reporter_id)->toBeNull()
        ->and($report->reporter)->toBeNull()
        ->and($report->source)->toBe(ReportSource::Ai)
        ->and($report->ai_confidence)->toBe(0.93);
});

it('records who closed a report and when', function () {
    $moderator = User::factory()->moderator()->create();

    $resolved = Report::factory()->resolved($moderator)->create()->refresh();
    $dismissed = Report::factory()->dismissed($moderator)->create()->refresh();

    expect($resolved->status)->toBe(ReportStatus::Resolved)
        ->and($resolved->resolver->is($moderator))->toBeTrue()
        ->and($resolved->resolved_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($dismissed->status)->toBe(ReportStatus::Dismissed)
        ->and($dismissed->resolver->is($moderator))->toBeTrue();
});

it('links threads with their subscribers and read markers', function () {
    $thread = Thread::factory()->create();
    $subscription = ThreadSubscription::factory()->for($thread)->create();
    $read = ThreadRead::factory()->for($thread)->create([
        'last_read_post_id' => $thread->first_post_id,
        'last_read_at' => now()->subHour(),
    ]);

    $read->refresh();

    expect($thread->subscriptions->modelKeys())->toBe([$subscription->id])
        ->and($thread->subscribers->modelKeys())->toBe([$subscription->user_id])
        ->and($thread->reads->modelKeys())->toBe([$read->id])
        ->and($subscription->thread->is($thread))->toBeTrue()
        ->and($read->thread->is($thread))->toBeTrue()
        ->and($read->user)->toBeInstanceOf(User::class)
        ->and($read->last_read_post_id)->toBe($thread->first_post_id)
        ->and($read->last_read_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('does not notify subscribers who erased their account', function () {
    $thread = Thread::factory()->create();
    $subscriptions = ThreadSubscription::factory()->for($thread)->count(2)->create();

    $subscriptions->first()->user->delete();

    expect($thread->subscribers->modelKeys())->toBe([$subscriptions->last()->user_id]);
});

it('records moderation actions with a stable subject alias', function () {
    $moderator = User::factory()->moderator()->create();
    $post = Post::factory()->create();

    $action = ModerationAction::factory()
        ->for($moderator, 'moderator')
        ->for($post, 'subject')
        ->create([
            'action' => ModerationActionType::PostHidden,
            'reason' => 'Spam links',
            'meta' => ['previous_status' => 'published'],
        ])
        ->refresh();

    expect($action->subject_type)->toBe('post')
        ->and($action->action)->toBe(ModerationActionType::PostHidden)
        ->and($action->reason)->toBe('Spam links')
        ->and($action->meta)->toBe(['previous_status' => 'published'])
        ->and($action->created_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($action->moderator->is($moderator))->toBeTrue()
        ->and($action->subject->is($post))->toBeTrue();
});

it('resolves deleted subjects in the moderation log, also when eager loading', function () {
    $post = Post::factory()->create();
    $member = User::factory()->create();
    ModerationAction::factory()->for($post, 'subject')->create(['action' => ModerationActionType::PostDeleted]);
    ModerationAction::factory()->for($member, 'subject')->create(['action' => ModerationActionType::UserBanned]);

    $post->delete();
    $member->delete();

    $subjects = ModerationAction::query()->with('subject')->oldest('id')->get()->pluck('subject');

    expect($subjects)->toHaveCount(2)
        ->and($subjects[0]->is($post))->toBeTrue()
        ->and($subjects[1]->is($member))->toBeTrue();
});

it('records actions taken by the system without a moderator', function () {
    $action = ModerationAction::factory()->system()->create()->refresh();

    expect($action->moderator_id)->toBeNull()
        ->and($action->moderator)->toBeNull();
});

it('links conversations with their participants and messages', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = Conversation::factory()->create();
    $conversation->participants()->attach([
        $alice->id => [],
        $bob->id => ['left_at' => now()],
    ]);
    $message = Message::factory()->for($conversation)->for($alice, 'author')->create();

    expect($conversation->participants->modelKeys())->toEqualCanonicalizing([$alice->id, $bob->id])
        ->and($conversation->messages->modelKeys())->toBe([$message->id])
        ->and($message->author->is($alice))->toBeTrue()
        ->and($message->conversation->is($conversation))->toBeTrue()
        ->and($alice->conversations->modelKeys())->toBe([$conversation->id])
        ->and($alice->conversations->first()->pivot->left_at)->toBeNull()
        ->and($alice->conversations->first()->pivot->last_read_message_id)->toBe(0)
        ->and($bob->conversations->first()->pivot)->toBeInstanceOf(ConversationParticipant::class)
        ->and($bob->conversations->first()->pivot->left_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('cascades engagement rows when a thread is purged', function () {
    $thread = Thread::factory()->create();
    $post = $thread->firstPost;
    Reaction::factory()->for($post)->create();
    Report::factory()->for($post)->create();
    ThreadSubscription::factory()->for($thread)->create();
    ThreadRead::factory()->for($thread)->create();

    $thread->forceDelete();

    expect(Post::withTrashed()->count())->toBe(0)
        ->and(Reaction::query()->count())->toBe(0)
        ->and(Report::query()->count())->toBe(0)
        ->and(ThreadSubscription::query()->count())->toBe(0)
        ->and(ThreadRead::query()->count())->toBe(0);
});
