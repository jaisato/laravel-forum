<?php

declare(strict_types=1);

use App\Jobs\Ai\GenerateThreadEmbedding;
use App\Jobs\Ai\GenerateThreadSummary;
use App\Jobs\Ai\ModeratePost;
use Illuminate\Support\Facades\Queue;

/*
 * Calls to AI providers are slow and rate limited: they run on the ai queue
 * (Horizon's supervisor-ai, with a longer timeout), so they never delay the
 * notifications of the default one.
 */

it('queues every AI job on the ai queue', function () {
    $thread = forumThread();
    Queue::fake();

    ModeratePost::dispatch($thread->firstPost);
    GenerateThreadSummary::dispatch($thread);
    GenerateThreadEmbedding::dispatch($thread);

    Queue::assertPushedOn('ai', ModeratePost::class);
    Queue::assertPushedOn('ai', GenerateThreadSummary::class);
    Queue::assertPushedOn('ai', GenerateThreadEmbedding::class);
});

it('tries a provider call three times at most, waiting 10 seconds and then a minute', function (Closure $job) {
    $job = $job();

    // One wait before each retry, none unused (docs/06-ia.md, section 4).
    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([10, 60]);
})->with([
    'moderation' => [fn () => new ModeratePost(forumThread()->firstPost)],
    'summary' => [fn () => new GenerateThreadSummary(forumThread())],
    'embedding' => [fn () => new GenerateThreadEmbedding(forumThread())],
]);
