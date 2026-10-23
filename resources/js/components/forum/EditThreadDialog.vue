<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import TagSelect from '@/components/forum/TagSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/threads';
import type { TagOption, Thread } from '@/types/forum';

const props = defineProps<{
    open: boolean;
    thread: Pick<Thread, 'slug' | 'title' | 'tags'>;
    tagOptions: TagOption[];
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const form = useForm({
    title: props.thread.title,
    tags: props.thread.tags.map((tag) => tag.id),
});

function submit(): void {
    form.patch(update.url(props.thread.slug), {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <form
                data-test="edit-thread-form"
                class="grid gap-4"
                @submit.prevent="submit"
            >
                <DialogHeader>
                    <DialogTitle>{{ $t('Edit the thread') }}</DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                'The text of the thread is its first post: edit it there.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-1.5">
                    <Label for="edit-thread-title">{{ $t('Title') }}</Label>
                    <Input
                        id="edit-thread-title"
                        v-model="form.title"
                        data-test="edit-thread-title"
                        maxlength="150"
                        required
                        :aria-invalid="form.errors.title ? true : undefined"
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="grid gap-1.5">
                    <span id="edit-thread-tags" class="text-sm font-medium">
                        {{ $t('Tags') }}
                    </span>
                    <TagSelect
                        v-model="form.tags"
                        :tags="tagOptions"
                        labelled-by="edit-thread-tags"
                    />
                    <InputError :message="form.errors.tags" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="emit('update:open', false)"
                    >
                        {{ $t('Cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ $t('Save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
