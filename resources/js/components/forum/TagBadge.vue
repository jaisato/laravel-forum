<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { TagSummary } from '@/types/forum';

defineProps<{
    tag: Pick<TagSummary, 'name' | 'color'>;
    href?: string;
}>();
</script>

<template>
    <component
        :is="href ? Link : 'span'"
        :href="href"
        class="inline-flex items-center rounded-md border px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground"
        :class="{ 'hover:border-foreground/30 hover:text-foreground': href }"
        :style="tag.color ? { borderColor: `${tag.color}55` } : undefined"
    >
        #{{ tag.name }}
    </component>
</template>
