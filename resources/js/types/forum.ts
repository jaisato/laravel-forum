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

export type Tag = {
    id: number;
    name: string;
    slug: string;
    color: string | null;
    threads_count: number;
};

export type TagSummary = Pick<Tag, 'id' | 'name' | 'slug' | 'color'>;

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

export type ThreadAbilities = {
    reply: boolean;
    update: boolean;
    delete: boolean;
    pin: boolean;
    lock: boolean;
    move: boolean;
    markSolution: boolean;
    subscribe: boolean;
    summarize: boolean;
};

export type AiSummary = {
    html: string;
    generated_at: string;
};

export type Thread = ThreadSummary & {
    solution_post_id: number | null;
    is_subscribed: boolean;
    ai_summary: AiSummary | null;
    can: ThreadAbilities;
};

export type ReactionSummary = {
    type: ReactionType;
    emoji: string;
    count: number;
    reacted: boolean;
};

export type PostAbilities = {
    update: boolean;
    delete: boolean;
    restore: boolean;
    react: boolean;
    report: boolean;
    hide: boolean;
    approve: boolean;
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

export type ForumNotification = {
    id: string;
    type: NotificationType;
    title: string;
    excerpt: string | null;
    url: string;
    actor: { username: string; avatar_url: string | null } | null;
    read_at: string | null;
    created_at: string;
};

export type Message = {
    id: number;
    body_html: string;
    created_at: string;
    author: UserSummary | null;
    is_own: boolean;
};

export type Conversation = {
    id: number;
    subject: string;
    last_message_at: string | null;
    is_unread: boolean;
    participants: UserSummary[];
};
