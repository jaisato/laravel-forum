<script setup lang="ts">
import SeoHead from '@/components/SeoHead.vue';
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <SeoHead />
    <AppHeaderLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppHeaderLayout>
</template>
