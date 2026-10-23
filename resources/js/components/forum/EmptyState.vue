<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    description?: string;
    icon?: Component;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-6 py-12 text-center"
    >
        <component
            :is="icon"
            v-if="icon"
            class="mb-1 size-10 text-muted-foreground/70"
            aria-hidden="true"
        />
        <h3 class="text-base font-semibold">{{ title }}</h3>
        <p v-if="description" class="max-w-md text-sm text-muted-foreground">
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-3">
            <slot />
        </div>
    </div>
</template>
