<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Eye, MessageSquare, Pencil, Sparkles } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import CategoryBadge from '@/components/forum/CategoryBadge.vue';
import EditThreadDialog from '@/components/forum/EditThreadDialog.vue';
import EmptyState from '@/components/forum/EmptyState.vue';
import MarkdownContent from '@/components/forum/MarkdownContent.vue';
import Pagination from '@/components/forum/Pagination.vue';
import PostCard from '@/components/forum/PostCard.vue';
import RelativeTime from '@/components/forum/RelativeTime.vue';
import ReplyForm from '@/components/forum/ReplyForm.vue';
import SubscriptionButton from '@/components/forum/SubscriptionButton.vue';
import TagBadge from '@/components/forum/TagBadge.vue';
import ThreadModerationMenu from '@/components/forum/ThreadModerationMenu.vue';
import ThreadStatusBadges from '@/components/forum/ThreadStatusBadges.vue';
import UserAvatar from '@/components/forum/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { formatCount } from '@/lib/format';
import { login, register } from '@/routes';
import { show as showCategory } from '@/routes/categories';
import { show as showTag } from '@/routes/tags';
import { show as showUser } from '@/routes/users';
import { notice as verificationNotice } from '@/routes/verification';
import type {
    AiSummary,
    MoveTarget,
    PageMeta,
    Paginated,
    Post,
    TagOption,
    Thread,
    ThreadAbilities,
} from '@/types/forum';

const props = defineProps<{
    thread: Thread;
    posts: Paginated<Post>;
    /** The post the page was opened at: ?post=, ?unread=1 or a new reply. */
    focus: number | null;
    can: ThreadAbilities;
    subscribed: boolean;
    meta: PageMeta;
    /** Only for those who may move the thread. */
    moveTargets?: MoveTarget[];
    /** Only for those who may edit the thread. */
    tagOptions?: TagOption[];
    /** Deferred; null while AI summaries are off or none was generated. */
    summary?: AiSummary | null;
}>();

const page = usePage();
const replyForm = ref<InstanceType<typeof ReplyForm> | null>(null);
// Mounted while open, so it always starts from the thread as it is now.
const editing = ref(false);

const count = (value: number) => formatCount(value, page.props.locale);

/**
 * Why the viewer cannot reply, so the page says what to do about it.
 */
const replyBlocker = computed(() => {
    const user = page.props.auth.user;

    if (props.can.reply) {
        return null;
    }

    if (user === null) {
        return 'guest';
    }

    if (props.thread.is_deleted) {
        return 'deleted';
    }

    if (!user.email_verified) {
        return 'unverified';
    }

    if (user.is_banned) {
        return 'banned';
    }

    // A locked category or a thread awaiting review fall in "closed".
    return props.thread.is_locked ? 'locked' : 'closed';
});

const blockerMessages = {
    deleted: 'This thread is deleted.',
    banned: 'Your account is suspended, so you cannot post right now.',
    locked: 'This thread is locked: only the staff can reply.',
    closed: 'Replies are closed in this thread.',
};

function quote(post: Post): void {
    replyForm.value?.quote(post);
}

/**
 * Brings the focused post into view. Inertia scrolls to the top once a
 * visit has mounted the page, so this waits for it.
 */
function scrollToFocus(): void {
    if (props.focus === null) {
        return;
    }

    const id = `post-${props.focus}`;

    window.setTimeout(() =>
        document.getElementById(id)?.scrollIntoView({ block: 'start' }),
    );
}

onMounted(scrollToFocus);

// A reply redirects back here focused on it (the page state is kept).
watch(() => props.focus, scrollToFocus);
</script>

<template>
    <Head :title="meta.title">
        <meta
            head-key="description"
            name="description"
            :content="meta.description"
        />
    </Head>

    <header class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <CategoryBadge
                :category="thread.category"
                :href="showCategory.url(thread.category.slug)"
            />
            <TagBadge
                v-for="tag in thread.tags"
                :key="tag.id"
                :tag="tag"
                :href="showTag.url(tag.slug)"
            />
        </div>

        <h1 class="text-2xl font-semibold tracking-tight break-words">
            {{ thread.title }}
        </h1>

        <ThreadStatusBadges :thread="thread" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p
                class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground"
            >
                <UserAvatar :user="thread.author" size="sm" />
                <Link
                    v-if="thread.author"
                    :href="showUser.url(thread.author.username)"
                    data-test="thread-author"
                    class="text-foreground hover:underline"
                    >{{ thread.author.name }}</Link
                >
                <template v-else>{{ $t('Deleted user') }}</template>
                <span aria-hidden="true">·</span>
                <RelativeTime :datetime="thread.created_at" />
                <span aria-hidden="true">·</span>
                <span
                    class="inline-flex items-center gap-1"
                    data-test="replies-count"
                    :title="$t('Replies')"
                >
                    <MessageSquare class="size-3.5" aria-hidden="true" />
                    {{ count(thread.replies_count) }}
                    <span class="sr-only">{{ $t('Replies') }}</span>
                </span>
                <span
                    class="inline-flex items-center gap-1"
                    data-test="views-count"
                    :title="$t('Views')"
                >
                    <Eye class="size-3.5" aria-hidden="true" />
                    {{ count(thread.views_count) }}
                    <span class="sr-only">{{ $t('Views') }}</span>
                </span>
            </p>

            <div class="flex items-center gap-2">
                <Button
                    v-if="can.update && tagOptions"
                    data-test="edit-thread"
                    variant="outline"
                    size="sm"
                    @click="editing = true"
                >
                    <Pencil aria-hidden="true" />
                    {{ $t('Edit') }}
                </Button>
                <SubscriptionButton
                    v-if="can.subscribe"
                    :thread-slug="thread.slug"
                    :subscribed="subscribed"
                />
            </div>
        </div>

        <EditThreadDialog
            v-if="editing && tagOptions"
            v-model:open="editing"
            :thread="thread"
            :tag-options="tagOptions"
        />

        <ThreadModerationMenu
            :thread="thread"
            :can="can"
            :move-targets="moveTargets ?? []"
        />
    </header>

    <section
        v-if="summary"
        data-test="ai-summary"
        aria-labelledby="summary-heading"
        class="rounded-xl border bg-muted/40 px-4 py-3"
    >
        <h2
            id="summary-heading"
            class="flex items-center gap-2 text-sm font-semibold"
        >
            <Sparkles class="size-4" aria-hidden="true" />
            {{ $t('Summary of the thread') }}
        </h2>
        <MarkdownContent :html="summary.html" class="mt-2 text-sm" />
        <p class="mt-2 text-xs text-muted-foreground">
            {{ $t('Written by an AI: it can make mistakes.') }}
        </p>
    </section>

    <section :aria-label="$t('Posts')" class="flex flex-col gap-4">
        <PostCard
            v-for="post in posts.data"
            :key="post.id"
            :post="post"
            :thread-slug="thread.slug"
            :can-manage-solution="can.mark_solution"
            :focused="post.id === focus"
            @quote="quote"
        />
        <EmptyState
            v-if="posts.data.length === 0"
            :title="$t('There is nothing on this page.')"
        />
    </section>

    <Pagination :links="posts.links" :meta="posts.meta" />

    <section
        aria-labelledby="reply-heading"
        data-test="reply-section"
        class="flex flex-col gap-3 rounded-xl border bg-card p-4"
    >
        <h2 id="reply-heading" class="text-base font-semibold">
            {{ $t('Reply') }}
        </h2>

        <ReplyForm v-if="can.reply" ref="replyForm" :thread="thread" />

        <div
            v-else-if="replyBlocker === 'guest'"
            data-test="reply-blocker"
            class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
        >
            {{ $t('Log in or sign up to join the conversation.') }}
            <div class="flex gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="login()">{{ $t('Log in') }}</Link>
                </Button>
                <Button size="sm" as-child>
                    <Link :href="register()">{{ $t('Sign up') }}</Link>
                </Button>
            </div>
        </div>

        <div
            v-else-if="replyBlocker === 'unverified'"
            data-test="reply-blocker"
            class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
        >
            {{ $t('Verify your email address to take part.') }}
            <Button variant="outline" size="sm" as-child>
                <Link :href="verificationNotice()">{{
                    $t('Verify email')
                }}</Link>
            </Button>
        </div>

        <p
            v-else-if="replyBlocker"
            data-test="reply-blocker"
            class="text-sm text-muted-foreground"
        >
            {{ $t(blockerMessages[replyBlocker]) }}
        </p>
    </section>
</template>
