<?php

declare(strict_types=1);

use App\Actions\Moderation\HidePost;
use App\Models\ThreadSubscription;
use App\Models\User;

describe('solution', function () {
    it('marks the solution once, however many times it is sent', function () {
        $thread = forumThread();
        $answer = forumReply($thread);
        $token = apiToken($thread->author);

        foreach (range(1, 2) as $attempt) {
            $this->withToken($token)
                ->putJson("/api/v1/threads/{$thread->id}/solution", ['post_id' => $answer->id])
                ->assertOk()
                ->assertJsonPath('data.solution_post_id', $answer->id)
                ->assertJsonPath('data.is_solved', true);
        }

        expect($answer->author->fresh()->reputation)->toBe(15);
    });

    it('unmarks it, however many times it is sent', function () {
        $thread = forumThread();
        $answer = forumReply($thread);
        $token = apiToken($thread->author);
        $this->withToken($token)->putJson("/api/v1/threads/{$thread->id}/solution", ['post_id' => $answer->id]);

        foreach (range(1, 2) as $attempt) {
            $this->withToken($token)
                ->deleteJson("/api/v1/threads/{$thread->id}/solution")
                ->assertOk()
                ->assertJsonPath('data.solution_post_id', null);
        }

        expect($answer->author->fresh()->reputation)->toBe(0);
    });

    it('takes only a reply of the thread', function () {
        $thread = forumThread();

        $this->withToken(apiToken($thread->author))
            ->putJson("/api/v1/threads/{$thread->id}/solution", ['post_id' => $thread->first_post_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('post_id');

        $this->withToken(apiToken($thread->author))
            ->putJson("/api/v1/threads/{$thread->id}/solution", ['post_id' => forumReply(forumThread())->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('post_id');
    });

    it('is for the author of the thread and staff', function () {
        $thread = forumThread();
        $answer = forumReply($thread);

        $this->withToken(apiToken($answer->author))
            ->putJson("/api/v1/threads/{$thread->id}/solution", ['post_id' => $answer->id])
            ->assertForbidden();

        $this->withToken(apiToken(User::factory()->moderator()->create()))
            ->putJson("/api/v1/threads/{$thread->id}/solution", ['post_id' => $answer->id])
            ->assertOk();
    });

    it('answers 404 to members for threads they may not see', function () {
        $thread = forumThread();
        $answer = forumReply($thread);
        app(HidePost::class)->handle($thread->firstPost, User::factory()->moderator()->create());

        $this->withToken(apiToken($thread->author))
            ->putJson("/api/v1/threads/{$thread->id}/solution", ['post_id' => $answer->id])
            ->assertNotFound();

        $this->withToken(apiToken($thread->author))
            ->deleteJson("/api/v1/threads/{$thread->id}/solution")
            ->assertNotFound();
    });
});

describe('subscription', function () {
    it('follows a thread once, however many times it is sent', function () {
        $thread = forumThread();
        $member = User::factory()->create();
        $token = apiToken($member);

        foreach (range(1, 2) as $attempt) {
            $this->withToken($token)
                ->putJson("/api/v1/threads/{$thread->id}/subscription")
                ->assertNoContent();
        }

        expect(ThreadSubscription::query()->where('user_id', $member->id)->count())->toBe(1);
    });

    it('stops following it, however many times it is sent', function () {
        $thread = forumThread();
        $token = apiToken($thread->author);

        foreach (range(1, 2) as $attempt) {
            $this->withToken($token)
                ->deleteJson("/api/v1/threads/{$thread->id}/subscription")
                ->assertNoContent();
        }

        expect(ThreadSubscription::query()->where('user_id', $thread->author->id)->count())->toBe(0);
    });

    it('needs a member who may take part', function () {
        $thread = forumThread();

        $this->putJson("/api/v1/threads/{$thread->id}/subscription")->assertUnauthorized();

        $this->withToken(apiToken(User::factory()->unverified()->create()))
            ->putJson("/api/v1/threads/{$thread->id}/subscription")
            ->assertForbidden();
    });

    it('answers 404 to members for threads they may not see', function () {
        $thread = forumThread();
        app(HidePost::class)->handle($thread->firstPost, User::factory()->moderator()->create());

        $this->withToken(apiToken(User::factory()->create()))
            ->putJson("/api/v1/threads/{$thread->id}/subscription")
            ->assertNotFound();
    });
});
