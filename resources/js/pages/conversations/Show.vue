<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { LogOut } from '@lucide/vue';
import MarkdownContent from '@/components/forum/MarkdownContent.vue';
import MarkdownEditor from '@/components/forum/MarkdownEditor.vue';
import Pagination from '@/components/forum/Pagination.vue';
import RelativeTime from '@/components/forum/RelativeTime.vue';
import UserAvatar from '@/components/forum/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { leave } from '@/routes/conversations';
import { store } from '@/routes/conversations/messages';
import { show as showUser } from '@/routes/users';
import type {
    ConversationAbilities,
    ConversationDetail,
    Message,
    Paginated,
} from '@/types/forum';

const props = defineProps<{
    conversation: ConversationDetail;
    messages: Paginated<Message>;
    can: ConversationAbilities;
}>();

// Private messages are not kept as drafts in the browser: a shared computer
// would keep them after signing out.
const form = useForm({ body: '' });

function submit(): void {
    if (form.body.trim() === '' || form.processing) {
        return;
    }

    form.post(store.url(props.conversation.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function leaveConversation(question: string): void {
    if (!window.confirm(question)) {
        return;
    }

    router.post(leave.url(props.conversation.id));
}
</script>

<template>
    <Head :title="conversation.subject" />

    <header class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex min-w-0 flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight break-words">
                {{ conversation.subject }}
            </h1>
            <p
                data-test="conversation-participants"
                class="text-sm text-muted-foreground"
            >
                {{ $t('With') }}
                {{
                    conversation.participants
                        .map((participant) => participant.name)
                        .join(', ')
                }}
            </p>
        </div>

        <Button
            v-if="can.leave"
            variant="outline"
            size="sm"
            data-test="leave-conversation"
            @click="
                leaveConversation(
                    $t(
                        'Leave this conversation? You will no longer be able to read it.',
                    ),
                )
            "
        >
            <LogOut aria-hidden="true" />
            {{ $t('Leave') }}
        </Button>
    </header>

    <Pagination :links="messages.links" :meta="messages.meta" />

    <ol class="flex flex-col gap-3">
        <li v-for="message in messages.data" :key="message.id">
            <article
                :id="`message-${message.id}`"
                data-test="message"
                :data-own="message.is_own ? 'true' : undefined"
                class="scroll-mt-24 rounded-xl border bg-card"
                :class="{ 'border-primary/30 bg-primary/5': message.is_own }"
            >
                <header
                    class="flex flex-wrap items-center gap-x-2 gap-y-1 border-b px-4 py-2 text-sm"
                >
                    <UserAvatar :user="message.author" size="sm" />
                    <Link
                        v-if="message.author"
                        :href="showUser.url(message.author.username)"
                        class="font-medium hover:underline"
                        >{{ message.author.name }}</Link
                    >
                    <span v-else class="font-medium">{{
                        $t('Deleted user')
                    }}</span>
                    <span class="text-muted-foreground" aria-hidden="true"
                        >·</span
                    >
                    <a
                        :href="`#message-${message.id}`"
                        class="text-muted-foreground hover:underline"
                    >
                        <RelativeTime :datetime="message.created_at" />
                    </a>
                </header>
                <div class="px-4 py-3">
                    <MarkdownContent :html="message.body_html" />
                </div>
            </article>
        </li>
    </ol>

    <Pagination :links="messages.links" :meta="messages.meta" />

    <form
        v-if="can.reply"
        data-test="reply-form"
        class="flex flex-col gap-3"
        @submit.prevent="submit"
    >
        <label for="message-body" class="sr-only">{{
            $t('Your message')
        }}</label>
        <MarkdownEditor
            id="message-body"
            v-model="form.body"
            :max-length="10000"
            :placeholder="$t('Write your message…')"
            :error="form.errors.body"
            @submit="submit"
        />
        <div class="flex items-center justify-end gap-3">
            <span class="hidden text-xs text-muted-foreground sm:inline">
                {{ $t('Ctrl + Enter to send') }}
            </span>
            <Button
                type="submit"
                :disabled="form.body.trim() === '' || form.processing"
            >
                {{ $t('Send') }}
            </Button>
        </div>
    </form>
    <p
        v-else
        data-test="reply-closed"
        class="rounded-xl border border-dashed px-4 py-3 text-sm text-muted-foreground"
    >
        {{ $t('You cannot write in this conversation right now.') }}
    </p>
</template>
