<?php

declare(strict_types=1);

use App\Actions\Forum\UpdatePost;
use App\Actions\Moderation\HidePost;
use App\Enums\ModerationActionType;
use App\Events\PostUpdated;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

it('re-renders the body and marks the post as edited', function () {
    $reply = forumReply(forumThread(), body: 'Primera versión');
    $this->freezeSecond();

    $updated = app(UpdatePost::class)->handle($reply, $reply->author, 'Segunda **versión**');

    expect($updated->fresh())
        ->body->toBe('Segunda **versión**')
        ->body_html->toBe('<p>Segunda <strong>versión</strong></p>')
        ->edited_by_id->toBe($reply->user_id)
        ->and($updated->fresh()->edited_at->equalTo(now()))->toBeTrue();
});

it('leaves an unchanged post alone', function () {
    Event::fake([PostUpdated::class]);
    $reply = forumReply(forumThread(), body: 'Sin cambios');

    app(UpdatePost::class)->handle($reply, $reply->author, 'Sin cambios');

    expect($reply->fresh()->edited_at)->toBeNull();
    Event::assertNotDispatched(PostUpdated::class);
});

it('tells listeners who was mentioned before the edit', function () {
    Event::fake([PostUpdated::class]);
    User::factory()->create(['username' => 'ana']);
    $reply = forumReply(forumThread(), body: 'Hola @ana y @Luis');

    app(UpdatePost::class)->handle($reply, $reply->author, 'Hola @ana y @marta');

    Event::assertDispatched(PostUpdated::class, fn (PostUpdated $event): bool => $event->post->is($reply)
        && $event->previousMentions === ['ana', 'luis']);
});

it('logs edits made by staff on posts of others', function () {
    $reply = forumReply(forumThread());
    $moderator = User::factory()->moderator()->create();

    app(UpdatePost::class)->handle($reply, $moderator, 'Editado por moderación');
    app(UpdatePost::class)->handle($reply, $reply->author, 'Editado por su autor');

    $action = ModerationAction::query()->sole();

    expect($action->action)->toBe(ModerationActionType::PostEdited)
        ->and($action->moderator->is($moderator))->toBeTrue()
        ->and($action->subject->is($reply))->toBeTrue()
        ->and($reply->fresh()->edited_by_id)->toBe($reply->user_id);
});

it('refuses the author once the post was hidden after the request was authorised', function () {
    $reply = forumReply(forumThread(), body: 'Texto original.');
    $authorised = Post::query()->findOrFail($reply->id);
    app(HidePost::class)->handle($reply, User::factory()->moderator()->create());

    expect(fn () => app(UpdatePost::class)->handle($authorised, $reply->author, 'Texto cambiado.'))
        ->toThrow(AuthorizationException::class);
    expect($reply->fresh()->body)->toBe('Texto original.');
});
