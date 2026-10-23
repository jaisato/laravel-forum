<?php

declare(strict_types=1);

use App\Enums\ReportReason;
use App\Models\Report;
use App\Models\User;

it('files a report and thanks the member', function () {
    $reply = forumReply(forumThread());
    $member = User::factory()->create();

    $this->actingAs($member)
        ->from(route('threads.show', $reply->thread))
        ->post(route('posts.reports.store', $reply), ['reason' => 'spam', 'details' => 'Enlaces a una tienda.'])
        ->assertRedirect(route('threads.show', $reply->thread))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Thanks, the moderators will review it.']);

    expect(Report::query()->sole())
        ->post_id->toBe($reply->id)
        ->reporter_id->toBe($member->id)
        ->reason->toBe(ReportReason::Spam)
        ->details->toBe('Enlaces a una tienda.');
});

it('files one open report per member and post', function () {
    $reply = forumReply(forumThread());
    $member = User::factory()->create();

    $this->actingAs($member)->post(route('posts.reports.store', $reply), ['reason' => 'spam']);
    $this->actingAs($member)->post(route('posts.reports.store', $reply), ['reason' => 'abuse']);

    expect(Report::query()->count())->toBe(1);
});

it('validates the report', function (array $data, string $field) {
    $this->actingAs(User::factory()->create())
        ->post(route('posts.reports.store', forumReply(forumThread())), $data)
        ->assertSessionHasErrors($field);
})->with([
    'no reason' => [[], 'reason'],
    'unknown reason' => [['reason' => 'boring'], 'reason'],
    'details too long' => [['reason' => 'other', 'details' => str_repeat('a', 1001)], 'details'],
]);

it('refuses reports of one\'s own posts', function () {
    $reply = forumReply(forumThread());

    $this->actingAs($reply->author)->post(route('posts.reports.store', $reply), ['reason' => 'spam'])->assertForbidden();
});

it('sends guests to the login page', function () {
    $this->post(route('posts.reports.store', forumReply(forumThread())), ['reason' => 'spam'])
        ->assertRedirect(route('login'));
});
