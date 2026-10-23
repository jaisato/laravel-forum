<?php

declare(strict_types=1);

use App\Actions\Moderation\HidePost;
use App\Actions\Moderation\ReportPost;
use App\Enums\ModerationActionType;
use App\Enums\PostStatus;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Reports\Pages\ViewReport;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * A member's report of a reply, filed the way the forum files them.
 */
function reportedReply(): Report
{
    $reply = forumReply(forumThread());

    return app(ReportPost::class)->handle($reply, User::factory()->create(), ReportReason::Spam, 'Publicidad');
}

it('queues the open reports, oldest first', function () {
    $older = Report::factory()->create(['created_at' => now()->subHour()]);
    $newer = Report::factory()->ai()->create();
    $resolved = Report::factory()->resolved()->create();
    $dismissed = Report::factory()->dismissed()->create();
    $this->actingAs(forumUser('moderator'));

    Livewire::test(ListReports::class)
        ->assertCanSeeTableRecords([$older, $newer], inOrder: true)
        ->assertCanNotSeeTableRecords([$resolved, $dismissed]);

    expect(ReportResource::getNavigationBadge())->toBe('2');
});

it('resolves a report, hiding the post when asked', function () {
    $moderator = forumUser('moderator');
    $report = reportedReply();
    $this->actingAs($moderator);

    Livewire::test(ListReports::class)
        ->callAction(TestAction::make('resolve')->table($report), data: ['hide_post' => true, 'note' => 'Spam confirmado'])
        ->assertHasNoFormErrors();

    expect($report->fresh())
        ->status->toBe(ReportStatus::Resolved)
        ->resolved_by_id->toBe($moderator->id)
        ->resolution_note->toBe('Spam confirmado')
        ->and($report->post->fresh()->status)->toBe(PostStatus::Hidden);
});

it('resolves a report leaving the post as it is', function () {
    $report = reportedReply();
    $this->actingAs(forumUser('moderator'));

    Livewire::test(ListReports::class)
        ->callAction(TestAction::make('resolve')->table($report), data: ['hide_post' => false])
        ->assertHasNoFormErrors();

    expect($report->fresh()->status)->toBe(ReportStatus::Resolved)
        ->and($report->post->fresh()->status)->toBe(PostStatus::Published);
});

it('dismisses a report', function () {
    $report = reportedReply();
    $this->actingAs(forumUser('moderator'));

    Livewire::test(ListReports::class)
        ->callAction(TestAction::make('dismiss')->table($report), data: ['note' => 'No es spam'])
        ->assertHasNoFormErrors();

    expect($report->fresh())
        ->status->toBe(ReportStatus::Dismissed)
        ->resolution_note->toBe('No es spam')
        ->and($report->post->fresh()->status)->toBe(PostStatus::Published)
        ->and(ModerationAction::query()->where('action', ModerationActionType::ReportDismissed)->exists())->toBeTrue();
});

it('publishes again a post the AI held, from its report', function () {
    $reply = forumReply(forumThread());
    app(HidePost::class)->handle($reply, null, 'Posible spam');
    $report = app(ReportPost::class)->forAi($reply, ReportReason::Spam, 'Posible spam', 0.92);
    $this->actingAs(forumUser('moderator'));

    Livewire::test(ListReports::class)
        ->callAction(TestAction::make('approvePost')->table($report))
        ->assertActionHidden(TestAction::make('approvePost')->table($report))
        ->callAction(TestAction::make('dismiss')->table($report), data: ['note' => 'Falso positivo']);

    expect($reply->fresh()->status)->toBe(PostStatus::Published)
        ->and($report->fresh()->status)->toBe(ReportStatus::Dismissed);
});

it('offers no decision on a report already closed', function () {
    $closed = Report::factory()->dismissed()->create();
    $this->actingAs(forumUser('moderator'));

    Livewire::test(ListReports::class)
        ->filterTable('status', ReportStatus::Dismissed->value)
        ->assertCanSeeTableRecords([$closed])
        ->assertActionHidden(TestAction::make('resolve')->table($closed))
        ->assertActionHidden(TestAction::make('dismiss')->table($closed));
});

it('shows a report with its post and, for the AI, its confidence', function () {
    $post = Post::factory()->create(['body' => 'Compra seguidores baratos en mi web']);
    $report = Report::factory()->ai(0.87)->create(['post_id' => $post->id]);
    $this->actingAs(forumUser('moderator'));

    Livewire::test(ViewReport::class, ['record' => $report->getRouteKey()])
        ->assertOk()
        ->assertSee('Compra seguidores baratos en mi web')
        ->assertSee('87%');
});
