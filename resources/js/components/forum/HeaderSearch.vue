<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { ref } from 'vue';
import { Input } from '@/components/ui/input';
import { search } from '@/routes';

const words = ref('');

function submit(): void {
    const q = words.value.trim();

    router.get(search.url(q === '' ? undefined : { query: { q } }));
}
</script>

<template>
    <form
        role="search"
        class="relative hidden md:block"
        @submit.prevent="submit"
    >
        <Search
            class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
        />
        <Input
            v-model="words"
            type="search"
            name="q"
            :placeholder="$t('Search…')"
            :aria-label="$t('Search the forum')"
            class="h-9 w-48 pl-8 lg:w-64"
        />
    </form>
    <Link
        :href="search.url()"
        data-test="search-link"
        class="inline-flex size-9 items-center justify-center rounded-md hover:bg-accent md:hidden"
        :aria-label="$t('Search')"
    >
        <Search class="size-5" aria-hidden="true" />
    </Link>
</template>
