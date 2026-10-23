<script setup lang="ts">
import { computed } from 'vue';
import type { Thread } from '@/types/forum';

const props = defineProps<{
    thread: Pick<
        Thread,
        'is_pinned' | 'is_locked' | 'is_solved' | 'status' | 'is_deleted'
    >;
}>();

const tones = {
    info: 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
    warning: 'bg-amber-500/10 text-amber-800 dark:text-amber-300',
    success: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    danger: 'bg-red-500/10 text-red-700 dark:text-red-300',
};

const badges = computed(() => {
    const list: { label: string; tone: keyof typeof tones }[] = [];

    if (props.thread.is_pinned) {
        list.push({ label: 'Pinned', tone: 'info' });
    }

    if (props.thread.is_locked) {
        list.push({ label: 'Locked', tone: 'warning' });
    }

    if (props.thread.is_solved) {
        list.push({ label: 'Solved', tone: 'success' });
    }

    // Only staff and the author ever see threads in these states.
    if (props.thread.status === 'hidden') {
        list.push({ label: 'Hidden', tone: 'danger' });
    } else if (props.thread.status === 'pending_review') {
        list.push({ label: 'Pending review', tone: 'warning' });
    }

    if (props.thread.is_deleted) {
        list.push({ label: 'Deleted', tone: 'danger' });
    }

    return list;
});
</script>

<template>
    <ul v-if="badges.length > 0" class="flex flex-wrap gap-1.5">
        <li
            v-for="badge in badges"
            :key="badge.label"
            data-test="status-badge"
            class="rounded-md px-1.5 py-0.5 text-[11px] font-medium"
            :class="tones[badge.tone]"
        >
            {{ $t(badge.label) }}
        </li>
    </ul>
</template>
