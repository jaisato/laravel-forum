<script setup lang="ts">
import { CircleCheck, MessageSquare } from '@lucide/vue';
import { show } from '@/routes/threads';
import type { SimilarThread } from '@/types/forum';

defineProps<{
    threads: SimilarThread[];
}>();
</script>

<template>
    <section
        v-if="threads.length > 0"
        data-test="similar-threads"
        aria-labelledby="similar-heading"
        aria-live="polite"
        class="rounded-lg border bg-muted/40 px-3 py-2"
    >
        <h2 id="similar-heading" class="text-sm font-medium">
            {{ $t('Maybe it has already been asked') }}
        </h2>
        <ul class="mt-1 flex flex-col gap-1">
            <li
                v-for="thread in threads"
                :key="thread.id"
                class="flex items-center gap-2 text-sm"
            >
                <!-- A new tab keeps the thread being written. -->
                <a
                    :href="show.url(thread.slug)"
                    target="_blank"
                    rel="noopener"
                    class="min-w-0 truncate underline-offset-4 hover:underline"
                >
                    {{ thread.title }}
                </a>
                <span
                    v-if="thread.is_solved"
                    data-test="similar-solved"
                    class="inline-flex shrink-0 items-center gap-0.5 text-xs text-emerald-600 dark:text-emerald-400"
                >
                    <CircleCheck class="size-3" aria-hidden="true" />
                    {{ $t('Solved') }}
                </span>
                <span
                    class="inline-flex shrink-0 items-center gap-0.5 text-xs text-muted-foreground"
                    :title="$t('Replies')"
                >
                    <MessageSquare class="size-3" aria-hidden="true" />
                    {{ thread.replies_count }}
                    <span class="sr-only">{{ $t('Replies') }}</span>
                </span>
            </li>
        </ul>
    </section>
</template>
