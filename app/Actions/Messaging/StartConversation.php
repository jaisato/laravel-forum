<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Starts a private conversation with its first message. Who can be written
 * to is the ConversationPolicy::message() rule (never yourself, never
 * someone who turned private messages off, unless you are staff); breaking
 * it is a validation error, whatever the entry point.
 */
final class StartConversation
{
    public function __construct(private readonly SendMessage $sendMessage) {}

    /**
     * @param  Collection<int, User>  $recipients
     *
     * @throws ValidationException
     */
    public function handle(User $sender, Collection $recipients, string $subject, string $body): Conversation
    {
        $recipients = $recipients->unique(fn (User $user): int => $user->id)->values();

        $this->ensureRecipientsCanBeWritten($sender, $recipients);

        return DB::transaction(function () use ($sender, $recipients, $subject, $body): Conversation {
            $conversation = Conversation::query()->create(['subject' => $subject]);

            $conversation->participants()->attach([
                $sender->id,
                ...$recipients->map(fn (User $user): int => $user->id)->all(),
            ]);

            $this->sendMessage->handle($conversation, $sender, $body);

            return $conversation;
        });
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function ensureRecipientsCanBeWritten(User $sender, Collection $recipients): void
    {
        $max = (int) config('forum.messages.max_recipients');

        $error = match (true) {
            $recipients->isEmpty() => __('Choose at least one member to write to.'),
            $recipients->count() > $max => __('A conversation can have at most :max recipients.', ['max' => $max]),
            default => null,
        };

        $refused = $recipients->first(
            fn (User $recipient): bool => Gate::forUser($sender)->denies('message', [Conversation::class, $recipient]),
        );

        if ($error === null && $refused !== null) {
            $error = __('You cannot send private messages to :username.', ['username' => $refused->username]);
        }

        if ($error !== null) {
            throw ValidationException::withMessages(['recipients' => $error]);
        }
    }
}
