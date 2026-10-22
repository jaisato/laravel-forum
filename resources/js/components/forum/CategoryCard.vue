<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import RelativeTime from '@/components/forum/RelativeTime.vue';
import UserAvatar from '@/components/forum/UserAvatar.vue';
import { formatCount } from '@/lib/format';
import { show as showCategory } from '@/routes/categories';
import { show as showThread } from '@/routes/threads';
import type { CategoryWithLatestThread } from '@/types/forum';

defineProps<{ category: CategoryWithLatestThread }>();

const page = usePage();

const count = (value: number) => formatCount(value, page.props.locale);
</script>

<template>
    <article
        class="flex flex-col gap-3 rounded-xl border border-l-4 bg-card p-4 sm:flex-row sm:items-center sm:gap-6"
        :style="{ borderLeftColor: category.color }"
    >
        <div class="min-w-0 flex-1">
            <h3 class="flex items-center gap-2 font-semibold">
                <Link
                    :href="showCategory.url(category.slug)"
                    data-test="category-link"
                    class="hover:underline"
                    >{{ category.name }}</Link
                >
                <span
                    v-if="category.is_locked"
                    data-test="category-locked"
                    role="img"
                    :aria-label="$t('Only staff can start threads here')"
                    :title="$t('Only staff can start threads here')"
                    class="text-muted-foreground"
                >
                    <Lock class="size-3.5" aria-hidden="true" />
                </span>
            </h3>
            <p
                v-if="category.description"
                class="mt-0.5 line-clamp-2 text-sm text-muted-foreground"
            >
                {{ category.description }}
            </p>
            <dl class="mt-2 flex gap-4 text-xs text-muted-foreground">
                <div class="flex gap-1" data-test="threads-count">
                    <dt>{{ $t('Threads') }}</dt>
                    <dd class="font-medium text-foreground">
                        {{ count(category.threads_count) }}
                    </dd>
                </div>
                <div class="flex gap-1" data-test="posts-count">
                    <dt>{{ $t('Messages') }}</dt>
                    <dd class="font-medium text-foreground">
                        {{ count(category.posts_count) }}
                    </dd>
                </div>
            </dl>
        </div>

        <div
            v-if="category.latest_thread"
            data-test="latest-thread"
            class="flex min-w-0 items-center gap-2 text-sm sm:w-72"
        >
            <UserAvatar :user="category.latest_thread.last_poster" size="sm" />
            <div class="min-w-0">
                <Link
                    :href="showThread.url(category.latest_thread.slug)"
                    class="block truncate font-medium hover:underline"
                    >{{ category.latest_thread.title }}</Link
                >
                <p class="truncate text-xs text-muted-foreground">
                    {{
                        category.latest_thread.last_poster?.name ??
                        $t('Deleted user')
                    }}
                    <template v-if="category.latest_thread.last_posted_at">
                        ·
                        <RelativeTime
                            :datetime="category.latest_thread.last_posted_at"
                        />
                    </template>
                </p>
            </div>
        </div>
        <p v-else class="text-sm text-muted-foreground sm:w-72">
            {{ $t('No threads yet') }}
        </p>
    </article>
</template>
