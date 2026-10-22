<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useRelativeTime } from '@/composables/useRelativeTime';

const props = defineProps<{ datetime: string }>();

const page = usePage();

// The server renders with its own clock and timezone, hence the
// data-allow-mismatch on the element: the browser corrects it on hydration.
const { relative, absolute } = useRelativeTime(
    () => props.datetime,
    () => page.props.locale,
);
</script>

<template>
    <time :datetime="datetime" :title="absolute" data-allow-mismatch>{{
        relative
    }}</time>
</template>
