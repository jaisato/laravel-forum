<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * A new private conversation: the usernames of its recipients, a subject
 * and the first message. Whether each recipient may be written to is the
 * ConversationPolicy::message() rule, which StartConversation applies.
 */
final class StoreConversationRequest extends FormRequest
{
    /** @var Collection<int, User>|null */
    private ?Collection $members = null;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Conversation::class) ?? false;
    }

    /**
     * "@Ana" and "ana" name the same member: usernames are stored lower case.
     */
    protected function prepareForValidation(): void
    {
        $recipients = $this->input('recipients');

        if (is_array($recipients)) {
            $this->merge(['recipients' => array_values(array_unique(array_map(
                fn (mixed $username): mixed => is_string($username) ? Str::lower(ltrim(trim($username), '@')) : $username,
                $recipients,
            )))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipients' => ['required', 'array', 'max:'.(int) config('forum.messages.max_recipients')],
            'recipients.*' => ['required', 'string'],
            'subject' => ['required', 'string', 'max:'.(int) config('forum.messages.subject_max')],
            'body' => MessageBodyRules::rules(),
        ];
    }

    /**
     * Every recipient must be a member of the forum.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['recipients', 'recipients.*'])) {
                return;
            }

            /** @var list<string> $usernames */
            $usernames = $this->input('recipients');
            $unknown = array_diff($usernames, $this->recipients()->pluck('username')->all());

            if ($unknown !== []) {
                $validator->errors()->add('recipients', __('No member is called :username.', ['username' => reset($unknown)]));
            }
        }];
    }

    /**
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        /** @var list<string> $usernames */
        $usernames = $this->input('recipients', []);

        return $this->members ??= User::query()->whereIn('username', $usernames)->get();
    }
}
