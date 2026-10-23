<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import { ref } from 'vue';
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

/**
 * The summary is written in the queue; the toast says it is on its way.
 */
function ask(): void {
    router.post(
        store.url(props.threadSlug),
        {},
        {
            preserveScroll: true,
            onStart: () => (asking.value = true),
            onFinish: () => (asking.value = false),
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
