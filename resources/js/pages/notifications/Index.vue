<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Bell, CheckCheck } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/forum/EmptyState.vue';
import NotificationItem from '@/components/forum/NotificationItem.vue';
import Pagination from '@/components/forum/Pagination.vue';
import { Button } from '@/components/ui/button';
import { readAll } from '@/routes/notifications';
import type { ForumNotification, Paginated } from '@/types/forum';

defineProps<{
    notifications: Paginated<ForumNotification>;
}>();

const page = usePage();

// The count of the whole list, not only of this page: the same as the bell.
const unread = computed(() => page.props.auth.user?.unread_notifications ?? 0);

function markAllRead(): void {
    router.post(readAll.url(), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('Notifications')" />

    <header class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold tracking-tight">
            {{ $t('Notifications') }}
        </h1>

        <Button
            v-if="unread > 0"
            variant="outline"
            size="sm"
            data-test="mark-all-read"
            @click="markAllRead"
        >
            <CheckCheck aria-hidden="true" />
            {{ $t('Mark all as read') }}
        </Button>
    </header>

    <ul
        v-if="notifications.data.length > 0"
        class="divide-y overflow-hidden rounded-xl border bg-card"
    >
        <li v-for="notification in notifications.data" :key="notification.id">
            <NotificationItem :notification="notification" />
        </li>
    </ul>
    <EmptyState
        v-else
        data-test="empty-notifications"
        :icon="Bell"
        :title="$t('No notifications yet')"
        :description="
            $t(
                'Replies in the threads you follow, mentions and solutions will show up here.',
            )
        "
    />

    <Pagination :links="notifications.links" :meta="notifications.meta" />
</template>
