<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import type { PaginationLinks, PaginationMeta } from '@/types/forum';

const props = defineProps<{
    links: PaginationLinks;
    meta: PaginationMeta;
}>();

// Laravel wraps the page numbers in "previous" and "next" entries.
const pages = computed(() => props.meta.links.slice(1, -1));

const edge =
    'inline-flex h-9 items-center gap-1 rounded-md border px-3 text-sm font-medium';
</script>

<template>
    <nav
        v-if="meta.last_page > 1"
        :aria-label="$t('Pagination')"
        class="flex items-center justify-between gap-2"
    >
        <Link
            v-if="links.prev"
            :href="links.prev"
            :class="[edge, 'hover:bg-accent']"
            data-test="previous-page"
            rel="prev"
        >
            <ChevronLeft class="size-4" aria-hidden="true" />
            {{ $t('Previous') }}
        </Link>
        <span
            v-else
            :class="[edge, 'pointer-events-none opacity-50']"
            aria-disabled="true"
            data-test="previous-page"
        >
            <ChevronLeft class="size-4" aria-hidden="true" />
            {{ $t('Previous') }}
        </span>

        <ul class="hidden items-center gap-1 sm:flex">
            <li v-for="(page, index) in pages" :key="`${index}-${page.label}`">
                <span
                    v-if="page.url === null"
                    class="px-2 text-sm text-muted-foreground"
                    >{{ page.label }}</span
                >
                <Link
                    v-else
                    :href="page.url"
                    data-test="page"
                    :aria-current="page.active ? 'page' : undefined"
                    class="inline-flex size-9 items-center justify-center rounded-md text-sm"
                    :class="
                        page.active
                            ? 'bg-primary font-semibold text-primary-foreground'
                            : 'hover:bg-accent'
                    "
                    >{{ page.label }}</Link
                >
            </li>
        </ul>

        <span class="text-sm text-muted-foreground sm:hidden">
            {{
                $t(':current of :last', {
                    current: String(meta.current_page),
                    last: String(meta.last_page),
                })
            }}
        </span>

        <Link
            v-if="links.next"
            :href="links.next"
            :class="[edge, 'hover:bg-accent']"
            data-test="next-page"
            rel="next"
        >
            {{ $t('Next') }}
            <ChevronRight class="size-4" aria-hidden="true" />
        </Link>
        <span
            v-else
            :class="[edge, 'pointer-events-none opacity-50']"
            aria-disabled="true"
            data-test="next-page"
        >
            {{ $t('Next') }}
            <ChevronRight class="size-4" aria-hidden="true" />
        </span>
    </nav>
</template>
