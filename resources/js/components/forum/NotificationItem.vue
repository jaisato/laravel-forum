<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AtSign,
    CircleCheck,
    Mail,
    MessageSquare,
    ShieldAlert,
} from '@lucide/vue';
import { computed } from 'vue';
import RelativeTime from '@/components/forum/RelativeTime.vue';
import UserAvatar from '@/components/forum/UserAvatar.vue';
import { read } from '@/routes/notifications';
import type { ForumNotification, NotificationType } from '@/types/forum';

const props = defineProps<{ notification: ForumNotification }>();

const icons = {
    thread_replied: MessageSquare,
    user_mentioned: AtSign,
    solution_accepted: CircleCheck,
    post_moderated: ShieldAlert,
    new_private_message: Mail,
} satisfies Record<NotificationType, unknown>;

const unread = computed(() => props.notification.read_at === null);

/**
 * Opening a notification goes through the server, which marks it read and
 * sends the member where it leads (or back here when it no longer leads
 * anywhere). Opening it in another tab is left to the browser.
 */
function open(event: MouseEvent): void {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();
    router.post(read.url(props.notification.id));
}
</script>

<template>
    <component
        :is="notification.url ? 'a' : 'button'"
        :href="notification.url ?? undefined"
        :type="notification.url ? undefined : 'button'"
        data-test="notification"
        :data-unread="unread ? 'true' : undefined"
        class="flex w-full gap-3 px-4 py-3 text-left transition-colors hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
        :class="{ 'bg-primary/5': unread }"
        @click="open"
    >
        <UserAvatar
            v-if="notification.actor"
            :user="{
                name: notification.actor.username,
                avatar_url: notification.actor.avatar_url,
            }"
            size="md"
        />
        <span
            v-else
            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground"
        >
            <component
                :is="icons[notification.type]"
                class="size-4"
                aria-hidden="true"
            />
        </span>

        <span class="min-w-0 flex-1">
            <span
                data-test="notification-title"
                class="block text-sm break-words"
                :class="unread ? 'font-semibold' : 'text-foreground/90'"
                >{{ notification.title }}</span
            >
            <span
                v-if="notification.excerpt"
                data-test="notification-excerpt"
                class="mt-0.5 line-clamp-2 block text-sm text-muted-foreground"
                >{{ notification.excerpt }}</span
            >
            <span class="mt-1 block text-xs text-muted-foreground">
                <RelativeTime :datetime="notification.created_at" />
            </span>
        </span>

        <template v-if="unread">
            <span
                class="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                aria-hidden="true"
            />
            <span class="sr-only">{{ $t('Unread') }}</span>
        </template>
    </component>
</template>
