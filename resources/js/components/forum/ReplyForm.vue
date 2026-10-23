<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import MarkdownEditor from '@/components/forum/MarkdownEditor.vue';
import { Button } from '@/components/ui/button';
import { useDraft } from '@/composables/useDraft';
import { store } from '@/routes/posts';
import type { Post, Thread } from '@/types/forum';

const props = defineProps<{
    thread: Pick<Thread, 'id' | 'slug'>;
}>();

// What the member writes survives a reload until the reply is published.
const { text, clear } = useDraft(`reply-${props.thread.id}`);
const form = useForm({ body: '', reply_to_id: null as number | null });
const quoted = ref<Pick<Post, 'id' | 'author'> | null>(null);
const editor = ref<InstanceType<typeof MarkdownEditor> | null>(null);
const root = ref<HTMLFormElement | null>(null);

const isEmpty = computed(() => text.value.trim() === '');

function submit(): void {
    if (isEmpty.value || form.processing) {
        return;
    }

    form.body = text.value;
    form.reply_to_id = quoted.value?.id ?? null;

    form.post(store.url(props.thread.slug), {
        preserveScroll: true,
        onSuccess: () => {
            clear();
            quoted.value = null;
            form.reset();
        },
    });
}

/**
 * Quotes a post: its text goes in as a Markdown quote and the reply is
 * linked to it.
 */
function quote(post: Pick<Post, 'id' | 'body' | 'author'>): void {
    const quote = (post.body ?? '')
        .split('\n')
        .map((line) => `> ${line}`)
        .join('\n');
    const before = isEmpty.value ? '' : `${text.value.trimEnd()}\n\n`;

    text.value = `${before}${quote}\n\n`;
    quoted.value = { id: post.id, author: post.author };
    form.reply_to_id = post.id;

    void nextTick(() => {
        root.value?.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
        editor.value?.focus();
    });
}

function dropQuote(): void {
    quoted.value = null;
    form.reply_to_id = null;
}

defineExpose({ quote });
</script>

<template>
    <form ref="root" class="flex flex-col gap-3" @submit.prevent="submit">
        <div
            v-if="quoted"
            data-test="replying-to"
            class="flex items-center gap-2 text-sm text-muted-foreground"
        >
            {{
                $t('Replying to :name', {
                    name: quoted.author?.name ?? $t('Deleted user'),
                })
            }}
            <button
                type="button"
                data-test="cancel-quote"
                class="rounded p-0.5 hover:bg-accent hover:text-foreground"
                :aria-label="$t('Do not reply to this post')"
                @click="dropQuote"
            >
                <X class="size-3.5" aria-hidden="true" />
            </button>
        </div>

        <label for="reply-body" class="sr-only">{{ $t('Your reply') }}</label>
        <MarkdownEditor
            id="reply-body"
            ref="editor"
            v-model="text"
            :placeholder="$t('Write your reply…')"
            :error="form.errors.body ?? form.errors.reply_to_id"
            @submit="submit"
        />

        <div class="flex items-center justify-end gap-3">
            <span class="hidden text-xs text-muted-foreground sm:inline">
                {{ $t('Ctrl + Enter to send') }}
            </span>
            <Button
                type="submit"
                data-test="submit-reply"
                :disabled="isEmpty || form.processing"
            >
                {{ $t('Post reply') }}
            </Button>
        </div>
    </form>
</template>
