<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, Clock, Globe, Mail, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import Pagination from '@/components/forum/Pagination.vue';
import PostSummaryList from '@/components/forum/PostSummaryList.vue';
import RelativeTime from '@/components/forum/RelativeTime.vue';
import RoleBadge from '@/components/forum/RoleBadge.vue';
import ThreadList from '@/components/forum/ThreadList.vue';
import UserAvatar from '@/components/forum/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { formatCount } from '@/lib/format';
import { create as createConversation } from '@/routes/conversations';
import { show } from '@/routes/users';
import type {
    PageMeta,
    Paginated,
    PostSummary,
    ProfileAbilities,
    ProfileTab,
    ThreadSummary,
    UserProfile,
} from '@/types/forum';

const props = defineProps<{
    profile: UserProfile;
    tab: ProfileTab;
    meta: PageMeta;
    can: ProfileAbilities;
    /** The threads tab. */
    threads?: Paginated<ThreadSummary>;
    /** The replies tab. */
    posts?: Paginated<PostSummary>;
}>();

const page = usePage();

const tabs: { value: ProfileTab; label: string }[] = [
    { value: 'threads', label: 'Threads' },
    { value: 'posts', label: 'Replies' },
];

function tabUrl(tab: ProfileTab): string {
    return show.url(
        props.profile.username,
        tab === 'threads' ? undefined : { query: { tab } },
    );
}

// Month and year in UTC, so the server and the browser agree on them.
const joined = computed(() =>
    new Intl.DateTimeFormat(page.props.locale, {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(props.profile.created_at)),
);

// The address without its scheme or a trailing slash, as people write it.
const websiteLabel = computed(() =>
    props.profile.website?.replace(/^https?:\/\//i, '').replace(/\/$/, ''),
);

const stats = computed(() => [
    {
        label: 'Reputation',
        value: props.profile.reputation,
        test: 'profile-reputation',
    },
    {
        label: 'Threads',
        value: props.profile.threads_count,
        test: 'profile-threads',
    },
    {
        label: 'Messages',
        value: props.profile.posts_count,
        test: 'profile-posts',
    },
]);
</script>

<template>
    <Head :title="meta.title">
        <meta
            head-key="description"
            name="description"
            :content="meta.description"
        />
    </Head>

    <section
        class="flex flex-col gap-5 rounded-xl border bg-card p-5 sm:flex-row sm:items-start"
    >
        <UserAvatar :user="profile" size="lg" />

        <div class="flex min-w-0 flex-1 flex-col gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1
                        class="text-2xl font-semibold tracking-tight break-words"
                    >
                        {{ profile.name }}
                    </h1>
                    <RoleBadge :role="profile.role" />
                </div>
                <p class="text-sm text-muted-foreground">
                    @{{ profile.username }}
                </p>
            </div>

            <div v-if="can.message">
                <Button variant="outline" size="sm" as-child>
                    <Link
                        :href="
                            createConversation.url({
                                query: { to: profile.username },
                            })
                        "
                        data-test="send-message"
                    >
                        <Mail aria-hidden="true" />
                        {{ $t('Send a message') }}
                    </Link>
                </Button>
            </div>

            <p
                v-if="profile.bio"
                data-test="profile-bio"
                class="text-sm break-words whitespace-pre-line"
            >
                {{ profile.bio }}
            </p>

            <ul
                class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground"
            >
                <li
                    v-if="profile.location"
                    class="inline-flex items-center gap-1.5"
                >
                    <MapPin class="size-4" aria-hidden="true" />
                    <span data-test="profile-location">{{
                        profile.location
                    }}</span>
                </li>
                <li
                    v-if="profile.website"
                    class="inline-flex min-w-0 items-center gap-1.5"
                >
                    <Globe class="size-4 shrink-0" aria-hidden="true" />
                    <a
                        :href="profile.website"
                        data-test="profile-website"
                        rel="nofollow ugc noopener noreferrer"
                        target="_blank"
                        class="truncate text-foreground hover:underline"
                        >{{ websiteLabel }}</a
                    >
                </li>
                <li class="inline-flex items-center gap-1.5">
                    <CalendarDays class="size-4" aria-hidden="true" />
                    <span data-test="profile-joined">{{
                        $t('Joined :date', { date: joined })
                    }}</span>
                </li>
                <li
                    v-if="profile.last_seen_at"
                    class="inline-flex items-center gap-1.5"
                    data-test="profile-seen"
                >
                    <Clock class="size-4" aria-hidden="true" />
                    <span>
                        {{ $t('Last seen') }}
                        <RelativeTime :datetime="profile.last_seen_at" />
                    </span>
                </li>
            </ul>
        </div>

        <dl
            class="grid grid-cols-3 gap-4 border-t pt-4 text-center sm:w-64 sm:shrink-0 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-5"
        >
            <div v-for="stat in stats" :key="stat.label" :data-test="stat.test">
                <dt class="text-xs text-muted-foreground">
                    {{ $t(stat.label) }}
                </dt>
                <dd class="text-lg font-semibold">
                    {{ formatCount(stat.value, page.props.locale) }}
                </dd>
            </div>
        </dl>
    </section>

    <nav :aria-label="$t('Profile sections')" class="flex gap-1 border-b">
        <Link
            v-for="option in tabs"
            :key="option.value"
            :href="tabUrl(option.value)"
            preserve-scroll
            data-test="profile-tab"
            class="-mb-px border-b-2 px-3 py-2 text-sm font-medium"
            :class="
                tab === option.value
                    ? 'border-primary text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
            :aria-current="tab === option.value ? 'page' : undefined"
            >{{ $t(option.label) }}</Link
        >
    </nav>

    <template v-if="tab === 'threads' && threads">
        <ThreadList :threads="threads.data" empty-title="No threads yet" />
        <Pagination :links="threads.links" :meta="threads.meta" />
    </template>

    <template v-else-if="tab === 'posts' && posts">
        <PostSummaryList
            :posts="posts.data"
            hide-author
            empty-title="No replies yet"
        />
        <Pagination :links="posts.links" :meta="posts.meta" />
    </template>
</template>
