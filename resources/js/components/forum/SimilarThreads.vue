<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
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
        data-test="related-threads"
        aria-labelledby="related-heading"
        class="rounded-xl border bg-card p-4"
    >
        <h2 id="related-heading" class="text-base font-semibold">
            {{ $t('Related threads') }}
        </h2>
        <ul class="mt-2 flex flex-col gap-2">
            <li
                v-for="thread in threads"
                :key="thread.id"
                class="flex items-center gap-2 text-sm"
            >
                <Link
                    :href="show.url(thread.slug)"
                    data-test="related-thread"
                    class="min-w-0 truncate underline-offset-4 hover:underline"
                >
                    {{ thread.title }}
                </Link>
                <span
                    v-if="thread.is_solved"
                    data-test="related-solved"
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
