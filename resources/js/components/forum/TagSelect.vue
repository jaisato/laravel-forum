<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import type { TagOption } from '@/types/forum';

const props = withDefaults(
    defineProps<{
        tags: TagOption[];
        modelValue: number[];
        /** forum.threads.max_tags; the server checks it too. */
        max?: number;
        labelledBy?: string;
    }>(),
    { max: 5, labelledBy: undefined },
);

const emit = defineEmits<{ 'update:modelValue': [value: number[]] }>();

const full = computed(() => props.modelValue.length >= props.max);

function isChosen(id: number): boolean {
    return props.modelValue.includes(id);
}

function toggle(id: number): void {
    emit(
        'update:modelValue',
        isChosen(id)
            ? props.modelValue.filter((chosen) => chosen !== id)
            : [...props.modelValue, id],
    );
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div
            v-if="tags.length > 0"
            role="group"
            :aria-labelledby="labelledBy"
            class="flex flex-wrap gap-1.5"
        >
            <button
                v-for="tag in tags"
                :key="tag.id"
                type="button"
                :data-test="`tag-option-${tag.id}`"
                :aria-pressed="isChosen(tag.id)"
                :disabled="!isChosen(tag.id) && full"
                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 aria-pressed:border-primary aria-pressed:bg-primary aria-pressed:text-primary-foreground"
                @click="toggle(tag.id)"
            >
                <Check
                    v-if="isChosen(tag.id)"
                    class="size-3"
                    aria-hidden="true"
                />
                <span
                    v-else-if="tag.color"
                    class="size-2 rounded-full"
                    :style="{ backgroundColor: tag.color }"
                    aria-hidden="true"
                />
                {{ tag.name }}
            </button>
        </div>
        <p v-else class="text-sm text-muted-foreground">
            {{ $t('There are no tags yet.') }}
        </p>

        <p
            v-if="tags.length > 0"
            data-test="tag-count"
            class="text-xs text-muted-foreground"
            aria-live="polite"
        >
            {{ modelValue.length }} / {{ max }}
        </p>
    </div>
</template>
