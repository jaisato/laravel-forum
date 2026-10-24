<?php

declare(strict_types=1);

use App\Actions\Moderation\DismissReport;
use App\Actions\Moderation\HidePost;
use App\Actions\Moderation\ReportPost;
use App\Actions\Moderation\ResolveReport;
use App\Enums\ModerationActionType;
use App\Enums\PostStatus;
use App\Enums\ReportReason;
use App\Enums\ReportSource;
use App\Enums\ReportStatus;
use App\Events\PostReported;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('files a report from a member', function () {
    $post = Post::factory()->create();
    $member = User::factory()->create();

    $report = app(ReportPost::class)->handle($post, $member, ReportReason::Spam, 'Link farm');

    expect($report->refresh())
        ->post_id->toBe($post->id)
        ->reporter_id->toBe($member->id)
        ->source->toBe(ReportSource::User)
        ->reason->toBe(ReportReason::Spam)
        ->details->toBe('Link farm')
        ->status->toBe(ReportStatus::Open);
});

it('returns the open report instead of filing a duplicate', function () {
    $post = Post::factory()->create();
    $member = User::factory()->create();

    $first = app(ReportPost::class)->handle($post, $member, ReportReason::Spam);
    $again = app(ReportPost::class)->handle($post, $member, ReportReason::Abuse);

    expect($again->id)->toBe($first->id)
        ->and(Report::query()->count())->toBe(1);
});

it('accepts a new report once the previous one is closed', function () {
    $post = Post::factory()->create();
    $member = User::factory()->create();
    $first = app(ReportPost::class)->handle($post, $member, ReportReason::Spam);
    app(DismissReport::class)->handle($first, User::factory()->moderator()->create());

    $second = app(ReportPost::class)->handle($post, $member, ReportReason::Spam);

    expect($second->id)->not->toBe($first->id)
        ->and($second->status)->toBe(ReportStatus::Open);
});

it('files one open AI report per post, saying what the latest verdict says', function () {
    $post = Post::factory()->create();

    $report = app(ReportPost::class)->forAi($post, ReportReason::Abuse, 'Insults another member.', 0.87);
    // The post was edited and the moderator read it again.
    $again = app(ReportPost::class)->forAi($post, ReportReason::Spam, 'Now it advertises a shop.', 0.6);

    expect($again->id)->toBe($report->id)
        ->and($report->refresh())
        ->reporter_id->toBeNull()
        ->source->toBe(ReportSource::Ai)
        ->reason->toBe(ReportReason::Spam)
        ->ai_confidence->toBe(0.6)
        ->details->toBe('Now it advertises a shop.');
});

it('announces new reports only', function () {
    Event::fake([PostReported::class]);
    $post = Post::factory()->create();
    $member = User::factory()->create();

    app(ReportPost::class)->handle($post, $member, ReportReason::Spam);
    app(ReportPost::class)->handle($post, $member, ReportReason::Spam);

    Event::assertDispatchedTimes(PostReported::class, 1);
});

it('resolves every open report of a post, hiding it if asked', function () {
    $reply = forumReply(forumThread());
    $spam = app(ReportPost::class)->handle($reply, User::factory()->create(), ReportReason::Spam);
    $abuse = app(ReportPost::class)->handle($reply, User::factory()->create(), ReportReason::Abuse);
    $moderator = User::factory()->moderator()->create();

    app(ResolveReport::class)->handle($spam, $moderator, hidePost: true, note: 'Spam ring');

    expect($reply->fresh()->status)->toBe(PostStatus::Hidden)
        ->and($spam->fresh())
        ->status->toBe(ReportStatus::Resolved)
        ->resolved_by_id->toBe($moderator->id)
        ->resolution_note->toBe('Spam ring')
        ->and($spam->fresh()->resolved_at)->not->toBeNull()
        ->and($abuse->fresh()->status)->toBe(ReportStatus::Resolved)
        ->and(ModerationAction::query()->pluck('action')->all())
        ->toContain(ModerationActionType::ReportResolved, ModerationActionType::PostHidden);
});

it('resolves a report without touching the post when not asked to hide it', function () {
    $reply = forumReply(forumThread());
    $report = app(ReportPost::class)->handle($reply, User::factory()->create(), ReportReason::Other);

    app(ResolveReport::class)->handle($report, User::factory()->moderator()->create(), hidePost: false);

    expect($report->fresh()->status)->toBe(ReportStatus::Resolved)
        ->and($reply->fresh()->status)->toBe(PostStatus::Published);
});

it('dismisses a single report and leaves the post alone', function () {
    $reply = forumReply(forumThread());
    $dismissed = app(ReportPost::class)->handle($reply, User::factory()->create(), ReportReason::OffTopic);
    $other = app(ReportPost::class)->handle($reply, User::factory()->create(), ReportReason::Abuse);
    $moderator = User::factory()->moderator()->create();

    app(DismissReport::class)->handle($dismissed, $moderator, 'On topic after all');

    expect($dismissed->fresh())
        ->status->toBe(ReportStatus::Dismissed)
        ->resolved_by_id->toBe($moderator->id)
        ->resolution_note->toBe('On topic after all')
        ->and($other->fresh()->status)->toBe(ReportStatus::Open)
        ->and($reply->fresh()->status)->toBe(PostStatus::Published)
        ->and(ModerationAction::query()->sole()->action)->toBe(ModerationActionType::ReportDismissed);
});

it('leaves closed reports alone', function () {
    $report = Report::factory()->dismissed()->create();
    $moderator = User::factory()->moderator()->create();

    app(ResolveReport::class)->handle($report, $moderator, hidePost: true);
    app(DismissReport::class)->handle($report, $moderator);

    expect($report->fresh()->status)->toBe(ReportStatus::Dismissed)
        ->and($report->post->fresh()->status)->toBe(PostStatus::Published)
        ->and(ModerationAction::query()->count())->toBe(0);
});

it('locks every open report of the post in id order before closing them', function () {
    $reply = forumReply(forumThread());
    app(ReportPost::class)->handle($reply, User::factory()->create(), ReportReason::Spam);
    $second = app(ReportPost::class)->handle($reply, User::factory()->create(), ReportReason::Abuse);
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    app(ResolveReport::class)->handle($second, User::factory()->moderator()->create(), hidePost: false);

    // Locking only the report at hand and then updating its siblings made
    // two moderators resolving two reports of one post wait for each other
    // forever (a deadlock): both now queue on the same rows in the same order.
    $lock = collect($queries)->first(fn (string $sql): bool => str_starts_with($sql, 'select') && str_contains($sql, 'from "reports"'));

    expect($lock)->toContain('"post_id" = ?')->toContain('order by "id" asc');
});

it('locks the thread before the reports it closes, as the AI moderator refreshing one does', function (Closure $close) {
    // Reported and held by the AI moderator, so hiding and approving it both
    // have work to do.
    $reply = forumReply(forumThread());
    $report = app(ReportPost::class)->forAi($reply, ReportReason::Abuse, 'Insults.', 0.95);
    app(HidePost::class)->handle($reply, null, 'Insults.');
    $tables = [];
    DB::listen(function (QueryExecuted $query) use (&$tables): void {
        if (preg_match('/^select .* from "(threads|reports)"/', $query->sql, $match) === 1) {
            $tables[] = $match[1].(str_contains($query->sql, 'for update') ? ' for update' : '');
        }
    });

    $close($report, User::factory()->moderator()->create());

    // The AI moderator refreshes its open report under the thread's lock
    // (ModeratePost), as edits and the rest of moderation lock the thread
    // first: locking the reports first, and the thread only to hide or
    // approve the post, left each side holding a row the other needed.
    $lock = DB::getDriverName() === 'pgsql' ? ' for update' : '';
    expect(array_slice($tables, 0, 2))->toBe(['threads'.$lock, 'reports'.$lock]);
})->with([
    'resolving it and hiding the post' => [fn (Report $report, User $moderator) => app(ResolveReport::class)->handle($report, $moderator, hidePost: true)],
    'resolving it' => [fn (Report $report, User $moderator) => app(ResolveReport::class)->handle($report, $moderator, hidePost: false)],
    'dismissing it and approving the post' => [fn (Report $report, User $moderator) => app(DismissReport::class)->handle($report, $moderator, approvePost: true)],
    'dismissing it' => [fn (Report $report, User $moderator) => app(DismissReport::class)->handle($report, $moderator)],
]);

it('refuses a report once the post was hidden after the request was authorised', function () {
    $reply = forumReply(forumThread());
    $authorised = Post::query()->findOrFail($reply->id);
    app(HidePost::class)->handle($reply, User::factory()->moderator()->create());

    expect(fn () => app(ReportPost::class)->handle($authorised, User::factory()->create(), ReportReason::Spam))
        ->toThrow(AuthorizationException::class);
    expect($reply->reports()->count())->toBe(0);
});
