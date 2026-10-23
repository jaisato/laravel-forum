<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ArchiveRestore,
    FolderInput,
    Lock,
    LockOpen,
    Pin,
    PinOff,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import MoveThreadDialog from '@/components/forum/MoveThreadDialog.vue';
import { Button } from '@/components/ui/button';
import { lock, pin, restore, unlock, unpin } from '@/routes/moderation/threads';
import type { MoveTarget, Thread, ThreadAbilities } from '@/types/forum';

const props = defineProps<{
    thread: Pick<
        Thread,
        'slug' | 'is_pinned' | 'is_locked' | 'is_deleted' | 'category'
    >;
    can: Pick<ThreadAbilities, 'pin' | 'lock' | 'move' | 'restore'>;
    moveTargets: MoveTarget[];
}>();

const moving = ref(false);

type Action = { label: string; icon: Component; run: () => void };

function send(url: string): void {
    router.post(url, {}, { preserveScroll: true });
}

const actions = computed(() => {
    const slug = props.thread.slug;
    const list: Action[] = [];

    if (props.can.pin) {
        list.push(
            props.thread.is_pinned
                ? {
                      label: 'Unpin',
                      icon: PinOff,
                      run: () => send(unpin.url(slug)),
                  }
                : { label: 'Pin', icon: Pin, run: () => send(pin.url(slug)) },
        );
    }

    if (props.can.lock) {
        list.push(
            props.thread.is_locked
                ? {
                      label: 'Unlock',
                      icon: LockOpen,
                      run: () => send(unlock.url(slug)),
                  }
                : {
                      label: 'Lock',
                      icon: Lock,
                      run: () => send(lock.url(slug)),
                  },
        );
    }

    if (props.can.move) {
        list.push({
            label: 'Move',
            icon: FolderInput,
            run: () => {
                moving.value = true;
            },
        });
    }

    if (props.can.restore) {
        list.push({
            label: 'Restore',
            icon: ArchiveRestore,
            run: () => send(restore.url(slug)),
        });
    }

    return list;
});
</script>

<template>
    <div
        v-if="actions.length > 0"
        role="toolbar"
        :aria-label="$t('Moderation')"
        class="flex flex-wrap items-center gap-1"
    >
        <Button
            v-for="action in actions"
            :key="action.label"
            data-test="moderation-action"
            variant="ghost"
            size="sm"
            @click="action.run"
        >
            <component :is="action.icon" aria-hidden="true" />
            {{ $t(action.label) }}
        </Button>

        <MoveThreadDialog
            v-if="can.move"
            v-model:open="moving"
            :thread-slug="thread.slug"
            :current-category-id="thread.category.id"
            :targets="moveTargets"
        />
    </div>
</template>
