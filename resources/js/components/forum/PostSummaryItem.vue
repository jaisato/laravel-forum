<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import RelativeTime from '@/components/forum/RelativeTime.vue';
import UserAvatar from '@/components/forum/UserAvatar.vue';
import { show as showThread } from '@/routes/threads';
import { show as showUser } from '@/routes/users';
import type { PostSummary } from '@/types/forum';

const props = defineProps<{
    post: PostSummary;
    /** In a list of one member's posts their name would repeat on each. */
    hideAuthor?: boolean;
}>();

// The thread page opens the page that holds the post (?post=) and the
// anchor brings it into view.
const href = computed(
    () =>
        `${showThread.url(props.post.thread.slug, { query: { post: props.post.id } })}#post-${props.post.id}`,
);

// Only staff and the author ever receive posts the public cannot see.
const statusLabel = computed(() => {
    switch (props.post.status) {
        case 'hidden':
            return 'Hidden';
        case 'pending_review':
            return 'Pending review';
        default:
            return null;
    }
});
</script>

<template>
    <article class="flex gap-3 px-4 py-3" data-test="post-summary">
        <UserAvatar
            v-if="!hideAuthor"
            :user="post.author"
            size="md"
            class="mt-0.5 hidden sm:flex"
        />

        <div class="min-w-0 flex-1">
            <p
                class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs text-muted-foreground"
            >
                <template v-if="!hideAuthor">
                    <Link
                        v-if="post.author"
                        :href="showUser.url(post.author.username)"
                        data-test="post-author"
                        class="font-medium text-foreground hover:underline"
                        >{{ post.author.name }}</Link
                    >
                    <span v-else data-test="post-author">{{
                        $t('Deleted user')
                    }}</span>
                    <span aria-hidden="true">·</span>
                </template>
                <span>{{ $t('in') }}</span>
                <Link
                    :href="showThread.url(post.thread.slug)"
                    data-test="post-thread"
                    class="min-w-0 font-medium break-words text-foreground hover:underline"
                    >{{ post.thread.title }}</Link
                >
                <span aria-hidden="true">·</span>
                <RelativeTime :datetime="post.created_at" />
                <span
                    v-if="statusLabel"
                    data-test="post-status"
                    class="rounded-md bg-amber-100 px-1.5 py-px text-[11px] font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-200"
                    >{{ $t(statusLabel) }}</span
                >
            </p>

            <Link
                :href="href"
                data-test="post-link"
                class="mt-1 line-clamp-3 text-sm break-words text-foreground hover:underline"
                >{{ post.excerpt }}</Link
            >
        </div>
    </article>
</template>
