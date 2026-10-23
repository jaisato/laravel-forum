<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Bell, BellOff } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { destroy, store } from '@/routes/threads/subscription';

const props = defineProps<{
    threadSlug: string;
    subscribed: boolean;
}>();

function toggle(): void {
    const options = { preserveScroll: true };

    if (props.subscribed) {
        router.delete(destroy.url(props.threadSlug), options);
    } else {
        router.post(store.url(props.threadSlug), {}, options);
    }
}
</script>

<template>
    <Button
        variant="outline"
        size="sm"
        :aria-pressed="subscribed ? 'true' : 'false'"
        @click="toggle"
    >
        <BellOff v-if="subscribed" aria-hidden="true" />
        <Bell v-else aria-hidden="true" />
        {{ subscribed ? $t('Following') : $t('Follow') }}
    </Button>
</template>
