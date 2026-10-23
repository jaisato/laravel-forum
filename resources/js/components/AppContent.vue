<script setup lang="ts">
defineProps<{ class?: string }>();
</script>

<template>
    <main
        class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-4 py-6 sm:py-8"
        :class="$props.class"
    >
        <slot />
    </main>
</template>
