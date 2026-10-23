<script setup lang="ts">
import { MessageSquare } from '@lucide/vue';
import EmptyState from '@/components/forum/EmptyState.vue';
import PostSummaryItem from '@/components/forum/PostSummaryItem.vue';
import type { PostSummary } from '@/types/forum';

withDefaults(
    defineProps<{
        posts: PostSummary[];
        hideAuthor?: boolean;
        emptyTitle?: string;
        emptyDescription?: string;
    }>(),
    { emptyTitle: 'No posts yet' },
);
</script>

<template>
    <ul
        v-if="posts.length > 0"
        class="divide-y overflow-hidden rounded-xl border bg-card"
    >
        <li v-for="post in posts" :key="post.id">
            <PostSummaryItem :post="post" :hide-author="hideAuthor" />
        </li>
    </ul>
    <EmptyState
        v-else
        data-test="empty-posts"
        :icon="MessageSquare"
        :title="$t(emptyTitle)"
        :description="emptyDescription ? $t(emptyDescription) : undefined"
    />
</template>
