<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Hash, Lock, Plus } from '@lucide/vue';
import { computed } from 'vue';
import Pagination from '@/components/forum/Pagination.vue';
import ThreadList from '@/components/forum/ThreadList.vue';
import { Button } from '@/components/ui/button';
import { show as showCategory } from '@/routes/categories';
import { show as showTag } from '@/routes/tags';
import { create, index as threadsIndex } from '@/routes/threads';
import type {
    ListingAbilities,
    ListingContext,
    ListingFilters,
    Paginated,
    ThreadFilter,
    ThreadSort,
    ThreadSummary,
} from '@/types/forum';

const props = defineProps<{
    threads: Paginated<ThreadSummary>;
    filters: ListingFilters;
    context: ListingContext;
    can: ListingAbilities;
}>();

const page = usePage();

// Guests see it too: the form asks them to log in first.
const showNewThread = computed(
    () => page.props.auth.user === null || props.can.create_thread,
);

// From a category, the thread starts in it.
const newThreadUrl = computed(() =>
    create.url(
        props.context?.type === 'category'
            ? { query: { category: props.context.category.slug } }
            : undefined,
    ),
);

const title = computed(() => {
    switch (props.context?.type) {
        case 'category':
            return props.context.category.name;
        case 'tag':
            return `#${props.context.tag.name}`;
        default:
            return null;
    }
});

const filterOptions = computed(() => {
    const options: { value: ThreadFilter; label: string }[] = [
        { value: 'all', label: 'All' },
        { value: 'unanswered', label: 'Unanswered' },
        { value: 'solved', label: 'Solved' },
        { value: 'unsolved', label: 'Unsolved' },
    ];

    // Personal filters need somebody to be personal about.
    if (page.props.auth.user) {
        options.push(
            { value: 'following', label: 'Threads I follow' },
            { value: 'mine', label: 'My threads' },
        );
    }

    return options;
});

const sortOptions: { value: ThreadSort; label: string }[] = [
    { value: 'activity', label: 'Latest activity' },
    { value: 'newest', label: 'Newest' },
    { value: 'replies', label: 'Most replies' },
    { value: 'views', label: 'Most views' },
];

/**
 * The listing with another sort or filter, back on its first page. The
 * defaults stay out of the address.
 */
function listingUrl(changes: Partial<ListingFilters>): string {
    const next = { ...props.filters, ...changes };
    const options = {
        query: {
            sort: next.sort === 'activity' ? undefined : next.sort,
            filter: next.filter === 'all' ? undefined : next.filter,
        },
    };

    switch (props.context?.type) {
        case 'category':
            return showCategory.url(props.context.category.slug, options);
        case 'tag':
            return showTag.url(props.context.tag.slug, options);
        default:
            return threadsIndex.url(options);
    }
}

function changeSort(event: Event): void {
    router.visit(
        listingUrl({
            sort: (event.target as HTMLSelectElement).value as ThreadSort,
        }),
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="title ?? $t('All threads')" />

    <header class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex min-w-0 flex-col gap-1">
            <template v-if="context?.type === 'category'">
                <h1
                    class="flex items-center gap-2 text-2xl font-semibold tracking-tight"
                >
                    <span
                        class="size-3 shrink-0 rounded-full"
                        :style="{ backgroundColor: context.category.color }"
                        aria-hidden="true"
                    />
                    {{ context.category.name }}
                    <span
                        v-if="context.category.is_locked"
                        role="img"
                        :aria-label="$t('Only staff can start threads here')"
                        :title="$t('Only staff can start threads here')"
                        class="text-muted-foreground"
                    >
                        <Lock class="size-4" aria-hidden="true" />
                    </span>
                </h1>
                <p
                    v-if="context.category.description"
                    class="text-sm text-muted-foreground"
                >
                    {{ context.category.description }}
                </p>
            </template>
            <template v-else-if="context?.type === 'tag'">
                <h1
                    class="flex items-center gap-1 text-2xl font-semibold tracking-tight"
                >
                    <Hash
                        class="size-5 text-muted-foreground"
                        aria-hidden="true"
                    />
                    {{ context.tag.name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t('Threads tagged :tag', {
                            tag: context.tag.name,
                        })
                    }}
                </p>
            </template>
            <h1 v-else class="text-2xl font-semibold tracking-tight">
                {{ $t('All threads') }}
            </h1>
        </div>

        <Button v-if="showNewThread" size="sm" as-child>
            <Link :href="newThreadUrl" data-test="new-thread">
                <Plus aria-hidden="true" />
                {{ $t('New thread') }}
            </Link>
        </Button>
    </header>

    <div
        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <nav
            :aria-label="$t('Filter threads')"
            class="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1 sm:pb-0"
        >
            <Link
                v-for="option in filterOptions"
                :key="option.value"
                :href="listingUrl({ filter: option.value })"
                preserve-scroll
                data-test="listing-filter"
                class="shrink-0 rounded-full border px-3 py-1 text-sm font-medium whitespace-nowrap"
                :class="
                    filters.filter === option.value
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                "
                :aria-current="
                    filters.filter === option.value ? 'page' : undefined
                "
                >{{ $t(option.label) }}</Link
            >
        </nav>

        <label class="flex items-center gap-2 text-sm text-muted-foreground">
            {{ $t('Sort by') }}
            <select
                :value="filters.sort"
                data-test="listing-sort"
                class="h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                @change="changeSort"
            >
                <option
                    v-for="option in sortOptions"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ $t(option.label) }}
                </option>
            </select>
        </label>
    </div>

    <ThreadList
        :threads="threads.data"
        :hide-category="context?.type === 'category'"
        :empty-title="
            filters.filter === 'all'
                ? 'No threads yet'
                : 'No threads match this filter'
        "
    />

    <Pagination :links="threads.links" :meta="threads.meta" />
</template>
