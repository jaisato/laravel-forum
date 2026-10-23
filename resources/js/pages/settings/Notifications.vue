<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/notifications/preferences';

type Preferences = {
    email_replies: boolean;
    email_mentions: boolean;
    email_solutions: boolean;
    email_messages: boolean;
    email_moderation: boolean;
    allow_private_messages: boolean;
};

const props = defineProps<{ preferences: Preferences }>();

const form = useForm({ ...props.preferences });

const emails: { key: keyof Preferences; label: string }[] = [
    { key: 'email_replies', label: 'New replies in the threads I follow' },
    { key: 'email_mentions', label: 'Mentions of my @username' },
    { key: 'email_solutions', label: 'My reply chosen as the solution' },
    { key: 'email_messages', label: 'New private messages' },
    { key: 'email_moderation', label: 'Moderation of my posts' },
];

function submit(): void {
    form.put(update.url(), { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('Notification settings')" />

    <h1 class="sr-only">{{ $t('Notification settings') }}</h1>

    <form class="space-y-8" @submit.prevent="submit">
        <fieldset class="space-y-4">
            <Heading
                variant="small"
                title="Emails"
                description="The bell always tells you. Choose what also reaches your inbox."
            />

            <div
                v-for="email in emails"
                :key="email.key"
                class="flex items-center gap-3"
            >
                <input
                    :id="email.key"
                    v-model="form[email.key]"
                    type="checkbox"
                    class="size-4 rounded border-input accent-primary"
                />
                <label :for="email.key" class="text-sm">{{
                    $t(email.label)
                }}</label>
            </div>
        </fieldset>

        <fieldset class="space-y-4">
            <Heading
                variant="small"
                title="Private messages"
                description="Staff can always write to you."
            />

            <div class="flex items-center gap-3">
                <input
                    id="allow_private_messages"
                    v-model="form.allow_private_messages"
                    type="checkbox"
                    class="size-4 rounded border-input accent-primary"
                />
                <label for="allow_private_messages" class="text-sm">{{
                    $t('Other members may send me private messages')
                }}</label>
            </div>
        </fieldset>

        <Button type="submit" :disabled="form.processing">{{
            $t('Save')
        }}</Button>
    </form>
</template>
