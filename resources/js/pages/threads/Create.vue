<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import MarkdownEditor from '@/components/forum/MarkdownEditor.vue';
import SimilarThreadsHint from '@/components/forum/SimilarThreadsHint.vue';
import TagSelect from '@/components/forum/TagSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useDraft } from '@/composables/useDraft';
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
            <span id="thread-tags" class="text-sm font-medium">
                {{ $t('Tags') }}
            </span>
            <TagSelect
                v-model="form.tags"
                :tags="tags"
                labelled-by="thread-tags"
            />
            <InputError :message="form.errors.tags" />
        </div>

        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing">
                {{ $t('Publish the thread') }}
            </Button>
        </div>
    </form>
</template>
