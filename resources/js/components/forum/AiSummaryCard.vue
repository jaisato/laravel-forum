<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { Page } from '@inertiajs/core';
import { Sparkles } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import MarkdownContent from '@/components/forum/MarkdownContent.vue';
import { Button } from '@/components/ui/button';
import { store } from '@/routes/threads/summary';
import type { AiSummary } from '@/types/forum';

const props = defineProps<{
    threadSlug: string;
    /** Null while none was written (or AI summaries are off). */
    summary: AiSummary | null;
    /** Whether the viewer may ask for one (ThreadPolicy::summarize). */
    canSummarize: boolean;
}>();

const asking = ref(false);

/** How often, and how many times, the card looks for a summary being written. */
const LOOK_EVERY_MS = 5000;
const LOOK_TIMES = 12;

let looking: ReturnType<typeof setTimeout> | undefined;

function stopLooking(): void {
    clearTimeout(looking);
    looking = undefined;
}

/**
 * Reloads the summary alone every few seconds, for a minute at most, until
 * the one asked for arrives (its date changes) or the page is left.
 */
function lookForSummary(remaining = LOOK_TIMES): void {
    stopLooking();

    if (remaining === 0) {
        return;
    }

    looking = setTimeout(() => {
        router.reload({ only: ['summary'] });
        lookForSummary(remaining - 1);
    }, LOOK_EVERY_MS);
}

watch(() => props.summary?.generated_at, stopLooking);
onBeforeUnmount(stopLooking);

/**
 * The summary is written in the queue; the toast says it is on its way and
 * the card looks for it. A request turned down (too many) comes back with
 * an error toast instead.
 */
function ask(): void {
    router.post(
        store.url(props.threadSlug),
        {},
        {
            preserveScroll: true,
            onStart: () => (asking.value = true),
            onFinish: () => (asking.value = false),
            onSuccess: (page: Page) => {
                const toast = page.flash?.toast as
                    { type?: string } | undefined;

                if (toast?.type !== 'error') {
                    lookForSummary();
                }
            },
        },
    );
}
</script>

<template>
    <section
        v-if="summary || canSummarize"
        data-test="ai-summary"
        aria-labelledby="summary-heading"
        class="rounded-xl border bg-muted/40 px-4 py-3"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2
                id="summary-heading"
                class="flex items-center gap-2 text-sm font-semibold"
            >
                <Sparkles class="size-4" aria-hidden="true" />
                {{ $t('Summary of the thread') }}
            </h2>
            <Button
                v-if="canSummarize && (!summary || summary.stale)"
                data-test="ai-summary-ask"
                variant="outline"
                size="sm"
                :disabled="asking"
                @click="ask"
            >
                {{
                    summary
                        ? $t('Update the summary')
                        : $t('Summarize the thread')
                }}
            </Button>
        </div>

        <template v-if="summary">
            <MarkdownContent :html="summary.html" class="mt-2 text-sm" />
            <p class="mt-2 text-xs text-muted-foreground">
                {{ $t('Written by an AI: it can make mistakes.') }}
            </p>
        </template>
        <p v-else class="mt-2 text-sm text-muted-foreground">
            {{
                $t(
                    'An AI can sum up the question, the main answers and the solution for you.',
                )
            }}
        </p>
    </section>
</template>
