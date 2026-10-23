import { mount } from '@vue/test-utils';
import type { FunctionalComponent } from 'vue';
import ThreadIndex from '@/pages/threads/Index.vue';
import type { ListingContext } from '@/types/forum';

const page = vi.hoisted(() => ({
    props: { auth: { user: null as unknown }, locale: 'es' },
}));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    const Head: FunctionalComponent<{ title: string }> = () => h('div');
    Head.props = ['title'];

    return {
        router: { visit: vi.fn() },
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

const category = {
    id: 1,
    name: 'Laravel',
    slug: 'laravel',
    description: null,
    color: '#ef4444',
    icon: null,
    is_locked: false,
    threads_count: 0,
    posts_count: 0,
};

function listing(createThread: boolean, context: ListingContext = null) {
    return mount(ThreadIndex, {
        props: {
            threads: {
                data: [],
                links: { first: null, last: null, prev: null, next: null },
                meta: {
                    current_page: 1,
                    last_page: 1,
                    per_page: 20,
                    total: 0,
                    from: null,
                    to: null,
                    path: '/threads',
                    links: [],
                },
            },
            filters: { sort: 'activity', filter: 'all' },
            context,
            can: { create_thread: createThread },
        },
        global: { stubs: { ThreadList: true } },
    });
}

beforeEach(() => {
    page.props.auth.user = null;
});

describe('threads/Index', () => {
    it('offers members who may post a new thread', () => {
        page.props.auth.user = { id: 7 };

        expect(
            listing(true).get('[data-test="new-thread"]').attributes('href'),
        ).toBe('/threads/create');
    });

    it('starts the thread in the category being listed', () => {
        page.props.auth.user = { id: 7 };

        expect(
            listing(true, { type: 'category', category })
                .get('[data-test="new-thread"]')
                .attributes('href'),
        ).toBe('/threads/create?category=laravel');
    });

    it('invites guests to start one, which asks them to log in', () => {
        expect(listing(false).find('[data-test="new-thread"]').exists()).toBe(
            true,
        );
    });

    it('hides it from members who may not start threads there', () => {
        page.props.auth.user = { id: 7 };

        expect(listing(false).find('[data-test="new-thread"]').exists()).toBe(
            false,
        );
    });
});
