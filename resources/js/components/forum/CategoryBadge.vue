<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { CategorySummary } from '@/types/forum';

defineProps<{
    category: Pick<CategorySummary, 'name' | 'color'>;
    href?: string;
}>();
</script>

<template>
    <component
        :is="href ? Link : 'span'"
        :href="href"
        class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
        :class="{ 'hover:text-foreground': href }"
    >
        <span
            class="size-2 shrink-0 rounded-full"
            :style="{ backgroundColor: category.color }"
            aria-hidden="true"
        />
        {{ category.name }}
    </component>
</template>
