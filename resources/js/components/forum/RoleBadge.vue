<script setup lang="ts">
import { ShieldCheck } from '@lucide/vue';
import type { Role } from '@/types/forum';

defineProps<{ role: Role }>();
</script>

<template>
    <span
        v-if="role !== 'member'"
        class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium"
        :class="
            role === 'admin'
                ? 'bg-red-500/10 text-red-700 dark:text-red-300'
                : 'bg-blue-500/10 text-blue-700 dark:text-blue-300'
        "
    >
        <ShieldCheck class="size-3" aria-hidden="true" />
        {{ role === 'admin' ? $t('Administrator') : $t('Moderator') }}
    </span>
</template>
