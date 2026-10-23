<script setup lang="ts">
import { Head, router, useForm, useHttp, usePage } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import MarkdownEditor from '@/components/forum/MarkdownEditor.vue';
import SimilarThreadsHint from '@/components/forum/SimilarThreadsHint.vue';
import TagSelect from '@/components/forum/TagSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useDraft } from '@/composables/useDraft';
import { tagSuggestions } from '@/routes/ai';
import { store } from '@/routes/threads';
import type { CategoryOption, SimilarThread, TagOption } from '@/types/forum';

const props = defineProps<{
    /** The categories the member may start threads in. */
    categories: CategoryOption[];
    tags: TagOption[];
    /** The category they came from, when they may post there. */
    category: number | null;
    /** Threads like the title being typed, refreshed by partial reloads. */
    similar: SimilarThread[];
}>();

/** Shorter titles say too little to look for similar threads. */
const SIMILAR_MIN_LENGTH = 10;

/** forum.threads.max_tags, as TagSelect. */
const MAX_TAGS = 5;

const page = usePage();

// What the member writes survives a reload until the thread is published.
const title = useDraft('new-thread-title');
const body = useDraft('new-thread-body');

const form = useForm({
    category_id: props.category,
    title: '',
    body: '',
    tags: [] as number[],
});

// The similar threads follow the title once it says enough (and go away
// when it no longer does), without touching the address.
watchDebounced(
    title.text,
    (value) => {
        const text = value.trim();

        if (text.length < SIMILAR_MIN_LENGTH && props.similar.length === 0) {
            return;
        }

        router.reload({
            only: ['similar'],
            data: { title: text },
            preserveUrl: true,
        });
    },
    { debounce: 400 },
);

// "Suggest tags": the AI picks among the forum's tags from what the member
// wrote so far; they are added to the ones already chosen.
const suggestions = useHttp<
    { title: string; body: string },
    { tags: TagOption[] }
>({
    title: '',
    body: '',
});
const suggestionNote = ref<string | null>(null);

async function suggestTags(): Promise<void> {
    suggestions.title = title.text.value;
    suggestions.body = body.text.value;
    suggestionNote.value = null;

    try {
        const { tags } = await suggestions.post(tagSuggestions.url());
        const chosen = [...form.tags];

        for (const tag of tags) {
            if (!chosen.includes(tag.id) && chosen.length < MAX_TAGS) {
                chosen.push(tag.id);
            }
        }

        form.tags = chosen;

        if (tags.length === 0) {
            suggestionNote.value = 'No tag fits what you wrote yet.';
        }
    } catch {
        suggestionNote.value = 'Tag suggestions are not available right now.';
    }
}

function submit(): void {
    if (form.processing) {
        return;
    }

    form.title = title.text.value;
    form.body = body.text.value;

    form.post(store.url(), {
        onSuccess: () => {
            title.clear();
            body.clear();
        },
    });
}
</script>

<template>
    <Head :title="$t('New thread')" />

    <h1 class="text-2xl font-semibold tracking-tight">
        {{ $t('New thread') }}
    </h1>

    <form class="flex flex-col gap-5" @submit.prevent="submit">
        <div class="grid gap-1.5">
            <Label for="thread-category">{{ $t('Category') }}</Label>
            <select
                id="thread-category"
                v-model.number="form.category_id"
                required
                class="h-9 rounded-md border bg-background px-2 text-sm"
                :aria-invalid="form.errors.category_id ? true : undefined"
            >
                <option :value="null" disabled>
                    {{ $t('Choose a category') }}
                </option>
                <option
                    v-for="option in categories"
                    :key="option.id"
                    :value="option.id"
                >
                    {{ option.name }}
                </option>
            </select>
            <InputError :message="form.errors.category_id" />
        </div>

        <div class="grid gap-1.5">
            <Label for="thread-title">{{ $t('Title') }}</Label>
            <Input
                id="thread-title"
                v-model="title.text.value"
                required
                maxlength="150"
                :placeholder="$t('What do you want to ask or share?')"
                :aria-invalid="form.errors.title ? true : undefined"
            />
            <InputError :message="form.errors.title" />
            <SimilarThreadsHint :threads="similar" />
        </div>

        <div class="grid gap-1.5">
            <Label for="thread-body">{{ $t('Message') }}</Label>
            <MarkdownEditor
                id="thread-body"
                v-model="body.text.value"
                :rows="10"
                :placeholder="
                    $t('Give the details: what you tried, what happened…')
                "
                :error="form.errors.body"
                @submit="submit"
            />
        </div>

        <div class="grid gap-1.5">
            <div class="flex items-center justify-between gap-2">
                <span id="thread-tags" class="text-sm font-medium">
                    {{ $t('Tags') }}
                </span>
                <Button
                    v-if="page.props.features.ai.tags"
                    type="button"
                    variant="ghost"
                    size="sm"
                    data-test="suggest-tags"
                    :disabled="
                        suggestions.processing || title.text.value.trim() === ''
                    "
                    @click="suggestTags"
                >
                    <Sparkles aria-hidden="true" />
                    {{ $t('Suggest tags') }}
                </Button>
            </div>
            <TagSelect
                v-model="form.tags"
                :tags="tags"
                :max="MAX_TAGS"
                labelled-by="thread-tags"
            />
            <p
                v-if="suggestionNote"
                aria-live="polite"
                class="text-xs text-muted-foreground"
            >
                {{ $t(suggestionNote) }}
            </p>
            <InputError :message="form.errors.tags" />
        </div>

        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing">
                {{ $t('Publish the thread') }}
            </Button>
        </div>
    </form>
</template>
