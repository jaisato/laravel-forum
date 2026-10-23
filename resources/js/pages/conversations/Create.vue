<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import MarkdownEditor from '@/components/forum/MarkdownEditor.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/conversations';
import type { UserSummary } from '@/types/forum';

const props = defineProps<{
    /** Filled in from ?to= (a profile's "Send a message"). */
    recipients: UserSummary[];
}>();

// Usernames separated by commas or spaces, "@" optional.
const to = ref(
    props.recipients.map((recipient) => recipient.username).join(', '),
);

const form = useForm({
    recipients: [] as string[],
    subject: '',
    body: '',
});

function submit(): void {
    if (form.processing) {
        return;
    }

    form.recipients = to.value
        .split(/[\s,]+/)
        .map((username) => username.replace(/^@/, ''))
        .filter((username) => username !== '');

    form.post(store.url(), { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('New conversation')" />

    <h1 class="text-2xl font-semibold tracking-tight">
        {{ $t('New conversation') }}
    </h1>

    <form class="flex flex-col gap-5" @submit.prevent="submit">
        <div class="grid gap-1.5">
            <Label for="conversation-recipients">{{ $t('To') }}</Label>
            <Input
                id="conversation-recipients"
                v-model="to"
                required
                autocomplete="off"
                :placeholder="$t('Usernames, separated by commas')"
                :aria-invalid="form.errors.recipients ? true : undefined"
            />
            <InputError :message="form.errors.recipients" />
        </div>

        <div class="grid gap-1.5">
            <Label for="conversation-subject">{{ $t('Subject') }}</Label>
            <Input
                id="conversation-subject"
                v-model="form.subject"
                required
                maxlength="150"
                :aria-invalid="form.errors.subject ? true : undefined"
            />
            <InputError :message="form.errors.subject" />
        </div>

        <div class="grid gap-1.5">
            <Label for="conversation-body">{{ $t('Message') }}</Label>
            <MarkdownEditor
                id="conversation-body"
                v-model="form.body"
                :rows="8"
                :max-length="10000"
                :error="form.errors.body"
                @submit="submit"
            />
        </div>

        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing">
                {{ $t('Send') }}
            </Button>
        </div>
    </form>
</template>
