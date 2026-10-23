import { flushPromises, mount } from '@vue/test-utils';
import type { FunctionalComponent } from 'vue';
import ThreadShow from '@/pages/threads/Show.vue';
import type { AuthUser, Post, Thread, ThreadAbilities } from '@/types/forum';

const page = vi.hoisted(() => ({
    props: { auth: { user: null as unknown }, locale: 'en' },
}));

vi.mock('@inertiajs/vue3', async () => {
    const { fakeUseForm } = await import('../support/fakeForm');
    const { defineComponent, h } = await import('vue');

    // The head tags render in place, where the test can read them.
    const Head: FunctionalComponent<{ title: string }> = (props, { slots }) =>
        h(
            'div',
            { 'data-test': 'head', title: props.title },
            slots.default?.(),
        );
    Head.props = ['title'];

    return {
        router: { post: vi.fn(), delete: vi.fn(), visit: vi.fn() },
        useForm: fakeUseForm,
        useHttp: () => ({ body: '', post: vi.fn() }),
        usePage: () => page,
        Head,
        Link: defineComponent({
            props: { href: { type: String, required: true } },
            setup:
                (props, { slots, attrs }) =>
                () =>
                    h('a', { ...attrs, href: props.href }, slots.default?.()),
        }),
    };
});

const member = (overrides: Partial<AuthUser> = {}): AuthUser => ({
    id: 7,
    name: 'Ana Gómez',
    username: 'ana',
    avatar_url: null,
    role: 'member',
    email: 'ana@example.com',
    is_staff: false,
    is_banned: false,
    email_verified: true,
    unread_notifications: 0,
    unread_conversations: 0,
    ...overrides,
});

const thread = (overrides: Partial<Thread> = {}): Thread => ({
    id: 4,
    title: '¿Cómo testear colas?',
    slug: 'como-testear-colas',
    status: 'published',
    is_pinned: false,
    is_locked: false,
    is_solved: false,
    is_unread: false,
    replies_count: 1,
    views_count: 12,
    created_at: '2026-10-22T09:00:00+00:00',
    last_posted_at: '2026-10-22T10:00:00+00:00',
    excerpt: null,
    author: null,
    last_poster: null,
    category: { id: 1, name: 'Laravel', slug: 'laravel', color: '#f00' },
    tags: [],
    solution_post_id: null,
    is_deleted: false,
    ...overrides,
});

const post = (
    id: number,
    body: string,
    can: Partial<Post['can']> = {},
): Post => ({
    id,
    thread_id: 4,
    body_html: `<p>${body}</p>`,
    body,
    status: 'published',
    is_first: id === 1,
    is_solution: false,
    is_deleted: false,
    created_at: '2026-10-22T09:00:00+00:00',
    edited_at: null,
    author: null,
    reply_to: null,
    reactions: [],
    can: {
        update: false,
        quote: false,
        delete: false,
        restore: false,
        react: false,
        report: false,
        hide: false,
        approve: false,
        mark_solution: false,
        ...can,
    },
});

const nothing: ThreadAbilities = {
    reply: false,
    update: false,
    delete: false,
    restore: false,
    pin: false,
    lock: false,
    move: false,
    mark_solution: false,
    subscribe: false,
};

function show(
    props: Record<string, unknown> = {},
    posts: Post[] = [post(1, 'Primer mensaje'), post(2, 'Una respuesta')],
) {
    return mount(ThreadShow, {
        props: {
            thread: thread(),
            posts: {
                data: posts,
                links: { first: null, last: null, prev: null, next: null },
                meta: {
                    current_page: 1,
                    last_page: 1,
                    per_page: 20,
                    total: 2,
                    from: 1,
                    to: 2,
                    path: '/t/como-testear-colas',
                    links: [],
                },
            },
            focus: null,
            can: nothing,
            subscribed: false,
            meta: {
                title: '¿Cómo testear colas?',
                description: 'Primer mensaje',
            },
            ...props,
        },
        global: { stubs: { ReportDialog: true, MoveThreadDialog: true } },
        attachTo: document.body,
    });
}

beforeEach(() => {
    page.props.auth.user = null;
    window.localStorage.clear();
    document.body.innerHTML = '';
});

describe('threads/Show', () => {
    it('shows the thread with its title and description for the head', () => {
        const wrapper = show();

        expect(wrapper.get('[data-test="head"]').attributes('title')).toBe(
            '¿Cómo testear colas?',
        );
        expect(
            wrapper.get('meta[name="description"]').attributes('content'),
        ).toBe('Primer mensaje');
        expect(wrapper.get('h1').text()).toBe('¿Cómo testear colas?');
        expect(
            wrapper.findAll('article').map((card) => card.attributes('id')),
        ).toEqual(['post-1', 'post-2']);
        expect(wrapper.find('[data-test="ai-summary"]').exists()).toBe(false);
    });

    it('shows the summary once it arrives', () => {
        const wrapper = show({
            summary: { html: '<p>Usa Queue::fake().</p>', generated_at: null },
        });

        expect(wrapper.get('[data-test="ai-summary"]').html()).toContain(
            'Usa Queue::fake().',
        );
    });

    it('lets members reply and quote a post', async () => {
        page.props.auth.user = member();
        const wrapper = show({ can: { ...nothing, reply: true } }, [
            post(1, 'Primer mensaje', { quote: true }),
            post(2, 'Una respuesta', { quote: true }),
        ]);

        expect(wrapper.find('[data-test="reply-blocker"]').exists()).toBe(
            false,
        );

        await wrapper
            .findAll('[data-test="post-action-quote"]')[1]
            .trigger('click');

        expect(wrapper.get('#reply-body').element).toHaveProperty(
            'value',
            '> Una respuesta\n\n',
        );
    });

    it.each([
        ['a guest', null, {}, 'Log in or sign up to join the conversation.'],
        [
            'an unverified member',
            member({ email_verified: false }),
            {},
            'Verify your email address to take part.',
        ],
        [
            'a banned member',
            member({ is_banned: true }),
            {},
            'Your account is suspended, so you cannot post right now.',
        ],
        [
            'a member in a locked thread',
            member(),
            { is_locked: true },
            'This thread is locked: only the staff can reply.',
        ],
        [
            'staff in a deleted thread',
            member({ role: 'moderator', is_staff: true }),
            { is_deleted: true },
            'This thread is deleted.',
        ],
        [
            'a member in a locked category',
            member(),
            {},
            'Replies are closed in this thread.',
        ],
    ])('tells %s why they cannot reply', (_, user, state, message) => {
        page.props.auth.user = user;
        const wrapper = show({ thread: thread(state) });

        expect(wrapper.find('#reply-body').exists()).toBe(false);
        expect(wrapper.get('[data-test="reply-blocker"]').text()).toContain(
            message,
        );
    });

    it('scrolls to the focused post, also after a reply', async () => {
        vi.useFakeTimers();
        const scrolled: string[] = [];
        vi.spyOn(Element.prototype, 'scrollIntoView').mockImplementation(
            function (this: Element) {
                scrolled.push(this.id);
            },
        );

        const wrapper = show({ focus: 2 });
        vi.runOnlyPendingTimers();

        expect(scrolled).toEqual(['post-2']);
        expect(wrapper.get('#post-2').attributes('data-focused')).toBe('true');

        await wrapper.setProps({
            focus: 1,
        });
        await flushPromises();
        vi.runOnlyPendingTimers();

        expect(scrolled).toEqual(['post-2', 'post-1']);

        vi.useRealTimers();
    });
});
