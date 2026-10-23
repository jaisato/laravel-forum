<?php

declare(strict_types=1);

use App\Actions\Forum\DeleteThread;
use App\Jobs\Ai\GenerateThreadEmbedding;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

$needsPostgres = fn (): bool => DB::connection()->getDriverName() !== 'pgsql';

it('queues an embedding for every thread, deleted ones aside', function () {
    $threads = [forumThread(), forumThread()];
    app(DeleteThread::class)->handle(forumThread(), User::factory()->moderator()->create());
    config(['forum.ai.enabled' => true, 'forum.ai.similar.enabled' => true, 'forum.ai.moderation.enabled' => false]);
    Queue::fake();

    $this->artisan('forum:embed-threads')
        ->expectsOutputToContain('2 threads queued')
        ->assertSuccessful();

    Queue::assertPushed(GenerateThreadEmbedding::class, 2);
    Queue::assertPushed(GenerateThreadEmbedding::class, fn (GenerateThreadEmbedding $job): bool => $job->thread->is($threads[0]) && ! $job->force);

    $this->artisan('forum:embed-threads --force')->assertSuccessful();

    Queue::assertPushed(GenerateThreadEmbedding::class, fn (GenerateThreadEmbedding $job): bool => $job->force);
})->skip($needsPostgres, 'pgvector lives in PostgreSQL.');

it('refuses while the feature is off', function () {
    Queue::fake();

    $this->artisan('forum:embed-threads')
        ->expectsOutputToContain('FORUM_AI_ENABLED')
        ->assertFailed();

    Queue::assertNothingPushed();
});

it('refuses without pgvector', function () {
    config(['forum.ai.enabled' => true, 'forum.ai.similar.enabled' => true]);
    Queue::fake();

    $this->artisan('forum:embed-threads')
        ->expectsOutputToContain('PostgreSQL')
        ->assertFailed();

    Queue::assertNothingPushed();
})->skip(fn (): bool => DB::connection()->getDriverName() === 'pgsql', 'Runs where pgvector is missing.');
