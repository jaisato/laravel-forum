/**
 * Shapes of the forum data the pages receive. They mirror the resources in
 * app/Http/Resources: change both together.
 */

export type Role = 'admin' | 'moderator' | 'member';

export type ContentStatus = 'published' | 'pending_review' | 'hidden';

export type ReactionType =
    'like' | 'love' | 'laugh' | 'insightful' | 'celebrate';

/**
 * Any member next to their content. A null author is an erased account.
 */
export type UserSummary = {
    id: number;
    name: string;
    username: string;
    avatar_url: string | null;
    role: Role;
};

/**
 * The signed in member (shared prop auth.user).
 */
export type AuthUser = UserSummary & {
    email: string;
    is_staff: boolean;
    is_banned: boolean;
    email_verified: boolean;
    unread_notifications: number;
    unread_conversations: number;
};

export type AiFeatures = {
    summaries: boolean;
    similar: boolean;
    tags: boolean;
};

export type Category = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    color: string;
    icon: string | null;
    is_locked: boolean;
    threads_count: number;
    posts_count: number;
};

export type CategorySummary = Pick<Category, 'id' | 'name' | 'slug' | 'color'>;

/**
 * The latest public thread of a category (home page cards).
 */
export type LatestThread = {
    id: number;
    title: string;
    slug: string;
    last_posted_at: string | null;
    last_poster: UserSummary | null;
};

export type CategoryWithLatestThread = Category & {
    latest_thread: LatestThread | null;
};

export type Tag = {
    id: number;
    name: string;
    slug: string;
    color: string | null;
    threads_count: number;
};

export type TagSummary = Pick<Tag, 'id' | 'name' | 'slug' | 'color'>;

/** A tag the tag pickers offer. */
export type TagOption = TagSummary;

/** A category the new thread form offers. */
export type CategoryOption = Pick<
    Category,
    'id' | 'name' | 'slug' | 'color' | 'is_locked'
>;

export type ThreadSummary = {
    id: number;
    title: string;
    slug: string;
    status: ContentStatus;
    is_pinned: boolean;
    is_locked: boolean;
    is_solved: boolean;
    is_unread: boolean;
    replies_count: number;
    views_count: number;
    created_at: string;
    last_posted_at: string | null;
    excerpt: string | null;
    author: UserSummary | null;
    last_poster: UserSummary | null;
    category: CategorySummary;
    tags: TagSummary[];
};

export type ThreadSort = 'activity' | 'newest' | 'replies' | 'views';

export type ThreadFilter =
    'all' | 'unanswered' | 'solved' | 'unsolved' | 'following' | 'mine';

export type ListingFilters = {
    sort: ThreadSort;
    filter: ThreadFilter;
};

/**
 * What a thread listing is narrowed to: nothing (all threads), a category
 * or a tag.
 */
export type ListingContext =
    { type: 'category'; category: Category } | { type: 'tag'; tag: Tag } | null;

export type ForumStats = {
    members: number;
    threads: number;
    posts: number;
    newest_member: UserSummary | null;
    online: UserSummary[];
    online_count: number;
};

/**
 * What the viewer may do with the thread on its page (prop `can`).
 */
/** A thread like the one being written, under the title of a new thread. */
export type SimilarThread = Pick<
    ThreadSummary,
    'id' | 'title' | 'slug' | 'replies_count' | 'is_solved'
>;

/** What the viewer may do from a listing. */
export type ListingAbilities = {
    create_thread: boolean;
};

export type ThreadAbilities = {
    reply: boolean;
    update: boolean;
    delete: boolean;
    restore: boolean;
    pin: boolean;
    lock: boolean;
    move: boolean;
    mark_solution: boolean;
    subscribe: boolean;
    /** "Summarize the thread", while AI summaries are on. */
    summarize: boolean;
};

export type AiSummary = {
    html: string;
    generated_at: string | null;
    /** Enough has happened since for it to be written again. */
    stale: boolean;
};

/**
 * The thread of its own page.
 */
export type Thread = ThreadSummary & {
    solution_post_id: number | null;
    is_deleted: boolean;
};

export type MoveTarget = {
    id: number;
    name: string;
};

export type PageMeta = {
    title: string;
    description: string;
};

export type ReactionSummary = {
    type: ReactionType;
    emoji: string;
    count: number;
    reacted: boolean;
};

export type PostAbilities = {
    update: boolean;
    /** Published and visible to the viewer, who may reply. */
    quote: boolean;
    delete: boolean;
    restore: boolean;
    react: boolean;
    report: boolean;
    hide: boolean;
    approve: boolean;
    mark_solution: boolean;
};

export type Post = {
    id: number;
    thread_id: number;
    body_html: string;
    /** The Markdown source, only for those who may edit or quote it. */
    body?: string;
    status: ContentStatus;
    is_first: boolean;
    is_solution: boolean;
    is_deleted: boolean;
    created_at: string;
    edited_at: string | null;
    author: UserSummary | null;
    reply_to: { id: number; author: UserSummary | null } | null;
    reactions: ReactionSummary[];
    can: PostAbilities;
};

export type PaginationLinks = {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
};

export type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    path: string;
    links: { url: string | null; label: string; active: boolean }[];
};

/**
 * A paginated Laravel resource collection.
 */
export type Paginated<T> = {
    data: T[];
    links: PaginationLinks;
    meta: PaginationMeta;
};

export type NotificationType =
    | 'thread_replied'
    | 'user_mentioned'
    | 'solution_accepted'
    | 'post_moderated'
    | 'new_private_message';

/**
 * A notification of the signed in member. One about a post they may no
 * longer see comes without its excerpt, actor and address.
 */
export type ForumNotification = {
    id: string;
    type: NotificationType;
    title: string;
    excerpt: string | null;
    /** A path on this forum; null when it no longer leads anywhere. */
    url: string | null;
    /** The post it is about, unless it is withheld. */
    post_id: number | null;
    actor: { username: string; avatar_url: string | null } | null;
    read_at: string | null;
    created_at: string;
};

/**
 * A member's public profile (page users/Show).
 */
export type UserProfile = UserSummary & {
    bio: string | null;
    location: string | null;
    /** Only web (http and https) addresses reach the page. */
    website: string | null;
    reputation: number;
    threads_count: number;
    posts_count: number;
    created_at: string;
    last_seen_at: string | null;
};

export type ProfileTab = 'threads' | 'posts';

/**
 * A post away from its thread page: a member's replies, a search result.
 */
export type PostSummary = {
    id: number;
    excerpt: string;
    status: ContentStatus;
    created_at: string;
    author: UserSummary | null;
    thread: { id: number; title: string; slug: string };
};

export type SearchType = 'threads' | 'posts';

/**
 * What the search page looks for, as the server understood it.
 */
export type SearchFilters = {
    q: string;
    type: SearchType;
    /** A category slug. */
    category: string | null;
    /** A tag slug. */
    tag: string | null;
};

/** A category or a tag to narrow a search to. */
export type SearchScopeOption = {
    name: string;
    slug: string;
};

export type Message = {
    id: number;
    body_html: string;
    created_at: string;
    author: UserSummary | null;
    is_own: boolean;
};

/**
 * A conversation in the member's inbox.
 */
export type Conversation = {
    id: number;
    subject: string;
    last_message_at: string | null;
    is_unread: boolean;
    /** The others still in it. */
    participants: UserSummary[];
    last_message: { excerpt: string; author: UserSummary | null } | null;
};

/**
 * A conversation on its own page.
 */
export type ConversationDetail = {
    id: number;
    subject: string;
    /** Everyone still in it, the viewer included. */
    participants: UserSummary[];
};

export type ConversationAbilities = {
    reply: boolean;
    leave: boolean;
};

/** What the viewer may do from a profile. */
export type ProfileAbilities = {
    message: boolean;
};
