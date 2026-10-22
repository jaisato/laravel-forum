<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CategoryBadge from '@/components/forum/CategoryBadge.vue';
import RelativeTime from '@/components/forum/RelativeTime.vue';
import TagBadge from '@/components/forum/TagBadge.vue';
import UserAvatar from '@/components/forum/UserAvatar.vue';
import { show as showCategory } from '@/routes/categories';
import { show as showTag } from '@/routes/tags';
import type { ThreadSummary } from '@/types/forum';

defineProps<{ thread: ThreadSummary }>();
</script>

<template>
    <Head :title="thread.title" />

    <header class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <CategoryBadge
                :category="thread.category"
                :href="showCategory.url(thread.category.slug)"
            />
            <TagBadge
                v-for="tag in thread.tags"
                :key="tag.id"
                :tag="tag"
                :href="showTag.url(tag.slug)"
            />
        </div>
        <h1 class="text-2xl font-semibold tracking-tight break-words">
            {{ thread.title }}
        </h1>
        <p class="flex items-center gap-2 text-sm text-muted-foreground">
            <UserAvatar :user="thread.author" size="sm" />
            {{ thread.author?.name ?? $t('Deleted user') }}
            ·
            <RelativeTime :datetime="thread.created_at" />
        </p>
    </header>
</template>
