<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ArchiveRestore,
    CircleCheck,
    CircleX,
    EyeOff,
    Flag,
    Pencil,
    Quote,
    ShieldCheck,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import ReportDialog from '@/components/forum/ReportDialog.vue';
import { Button } from '@/components/ui/button';
import { approve, hide, restore } from '@/routes/moderation/posts';
import { destroy } from '@/routes/posts';
import {
    destroy as unmarkSolution,
    store as markSolution,
} from '@/routes/threads/solution';
import type { Post } from '@/types/forum';

const props = defineProps<{
    post: Post;
    threadSlug: string;
    /** May quote: the viewer may reply and got the Markdown source. */
    canQuote: boolean;
    /** May change the thread's solution (its author or staff). */
    canManageSolution: boolean;
}>();

const emit = defineEmits<{ quote: []; edit: [] }>();

const reporting = ref(false);
const options = { preserveScroll: true };

function send(url: string): void {
    router.post(url, {}, options);
}

function confirmDelete(question: string): void {
    if (window.confirm(question)) {
        router.delete(destroy.url(props.post.id), options);
    }
}
</script>

<template>
    <div class="flex flex-wrap items-center justify-end gap-0.5">
        <Button
            v-if="canQuote"
            data-test="post-action-quote"
            variant="ghost"
            size="sm"
            @click="emit('quote')"
        >
            <Quote aria-hidden="true" />{{ $t('Quote') }}
        </Button>
        <Button
            v-if="post.can.update"
            data-test="post-action-edit"
            variant="ghost"
            size="sm"
            @click="emit('edit')"
        >
            <Pencil aria-hidden="true" />{{ $t('Edit') }}
        </Button>
        <Button
            v-if="post.can.mark_solution"
            data-test="post-action-solution"
            variant="ghost"
            size="sm"
            @click="
                router.post(
                    markSolution.url(threadSlug),
                    { post_id: post.id },
                    options,
                )
            "
        >
            <CircleCheck aria-hidden="true" />{{ $t('Mark as solution') }}
        </Button>
        <Button
            v-if="post.is_solution && canManageSolution"
            data-test="post-action-unsolve"
            variant="ghost"
            size="sm"
            @click="router.delete(unmarkSolution.url(threadSlug), options)"
        >
            <CircleX aria-hidden="true" />{{ $t('Unmark solution') }}
        </Button>
        <Button
            v-if="post.can.report"
            data-test="post-action-report"
            variant="ghost"
            size="sm"
            @click="reporting = true"
        >
            <Flag aria-hidden="true" />{{ $t('Report') }}
        </Button>
        <Button
            v-if="post.can.hide"
            data-test="post-action-hide"
            variant="ghost"
            size="sm"
            @click="send(hide.url(post.id))"
        >
            <EyeOff aria-hidden="true" />{{ $t('Hide') }}
        </Button>
        <Button
            v-if="post.can.approve"
            data-test="post-action-approve"
            variant="ghost"
            size="sm"
            @click="send(approve.url(post.id))"
        >
            <ShieldCheck aria-hidden="true" />{{ $t('Approve') }}
        </Button>
        <Button
            v-if="post.can.restore"
            data-test="post-action-restore"
            variant="ghost"
            size="sm"
            @click="send(restore.url(post.id))"
        >
            <ArchiveRestore aria-hidden="true" />{{ $t('Restore') }}
        </Button>
        <Button
            v-if="post.can.delete"
            data-test="post-action-delete"
            variant="ghost"
            size="sm"
            class="text-destructive hover:text-destructive"
            @click="
                confirmDelete(
                    post.is_first
                        ? $t('Delete the whole thread?')
                        : $t('Delete this post?'),
                )
            "
        >
            <Trash2 aria-hidden="true" />{{ $t('Delete') }}
        </Button>

        <ReportDialog
            v-if="post.can.report"
            v-model:open="reporting"
            :post-id="post.id"
        />
    </div>
</template>
