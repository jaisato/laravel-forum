<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { CircleCheck, Eye, Lock, MessageSquare, Pin } from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import CategoryBadge from '@/components/forum/CategoryBadge.vue';
import RelativeTime from '@/components/forum/RelativeTime.vue';
import TagBadge from '@/components/forum/TagBadge.vue';
import UserAvatar from '@/components/forum/UserAvatar.vue';
import { formatCount } from '@/lib/format';
import { show as showCategory } from '@/routes/categories';
import { show as showTag } from '@/routes/tags';
import { show as showThread } from '@/routes/threads';
import type { ThreadSummary } from '@/types/forum';

const props = defineProps<{
    thread: ThreadSummary;
    /** Inside a category page its badge would repeat on every thread. */
    hideCategory?: boolean;
}>();

const page = usePage();

// Members land on the first post they have not read yet.
const href = computed(() =>
    showThread.url(
        props.thread.slug,
        props.thread.is_unread ? { query: { unread: 1 } } : undefined,
    ),
);

type State = { label: string; icon: Component; class: string };

const states = computed(() => {
    const list: State[] = [];

    if (props.thread.is_pinned) {
        list.push({ label: 'Pinned', icon: Pin, class: 'text-sky-600' });
    }

    if (props.thread.is_locked) {
        list.push({ label: 'Locked', icon: Lock, class: 'text-amber-600' });
    }

    if (props.thread.is_solved) {
        list.push({
            label: 'Solved',
            icon: CircleCheck,
            class: 'text-emerald-600',
        });
    }

    return list;
});

// Only staff and the author ever receive threads the public cannot see.
const statusLabel = computed(() => {
    switch (props.thread.status) {
        case 'hidden':
            return 'Hidden';
        case 'pending_review':
            return 'Pending review';
        default:
            return null;
    }
});

const answered = computed(() => props.thread.replies_count > 0);

const count = (value: number) => formatCount(value, page.props.locale);
</script>

<template>
    <article class="flex gap-3 px-4 py-3 sm:gap-4" data-test="thread-item">
        <UserAvatar
            :user="thread.author"
            size="md"
            class="mt-0.5 hidden sm:flex"
        />

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span
                    v-for="state in states"
                    :key="state.label"
                    data-test="thread-state"
                    role="img"
                    :aria-label="$t(state.label)"
                    :title="$t(state.label)"
                    :class="state.class"
                >
                    <component
                        :is="state.icon"
                        class="size-4"
                        aria-hidden="true"
                    />
                </span>
                <Link
                    :href="href"
                    data-test="thread-link"
                    class="min-w-0 break-words text-foreground hover:underline"
                    :class="thread.is_unread ? 'font-semibold' : 'font-medium'"
                    >{{ thread.title }}</Link
                >
                <span
                    v-if="thread.is_unread"
                    data-test="unread-badge"
                    class="rounded-full bg-primary px-1.5 py-px text-[10px] font-semibold tracking-wide text-primary-foreground uppercase"
                    >{{ $t('New') }}</span
                >
                <span
                    v-if="statusLabel"
                    data-test="thread-status"
                    class="rounded-md bg-amber-100 px-1.5 py-px text-[11px] font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-200"
                    >{{ $t(statusLabel) }}</span
                >
            </div>

            <p
                v-if="thread.excerpt"
                data-test="thread-excerpt"
                class="mt-0.5 line-clamp-1 text-sm text-muted-foreground"
            >
                {{ thread.excerpt }}
            </p>

            <div
                class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
            >
                <CategoryBadge
                    v-if="!hideCategory"
                    :category="thread.category"
                    :href="showCategory.url(thread.category.slug)"
                />
                <TagBadge
                    v-for="tag in thread.tags"
                    :key="tag.id"
                    :tag="tag"
                    :href="showTag.url(tag.slug)"
                />
                <span data-test="thread-activity">
                    {{ thread.author?.name ?? $t('Deleted user') }}
                    <template v-if="answered">
                        ·
                        {{
                            $t('Last reply by :name', {
                                name:
                                    thread.last_poster?.name ??
                                    $t('Deleted user'),
                            })
                        }}
                        <RelativeTime
                            :datetime="
                                thread.last_posted_at ?? thread.created_at
                            "
                        />
                    </template>
                    <template v-else>
                        · <RelativeTime :datetime="thread.created_at" />
                    </template>
                </span>
            </div>
        </div>

        <div
            class="flex shrink-0 flex-col items-end gap-1 text-xs text-muted-foreground sm:flex-row sm:items-start sm:gap-4"
        >
            <span
                class="inline-flex items-center gap-1"
                data-test="replies-count"
                :title="$t('Replies')"
            >
                <MessageSquare class="size-3.5" aria-hidden="true" />
                {{ count(thread.replies_count) }}
                <span class="sr-only">{{ $t('Replies') }}</span>
            </span>
            <span
                class="inline-flex items-center gap-1"
                data-test="views-count"
                :title="$t('Views')"
            >
                <Eye class="size-3.5" aria-hidden="true" />
                {{ count(thread.views_count) }}
                <span class="sr-only">{{ $t('Views') }}</span>
            </span>
        </div>
    </article>
</template>
