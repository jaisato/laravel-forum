<template>
    <div class="flex items-center justify-between border-t border-gray-200 px-4 py-3 sm:px-6">
        <div class="flex flex-1 justify-between sm:hidden">
            <!--
                Laravel sends url: null for the link that leads nowhere: "Previous"
                on the first page, "Next" on the last, and every "..." gap. An
                Inertia <Link> cannot take a null href - it calls href.toString()
                while rendering, so each of those links threw a TypeError and was
                dropped from the page. Links without a URL render as inert spans.
            -->
            <component :is="previousUrl ? Link : 'span'"
                       :href="previousUrl ?? undefined"
                       :aria-disabled="previousUrl ? undefined : 'true'"
                       class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium"
                       :class="previousUrl ? 'text-gray-700 hover:bg-gray-50' : 'text-gray-400 cursor-default'">Previous</component>
            <component :is="nextUrl ? Link : 'span'"
                       :href="nextUrl ?? undefined"
                       :aria-disabled="nextUrl ? undefined : 'true'"
                       class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium"
                       :class="nextUrl ? 'text-gray-700 hover:bg-gray-50' : 'text-gray-400 cursor-default'">Next</component>
        </div>
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700">
                    Showing
                    {{ ' ' }}
                    <span class="font-medium">{{ meta.from }}</span>
                    {{ ' ' }}
                    to
                    {{ ' ' }}
                    <span class="font-medium">{{ meta.to }}</span>
                    {{ ' ' }}
                    of
                    {{ ' ' }}
                    <span class="font-medium">{{ meta.total }}</span>
                    {{ ' ' }}
                    results
                </p>
            </div>
            <div>
                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm bg-white" aria-label="Pagination">
                    <component v-for="(link, index) in meta.links"
                          :key="index"
                          :is="link.url ? Link : 'span'"
                          :href="link.url ?? undefined"
                          :aria-current="link.active ? 'page' : undefined"
                          :aria-disabled="link.url ? undefined : 'true'"
                          class="relative inline-flex items-center first-of-type:rounded-l-md last-of-type:rounded-r-md px-3 py-2"
                          :class="{
                           'z-10 bg-indigo-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600': link.active,
                           'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:outline-offset-0': !link.active && link.url,
                           'text-gray-400 ring-1 ring-inset ring-gray-300 cursor-default': !link.url
                       }"
                          v-html="link.label"
                    >
                    </component>
                </nav>
            </div>
        </div>
    </div>
</template>

<script setup>
import {Link} from "@inertiajs/vue3";
import {computed} from "vue";

const props = defineProps(['meta']);

const previousUrl = computed(() => props.meta.links[0].url);
const nextUrl = computed(() => [...props.meta.links].reverse()[0].url);
</script>
