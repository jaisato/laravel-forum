<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, reactive } from 'vue';
import EmptyState from '@/components/forum/EmptyState.vue';
import Pagination from '@/components/forum/Pagination.vue';
import PostSummaryList from '@/components/forum/PostSummaryList.vue';
import ThreadList from '@/components/forum/ThreadList.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { search } from '@/routes';
import type {
    Paginated,
    PostSummary,
    SearchFilters,
    SearchScopeOption,
    SearchType,
    ThreadSummary,
} from '@/types/forum';

const props = defineProps<{
    filters: SearchFilters;
    /** Null until there is something to look for, or when posts were searched. */
    threads: Paginated<ThreadSummary> | null;
    /** Null until there is something to look for, or when threads were searched. */
    posts: Paginated<PostSummary> | null;
    categories: SearchScopeOption[];
    tags: SearchScopeOption[];
}>();

// The form starts from the search on screen; "" means any category or tag.
const form = reactive({
    q: props.filters.q,
    category: props.filters.category ?? '',
    tag: props.filters.tag ?? '',
});

const types: { value: SearchType; label: string }[] = [
    { value: 'threads', label: 'Threads' },
    { value: 'posts', label: 'Posts' },
];

/**
 * The search page for the given search. The defaults (no words, threads,
 * any category or tag) stay out of the address.
 */
function searchUrl(next: SearchFilters): string {
    return search.url({
        query: {
            q: next.q === '' ? undefined : next.q,
            type: next.type === 'threads' ? undefined : next.type,
            category: next.category ?? undefined,
            tag: next.tag ?? undefined,
        },
    });
}

function submit(): void {
    router.get(
        searchUrl({
            q: form.q.trim(),
            type: props.filters.type,
            category: form.category === '' ? null : form.category,
            tag: form.tag === '' ? null : form.tag,
        }),
    );
}

const results = computed(() =>
    props.filters.type === 'threads' ? props.threads : props.posts,
);
</script>

<template>
    <Head
        :title="
            filters.q === ''
                ? $t('Search')
                : $t('Search: :words', { words: filters.q })
        "
    />

    <h1 class="text-2xl font-semibold tracking-tight">{{ $t('Search') }}</h1>

    <form
        role="search"
        class="flex flex-col gap-3 sm:flex-row sm:items-center"
        @submit.prevent="submit"
    >
        <div class="relative flex-1">
            <Search
                class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                v-model="form.q"
                type="search"
                name="q"
                :placeholder="$t('Words to look for')"
                :aria-label="$t('Search the forum')"
                maxlength="200"
                class="pl-8"
            />
        </div>

        <select
            v-model="form.category"
            data-test="search-category"
            :aria-label="$t('Category')"
            class="h-9 rounded-md border border-input bg-background px-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            @change="submit"
        >
            <option value="">{{ $t('All categories') }}</option>
            <option
                v-for="category in categories"
                :key="category.slug"
                :value="category.slug"
            >
                {{ category.name }}
            </option>
        </select>

        <select
            v-model="form.tag"
            data-test="search-tag"
            :aria-label="$t('Tag')"
            class="h-9 rounded-md border border-input bg-background px-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            @change="submit"
        >
            <option value="">{{ $t('All tags') }}</option>
            <option v-for="tag in tags" :key="tag.slug" :value="tag.slug">
                #{{ tag.name }}
            </option>
        </select>

        <Button type="submit">{{ $t('Search') }}</Button>
    </form>

    <nav :aria-label="$t('What to search')" class="flex gap-1 border-b">
        <Link
            v-for="option in types"
            :key="option.value"
            :href="searchUrl({ ...filters, type: option.value })"
            data-test="search-type"
            class="-mb-px border-b-2 px-3 py-2 text-sm font-medium"
            :class="
                filters.type === option.value
                    ? 'border-primary text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
            :aria-current="filters.type === option.value ? 'page' : undefined"
            >{{ $t(option.label) }}</Link
        >
    </nav>

    <EmptyState
        v-if="results === null"
        data-test="search-prompt"
        :icon="Search"
        :title="$t('Search the forum')"
        :description="
            $t(
                'Type a few words to find threads and posts. Only what everybody can read shows up.',
            )
        "
    />

    <template v-else>
        <p class="text-sm text-muted-foreground" data-test="search-total">
            {{
                $t('Results for “:words”: :count', {
                    count: String(results.meta.total),
                    words: filters.q,
                })
            }}
        </p>

        <ThreadList
            v-if="threads"
            :threads="threads.data"
            empty-title="Nothing found"
            empty-description="Try other words, or search every category and tag."
        />
        <PostSummaryList
            v-else-if="posts"
            :posts="posts.data"
            empty-title="Nothing found"
            empty-description="Try other words, or search every category and tag."
        />

        <Pagination :links="results.links" :meta="results.meta" />
    </template>
</template>
