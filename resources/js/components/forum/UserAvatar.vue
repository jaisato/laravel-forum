<script setup lang="ts">
import { UserRound } from '@lucide/vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { cn } from '@/lib/utils';
import type { UserSummary } from '@/types/forum';

type Props = {
    /** null for an erased account. */
    user: Pick<UserSummary, 'name' | 'avatar_url'> | null;
    size?: 'sm' | 'md' | 'lg';
    class?: string;
};

const props = withDefaults(defineProps<Props>(), { size: 'md' });

const sizes = {
    sm: 'size-6 text-[10px]',
    md: 'size-9 text-xs',
    lg: 'size-20 text-xl',
};
</script>

<template>
    <Avatar
        :class="
            cn(
                'shrink-0 overflow-hidden rounded-full',
                sizes[size],
                props.class,
            )
        "
    >
        <AvatarImage
            v-if="user?.avatar_url"
            :src="user.avatar_url"
            :alt="user.name"
        />
        <AvatarFallback class="bg-muted font-semibold text-muted-foreground">
            <template v-if="user">{{ getInitials(user.name) }}</template>
            <UserRound
                v-else
                class="size-1/2"
                role="img"
                :aria-label="$t('Deleted user')"
            />
        </AvatarFallback>
    </Avatar>
</template>
