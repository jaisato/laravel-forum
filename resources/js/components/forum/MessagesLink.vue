<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Mail } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    href: string;
    /** Conversations with messages the member has not read. */
    count: number;
}>();

const badge = computed(() => (props.count > 99 ? '99+' : String(props.count)));
</script>

<template>
    <Link
        :href="href"
        class="relative inline-flex size-9 items-center justify-center rounded-md hover:bg-accent"
        :aria-label="
            count > 0
                ? $t('Messages (:count unread)', { count: String(count) })
                : $t('Messages')
        "
    >
        <Mail class="size-5" aria-hidden="true" />
        <span
            v-if="count > 0"
            data-test="unread-count"
            class="absolute -top-0.5 -right-0.5 min-w-4 rounded-full bg-red-600 px-1 text-center text-[10px] leading-4 font-semibold text-white"
            >{{ badge }}</span
        >
    </Link>
</template>
