<?php

declare(strict_types=1);

use App\Filament\Widgets\ActivityChart;
use App\Filament\Widgets\ForumStatsOverview;
use App\Models\Report;
use App\Models\User;
use App\Queries\DailyActivity;
use Livewire\Livewire;

it('shows the members, threads, posts and open reports', function () {
    $thread = forumThread();
    forumReply($thread);
    forumReply($thread);
    User::factory()->unverified()->create();
    Report::factory()->count(2)->create();
    Report::factory()->resolved()->create();
    $this->actingAs(forumUser('moderator'));

    // Members: the two authors, the reporters and the moderator; the
    // unverified account does not count.
    $members = User::query()->whereNotNull('email_verified_at')->count();

    Livewire::test(ForumStatsOverview::class)
        ->assertSeeInOrder(['Members', (string) $members, 'Threads', '1', 'Posts', '3', 'Open reports', '2']);
});

it('draws the activity of the last 30 days', function () {
    forumThread();
    $this->actingAs(forumUser('moderator'));

    Livewire::test(ActivityChart::class)->assertOk();
});

it('counts the threads, posts and new members of each of the last days', function () {
    $this->travelTo(now()->setTime(12, 0));
    $thread = forumThread();

    $this->travel(-2)->days();
    forumReply($thread);
    $this->travelBack();
    $this->travelTo(now()->setTime(12, 0));

    $activity = app(DailyActivity::class)->lastDays(7);

    expect($activity['days'])->toHaveCount(7)
        ->and($activity['days'][6])->toBe(now()->toDateString())
        ->and($activity['threads'])->toBe([0, 0, 0, 0, 0, 0, 1])
        ->and($activity['posts'])->toBe([0, 0, 0, 0, 1, 0, 1])
        ->and($activity['members'])->toBe([0, 0, 0, 0, 1, 0, 1]);
});
