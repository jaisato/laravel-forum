<script setup lang="ts">
import { router } from '@inertiajs/vue3';

defineProps<{
    /** HTML rendered and sanitised on the server (MarkdownRenderer). */
    html: string;
}>();

/**
 * Links to the forum itself open as Inertia visits instead of full page
 * loads; external links keep their target and rel attributes.
 */
function followInternalLink(event: MouseEvent): void {
    if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
    ) {
        return;
    }

    const link = (event.target as HTMLElement | null)?.closest('a');

    if (!link || link.target === '_blank' || link.hasAttribute('download')) {
        return;
    }

    const url = new URL(link.href, window.location.href);
    const samePage =
        url.pathname === window.location.pathname &&
        url.search === window.location.search;

    if (url.origin !== window.location.origin || samePage) {
        return;
    }

    event.preventDefault();
    router.visit(url.pathname + url.search + url.hash);
}
</script>

<template>
    <!-- eslint-disable vue/no-v-html -- server-sanitised HTML (App\Support\Markdown\MarkdownRenderer), never raw user input -->
    <div
        class="prose prose-sm max-w-none break-words prose-neutral sm:prose-base dark:prose-invert prose-a:text-primary prose-pre:bg-muted prose-pre:text-foreground"
        @click="followInternalLink"
        v-html="html"
    />
    <!-- eslint-enable vue/no-v-html -->
</template>
