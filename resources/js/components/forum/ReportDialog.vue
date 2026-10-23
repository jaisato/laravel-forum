<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
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
import { store } from '@/routes/posts/reports';

const props = defineProps<{
    open: boolean;
    postId: number;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

// The ReportReason cases and their labels.
const reasons = [
    { value: 'spam', label: 'Spam' },
    { value: 'abuse', label: 'Abuse or harassment' },
    { value: 'off_topic', label: 'Off topic' },
    { value: 'illegal', label: 'Illegal content' },
    { value: 'other', label: 'Other' },
];

const form = useForm({ reason: '', details: '' });

function submit(): void {
    form.post(store.url(props.postId), {
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
                data-test="report-form"
                class="grid gap-4"
                @submit.prevent="submit"
            >
                <DialogHeader>
                    <DialogTitle>{{ $t('Report this post') }}</DialogTitle>
                    <DialogDescription>
                        {{ $t('Tell the moderators what is wrong with it.') }}
                    </DialogDescription>
                </DialogHeader>

                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">
                        {{ $t('Reason') }}
                    </legend>
                    <label
                        v-for="reason in reasons"
                        :key="reason.value"
                        class="flex items-center gap-2 text-sm"
                    >
                        <input
                            v-model="form.reason"
                            type="radio"
                            name="reason"
                            data-test="report-reason"
                            :value="reason.value"
                        />
                        {{ $t(reason.label) }}
                    </label>
                    <InputError :message="form.errors.reason" />
                </fieldset>

                <label class="grid gap-1.5 text-sm font-medium">
                    {{ $t('Details (optional)') }}
                    <textarea
                        v-model="form.details"
                        data-test="report-details"
                        rows="3"
                        maxlength="1000"
                        class="rounded-md border bg-background px-3 py-2 font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                    />
                </label>
                <InputError :message="form.errors.details" />

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
                        data-test="submit-report"
                        :disabled="form.reason === '' || form.processing"
                    >
                        {{ $t('Send report') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
