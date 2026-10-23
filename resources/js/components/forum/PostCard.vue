<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { CircleCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import MarkdownContent from '@/components/forum/MarkdownContent.vue';
import MarkdownEditor from '@/components/forum/MarkdownEditor.vue';
import PostActions from '@/components/forum/PostActions.vue';
import PostReactions from '@/components/forum/PostReactions.vue';
import RelativeTime from '@/components/forum/RelativeTime.vue';
import RoleBadge from '@/components/forum/RoleBadge.vue';
import UserAvatar from '@/components/forum/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/posts';
import { show as showUser } from '@/routes/users';
import type { Post } from '@/types/forum';

const props = defineProps<{
    post: Post;
    threadSlug: string;
    canManageSolution: boolean;
    /** The post the page was opened at (?post= or ?unread=1). */
    focused: boolean;
}>();

const emit = defineEmits<{ quote: [post: Post] }>();

const editing = ref(false);
const form = useForm({ body: props.post.body ?? '' });

// Only staff and the author ever receive posts in these states.
const states = computed(() => {
    const list: string[] = [];

    if (props.post.status === 'hidden') {
        list.push('Hidden');
    } else if (props.post.status === 'pending_review') {
        list.push('Pending review');
    }

    if (props.post.is_deleted) {
        list.push('Deleted');
    }

    return list;
});

function edit(): void {
    form.body = props.post.body ?? '';
    form.clearErrors();
    editing.value = true;
}

function save(): void {
    form.patch(update.url(props.post.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = false;
        },
    });
}
</script>

<template>
    <article
        :id="`post-${post.id}`"
        :data-focused="focused ? 'true' : undefined"
        class="scroll-mt-24 rounded-xl border bg-card transition-shadow"
        :class="{
            'ring-2 ring-primary/40': focused,
            'border-emerald-500/60': post.is_solution,
            'opacity-75': post.is_deleted,
        }"
    >
        <header
            class="flex flex-wrap items-center gap-x-2 gap-y-1 border-b px-4 py-2 text-sm"
        >
            <UserAvatar :user="post.author" size="sm" />
            <Link
                v-if="post.author"
                :href="showUser.url(post.author.username)"
                data-test="post-author"
                class="font-medium hover:underline"
                >{{ post.author.name }}</Link
            >
            <span v-else data-test="post-author" class="font-medium">{{
                $t('Deleted user')
            }}</span>
            <RoleBadge v-if="post.author" :role="post.author.role" />
            <span class="text-muted-foreground" aria-hidden="true">·</span>
            <a
                :href="`#post-${post.id}`"
                class="text-muted-foreground hover:underline"
            >
                <RelativeTime :datetime="post.created_at" />
            </a>
            <span
                v-if="post.edited_at"
                data-test="edited"
                class="text-xs text-muted-foreground"
                >{{ $t('edited') }}</span
            >
            <span
                v-if="post.is_solution"
                data-test="solution-badge"
                class="inline-flex items-center gap-1 rounded-md bg-emerald-500/10 px-1.5 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-300"
            >
                <CircleCheck class="size-3" aria-hidden="true" />{{
                    $t('Solution')
                }}
            </span>
            <span
                v-for="state in states"
                :key="state"
                data-test="post-state"
                class="rounded-md bg-red-500/10 px-1.5 py-0.5 text-[11px] font-medium text-red-700 dark:text-red-300"
                >{{ $t(state) }}</span
            >
        </header>

        <div class="px-4 py-3">
            <a
                v-if="post.reply_to"
                data-test="reply-to"
                :href="`#post-${post.reply_to.id}`"
                class="mb-2 inline-flex text-xs text-muted-foreground hover:underline"
            >
                {{
                    $t('In reply to :name', {
                        name: post.reply_to.author?.name ?? $t('Deleted user'),
                    })
                }}
            </a>

            <form
                v-if="editing"
                data-test="edit-form"
                class="grid gap-2"
                @submit.prevent="save"
            >
                <MarkdownEditor
                    v-model="form.body"
                    :error="form.errors.body"
                    @submit="save"
                />
                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        data-test="cancel-edit"
                        @click="editing = false"
                    >
                        {{ $t('Cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ $t('Save') }}
                    </Button>
                </div>
            </form>
            <MarkdownContent
                v-else
                data-test="post-body"
                :html="post.body_html"
            />
        </div>

        <footer
            class="flex flex-wrap items-center justify-between gap-2 border-t px-2 py-1.5"
        >
            <PostReactions
                :post-id="post.id"
                :reactions="post.reactions"
                :can-react="post.can.react"
            />
            <PostActions
                :post="post"
                :thread-slug="threadSlug"
                :can-manage-solution="canManageSolution"
                @quote="emit('quote', post)"
                @edit="edit"
            />
        </footer>
    </article>
</template>
