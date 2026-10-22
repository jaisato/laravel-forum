<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, FolderOpen } from '@lucide/vue';
import CategoryCard from '@/components/forum/CategoryCard.vue';
import EmptyState from '@/components/forum/EmptyState.vue';
import ThreadList from '@/components/forum/ThreadList.vue';
import UserAvatar from '@/components/forum/UserAvatar.vue';
import { formatCount } from '@/lib/format';
import { index as threadsIndex } from '@/routes/threads';
import type {
    CategoryWithLatestThread,
    ForumStats,
    ThreadSummary,
} from '@/types/forum';

defineProps<{
    categories: CategoryWithLatestThread[];
    threads: ThreadSummary[];
    stats: ForumStats;
}>();

const page = usePage();

const count = (value: number) => formatCount(value, page.props.locale);
</script>

<template>
    <Head :title="$t('Home')" />

    <h1 class="sr-only">{{ page.props.name }}</h1>

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="flex min-w-0 flex-col gap-8">
            <section aria-labelledby="categories-heading">
                <h2
                    id="categories-heading"
                    class="mb-3 text-lg font-semibold tracking-tight"
                >
                    {{ $t('Categories') }}
                </h2>
                <div v-if="categories.length > 0" class="flex flex-col gap-3">
                    <CategoryCard
                        v-for="category in categories"
                        :key="category.id"
                        :category="category"
                    />
                </div>
                <EmptyState
                    v-else
                    :icon="FolderOpen"
                    :title="$t('No categories yet')"
                    :description="
                        $t('An administrator has to create the first one.')
                    "
                />
            </section>

            <section aria-labelledby="latest-heading">
                <div class="mb-3 flex items-center justify-between gap-4">
                    <h2
                        id="latest-heading"
                        class="text-lg font-semibold tracking-tight"
                    >
                        {{ $t('Latest threads') }}
                    </h2>
                    <Link
                        :href="threadsIndex.url()"
                        class="inline-flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground"
                    >
                        {{ $t('View all') }}
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </div>
                <ThreadList :threads="threads" />
            </section>
        </div>

        <aside class="flex flex-col gap-6" :aria-label="$t('Community')">
            <section class="rounded-xl border bg-card p-4">
                <h2 class="mb-3 text-sm font-semibold">
                    {{ $t('Statistics') }}
                </h2>
                <dl class="grid grid-cols-3 gap-2 text-center">
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            {{ $t('Members') }}
                        </dt>
                        <dd class="text-lg font-semibold">
                            {{ count(stats.members) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            {{ $t('Threads') }}
                        </dt>
                        <dd class="text-lg font-semibold">
                            {{ count(stats.threads) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            {{ $t('Messages') }}
                        </dt>
                        <dd class="text-lg font-semibold">
                            {{ count(stats.posts) }}
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="stats.newest_member"
                    class="mt-4 flex items-center gap-2 border-t pt-3 text-sm"
                >
                    <UserAvatar :user="stats.newest_member" size="sm" />
                    <span class="min-w-0 truncate">
                        <span class="text-muted-foreground"
                            >{{ $t('Newest member') }}:</span
                        >
                        {{ stats.newest_member.name }}
                    </span>
                </p>
            </section>

            <section class="rounded-xl border bg-card p-4">
                <h2 class="mb-3 text-sm font-semibold">
                    {{
                        $t('Online now (:count)', {
                            count: String(stats.online_count),
                        })
                    }}
                </h2>
                <ul
                    v-if="stats.online.length > 0"
                    class="flex flex-wrap gap-1.5"
                >
                    <li
                        v-for="member in stats.online"
                        :key="member.id"
                        :title="member.name"
                    >
                        <UserAvatar :user="member" size="sm" />
                        <span class="sr-only">{{ member.name }}</span>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">
                    {{ $t('Nobody in the last 15 minutes.') }}
                </p>
                <p
                    v-if="stats.online_count > stats.online.length"
                    class="mt-2 text-xs text-muted-foreground"
                >
                    {{
                        $t('And :count more', {
                            count: String(
                                stats.online_count - stats.online.length,
                            ),
                        })
                    }}
                </p>
            </section>
        </aside>
    </div>
</template>
