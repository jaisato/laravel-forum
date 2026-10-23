<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { SmilePlus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toggle as toggleReaction } from '@/routes/posts/reactions';
import type { ReactionSummary, ReactionType } from '@/types/forum';

const props = defineProps<{
    postId: number;
    reactions: ReactionSummary[];
    canReact: boolean;
}>();

const labels: Record<ReactionType, string> = {
    like: 'Like',
    love: 'Love',
    laugh: 'Funny',
    insightful: 'Insightful',
    celebrate: 'Celebrate',
};

const picker = ref<HTMLDetailsElement | null>(null);

const given = computed(() =>
    props.reactions.filter((reaction) => reaction.count > 0),
);

function toggle(type: ReactionType): void {
    if (!props.canReact) {
        return;
    }

    if (picker.value) {
        picker.value.open = false;
    }

    // Only the posts come back: the reply being written stays as it is.
    router.post(
        toggleReaction.url(props.postId),
        { type },
        { preserveScroll: true, preserveState: true, only: ['posts'] },
    );
}
</script>

<template>
    <div
        v-if="given.length > 0 || canReact"
        class="flex flex-wrap items-center gap-1.5"
    >
        <button
            v-for="reaction in given"
            :key="reaction.type"
            type="button"
            data-test="reaction"
            :aria-pressed="reaction.reacted ? 'true' : 'false'"
            :disabled="!canReact"
            :title="$t(labels[reaction.type])"
            class="inline-flex h-7 items-center gap-1 rounded-full border px-2 text-xs tabular-nums transition-colors enabled:hover:bg-accent disabled:cursor-default"
            :class="
                reaction.reacted
                    ? 'border-primary/40 bg-primary/10 font-semibold'
                    : 'text-muted-foreground'
            "
            @click="toggle(reaction.type)"
        >
            {{ reaction.emoji }} {{ reaction.count }}
        </button>

        <details v-if="canReact" ref="picker" class="relative">
            <summary
                class="inline-flex h-7 cursor-pointer list-none items-center rounded-full border px-2 text-muted-foreground hover:bg-accent [&::-webkit-details-marker]:hidden"
                :title="$t('Add a reaction')"
            >
                <SmilePlus class="size-4" aria-hidden="true" />
                <span class="sr-only">{{ $t('Add a reaction') }}</span>
            </summary>
            <div
                class="absolute bottom-full left-0 z-10 mb-1 flex gap-0.5 rounded-full border bg-popover p-1 shadow-md"
            >
                <button
                    v-for="reaction in reactions"
                    :key="reaction.type"
                    type="button"
                    data-test="reaction-option"
                    :aria-label="$t(labels[reaction.type])"
                    :title="$t(labels[reaction.type])"
                    class="flex size-8 items-center justify-center rounded-full text-base hover:bg-accent"
                    @click="toggle(reaction.type)"
                >
                    {{ reaction.emoji }}
                </button>
            </div>
        </details>
    </div>
</template>
