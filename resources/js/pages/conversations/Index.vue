<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Mail, SquarePen } from '@lucide/vue';
import EmptyState from '@/components/forum/EmptyState.vue';
import Pagination from '@/components/forum/Pagination.vue';
import RelativeTime from '@/components/forum/RelativeTime.vue';
import UserAvatar from '@/components/forum/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { create, show } from '@/routes/conversations';
import type { Conversation, Paginated } from '@/types/forum';

defineProps<{
    conversations: Paginated<Conversation>;
}>();
</script>

<template>
    <Head :title="$t('Messages')" />

    <header class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold tracking-tight">
            {{ $t('Messages') }}
        </h1>

        <Button size="sm" as-child>
            <Link :href="create.url()" data-test="new-conversation">
                <SquarePen aria-hidden="true" />
                {{ $t('New conversation') }}
            </Link>
        </Button>
    </header>

    <ul
        v-if="conversations.data.length > 0"
        class="divide-y overflow-hidden rounded-xl border bg-card"
    >
        <li v-for="conversation in conversations.data" :key="conversation.id">
            <article
                data-test="conversation"
                :data-unread="conversation.is_unread ? 'true' : undefined"
                class="flex gap-3 px-4 py-3"
                :class="{ 'bg-primary/5': conversation.is_unread }"
            >
                <UserAvatar
                    :user="conversation.participants[0] ?? null"
                    size="md"
                    class="mt-0.5"
                />

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="show.url(conversation.id)"
                            data-test="conversation-link"
                            class="min-w-0 break-words hover:underline"
                            :class="
                                conversation.is_unread
                                    ? 'font-semibold'
                                    : 'font-medium'
                            "
                            >{{ conversation.subject }}</Link
                        >
                        <span
                            v-if="conversation.is_unread"
                            class="rounded-full bg-primary px-1.5 py-px text-[10px] font-semibold tracking-wide text-primary-foreground uppercase"
                            >{{ $t('New') }}</span
                        >
                    </div>
                    <p
                        data-test="conversation-participants"
                        class="text-xs text-muted-foreground"
                    >
                        {{
                            conversation.participants.length > 0
                                ? conversation.participants
                                      .map((participant) => participant.name)
                                      .join(', ')
                                : $t('Nobody else')
                        }}
                    </p>
                    <p
                        v-if="conversation.last_message"
                        data-test="conversation-last-message"
                        class="mt-0.5 line-clamp-1 text-sm text-muted-foreground"
                    >
                        {{
                            `${conversation.last_message.author?.name ?? $t('Deleted user')}: ${conversation.last_message.excerpt}`
                        }}
                    </p>
                </div>

                <span
                    v-if="conversation.last_message_at"
                    class="shrink-0 text-xs text-muted-foreground"
                >
                    <RelativeTime :datetime="conversation.last_message_at" />
                </span>
            </article>
        </li>
    </ul>
    <EmptyState
        v-else
        data-test="empty-conversations"
        :icon="Mail"
        :title="$t('No conversations yet')"
        :description="
            $t('Your private conversations with other members show up here.')
        "
    />

    <Pagination :links="conversations.links" :meta="conversations.meta" />
</template>
