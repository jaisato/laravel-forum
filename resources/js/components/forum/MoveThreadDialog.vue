<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { move } from '@/routes/moderation/threads';
import type { MoveTarget } from '@/types/forum';

const props = defineProps<{
    open: boolean;
    threadSlug: string;
    currentCategoryId: number;
    targets: MoveTarget[];
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const form = useForm({ category_id: null as number | null });

function submit(): void {
    form.post(move.url(props.threadSlug), {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
            form.reset();
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <form
                data-test="move-form"
                class="grid gap-4"
                @submit.prevent="submit"
            >
                <DialogHeader>
                    <DialogTitle>{{ $t('Move the thread') }}</DialogTitle>
                </DialogHeader>

                <label class="grid gap-1.5 text-sm font-medium">
                    {{ $t('Category') }}
                    <select
                        v-model.number="form.category_id"
                        data-test="move-target"
                        class="h-9 rounded-md border bg-background px-2 font-normal"
                    >
                        <option
                            v-for="target in targets"
                            :key="target.id"
                            :value="target.id"
                            :disabled="target.id === currentCategoryId"
                        >
                            {{ target.name }}
                        </option>
                    </select>
                </label>
                <InputError :message="form.errors.category_id" />

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="emit('update:open', false)"
                    >
                        {{ $t('Cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :disabled="
                            form.category_id === null ||
                            form.category_id === currentCategoryId ||
                            form.processing
                        "
                    >
                        {{ $t('Move') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
