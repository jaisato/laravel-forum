<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarkdownContent from '@/components/forum/MarkdownContent.vue';
import InputError from '@/components/InputError.vue';
import { preview as previewRoute } from '@/routes/markdown';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        id?: string;
        placeholder?: string;
        rows?: number;
        maxLength?: number;
        error?: string;
        disabled?: boolean;
    }>(),
    { rows: 6, maxLength: 30000 },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    submit: [];
}>();

const tab = ref<'write' | 'preview'>('write');
const state = ref<'idle' | 'loading' | 'failed'>('idle');
const html = ref<string | null>(null);
const textarea = ref<HTMLTextAreaElement | null>(null);
const http = useHttp({ body: '' });
// Only the latest preview request may fill the tab.
let latestPreview = 0;

const isEmpty = computed(() => props.modelValue.trim() === '');
// The counter only shows up when the limit gets close.
const showCount = computed(
    () => props.modelValue.length >= props.maxLength * 0.9,
);

/**
 * The preview comes from the server, so it is exactly what the post will
 * look like: same Markdown flavour, mentions and sanitising.
 */
async function showPreview(): Promise<void> {
    const request = ++latestPreview;

    tab.value = 'preview';
    html.value = null;

    if (isEmpty.value) {
        return;
    }

    state.value = 'loading';
    http.body = props.modelValue;

    try {
        const response = (await http.post(previewRoute.url())) as {
            html: string;
        };

        if (request === latestPreview) {
            html.value = response.html;
            state.value = 'idle';
        }
    } catch {
        if (request === latestPreview) {
            state.value = 'failed';
        }
    }
}

function onInput(event: Event): void {
    emit('update:modelValue', (event.target as HTMLTextAreaElement).value);
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        emit('submit');
    }
}

defineExpose({
    focus: (): void => {
        tab.value = 'write';
        textarea.value?.focus();
    },
});
</script>

<template>
    <div
        class="rounded-lg border bg-background focus-within:ring-2 focus-within:ring-ring/50"
        :class="{ 'border-destructive': error }"
    >
        <div
            role="tablist"
            class="flex items-center gap-1 border-b px-2 pt-1.5 text-sm"
        >
            <button
                type="button"
                role="tab"
                data-test="tab-write"
                :aria-selected="tab === 'write' ? 'true' : 'false'"
                class="rounded-t-md px-3 py-1.5"
                :class="
                    tab === 'write'
                        ? 'bg-muted font-medium'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="tab = 'write'"
            >
                {{ $t('Write') }}
            </button>
            <button
                type="button"
                role="tab"
                data-test="tab-preview"
                :aria-selected="tab === 'preview' ? 'true' : 'false'"
                class="rounded-t-md px-3 py-1.5"
                :class="
                    tab === 'preview'
                        ? 'bg-muted font-medium'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="showPreview"
            >
                {{ $t('Preview') }}
            </button>
            <span
                class="ml-auto hidden text-xs text-muted-foreground sm:inline"
            >
                {{ $t('Markdown is supported') }}
            </span>
        </div>

        <textarea
            v-if="tab === 'write'"
            :id="id"
            ref="textarea"
            :value="modelValue"
            :rows="rows"
            :placeholder="placeholder"
            :disabled="disabled"
            :maxlength="maxLength"
            :aria-invalid="error ? 'true' : undefined"
            class="block w-full resize-y bg-transparent px-3 py-2 text-sm outline-none placeholder:text-muted-foreground disabled:opacity-50"
            @input="onInput"
            @keydown="onKeydown"
        />
        <div
            v-else
            data-test="preview"
            class="min-h-32 px-3 py-2 text-sm"
            aria-live="polite"
        >
            <p v-if="isEmpty" class="text-muted-foreground">
                {{ $t('Nothing to preview.') }}
            </p>
            <p v-else-if="state === 'loading'" class="text-muted-foreground">
                {{ $t('Loading the preview…') }}
            </p>
            <p v-else-if="state === 'failed'" class="text-destructive">
                {{ $t('The preview is not available right now.') }}
            </p>
            <MarkdownContent v-else-if="html !== null" :html="html" />
        </div>

        <div
            v-if="error || showCount"
            class="flex items-start justify-between gap-2 px-3 pb-2"
        >
            <InputError :message="error" />
            <span
                v-if="showCount"
                data-test="char-count"
                class="ml-auto text-xs text-muted-foreground tabular-nums"
                >{{ modelValue.length }} / {{ maxLength }}</span
            >
        </div>
    </div>
</template>
