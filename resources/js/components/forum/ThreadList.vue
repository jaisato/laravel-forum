<script setup lang="ts">
import { MessagesSquare } from '@lucide/vue';
import EmptyState from '@/components/forum/EmptyState.vue';
import ThreadListItem from '@/components/forum/ThreadListItem.vue';
import type { ThreadSummary } from '@/types/forum';

withDefaults(
    defineProps<{
        threads: ThreadSummary[];
        hideCategory?: boolean;
        emptyTitle?: string;
        emptyDescription?: string;
    }>(),
    { emptyTitle: 'No threads yet' },
);
</script>

<template>
    <ul
        v-if="threads.length > 0"
        class="divide-y overflow-hidden rounded-xl border bg-card"
    >
        <li v-for="thread in threads" :key="thread.id">
            <ThreadListItem :thread="thread" :hide-category="hideCategory" />
        </li>
    </ul>
    <EmptyState
        v-else
        data-test="empty-threads"
        :icon="MessagesSquare"
        :title="$t(emptyTitle)"
        :description="emptyDescription ? $t(emptyDescription) : undefined"
    >
        <slot name="empty" />
    </EmptyState>
</template>
