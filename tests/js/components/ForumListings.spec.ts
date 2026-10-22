import { mount } from '@vue/test-utils';
import CategoryCard from '@/components/forum/CategoryCard.vue';
import ThreadList from '@/components/forum/ThreadList.vue';
import type { CategoryWithLatestThread, ThreadSummary } from '@/types/forum';

const page = vi.hoisted(() => ({ props: { locale: 'en' } }));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    return {
        usePage: () => page,
        Link: defineComponent({
            props: { href: { type: String, required: true } },
            setup:
                (props, { slots, attrs }) =>
                () =>
                    h('a', { ...attrs, href: props.href }, slots.default?.()),
        }),
    };
});

function thread(id: number): ThreadSummary {
    return {
        id,
        title: `Hilo número ${id}`,
        slug: `hilo-numero-${id}`,
        status: 'published',
        is_pinned: false,
        is_locked: false,
        is_solved: false,
        is_unread: false,
        replies_count: 0,
        views_count: 0,
        created_at: '2026-10-22T10:00:00+00:00',
        last_posted_at: '2026-10-22T10:00:00+00:00',
        excerpt: null,
        author: null,
        last_poster: null,
        category: { id: 1, name: 'General', slug: 'general', color: '#6366f1' },
        tags: [],
    };
}

function category(
    overrides: Partial<CategoryWithLatestThread> = {},
): CategoryWithLatestThread {
    return {
        id: 1,
        name: 'Laravel',
        slug: 'laravel',
        description: 'Todo sobre el framework.',
        color: '#f97316',
        icon: 'code',
        is_locked: false,
        threads_count: 1520,
        posts_count: 8,
        latest_thread: {
            id: 4,
            title: 'Cómo testear colas',
            slug: 'como-testear-colas',
            last_posted_at: '2026-10-22T11:00:00+00:00',
            last_poster: {
                id: 2,
                name: 'Luis Pérez',
                username: 'luis',
                avatar_url: null,
                role: 'member',
            },
        },
        ...overrides,
    };
}

describe('ThreadList', () => {
    it('lists the threads in order', () => {
        const wrapper = mount(ThreadList, {
            props: { threads: [thread(1), thread(2)] },
        });

        expect(
            wrapper
                .findAll('[data-test="thread-link"]')
                .map((link) => link.text()),
        ).toEqual(['Hilo número 1', 'Hilo número 2']);
        expect(wrapper.find('[data-test="empty-threads"]').exists()).toBe(
            false,
        );
    });

    it('explains an empty listing', () => {
        const wrapper = mount(ThreadList, {
            props: { threads: [], emptyTitle: 'Nothing here yet' },
        });

        expect(wrapper.get('[data-test="empty-threads"]').text()).toContain(
            'Nothing here yet',
        );
    });

    it('can leave the category of every thread out', () => {
        const wrapper = mount(ThreadList, {
            props: { threads: [thread(1)], hideCategory: true },
        });

        expect(
            wrapper.findAll('a').map((a) => a.attributes('href')),
        ).not.toContain('/c/general');
    });
});

describe('CategoryCard', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-10-22T12:00:00Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('links the category and shows its counters', () => {
        const wrapper = mount(CategoryCard, {
            props: { category: category() },
        });

        expect(
            wrapper.get('[data-test="category-link"]').attributes('href'),
        ).toBe('/c/laravel');
        expect(wrapper.text()).toContain('Todo sobre el framework.');
        expect(wrapper.get('[data-test="threads-count"]').text()).toContain(
            '1.5K',
        );
        expect(wrapper.get('[data-test="posts-count"]').text()).toContain('8');
        expect(wrapper.find('[data-test="category-locked"]').exists()).toBe(
            false,
        );
    });

    it('shows the latest thread with its last poster', () => {
        const wrapper = mount(CategoryCard, {
            props: { category: category() },
        });

        const latest = wrapper.get('[data-test="latest-thread"]');

        expect(latest.get('a').attributes('href')).toBe(
            '/t/como-testear-colas',
        );
        expect(latest.text()).toContain('Cómo testear colas');
        expect(latest.text()).toContain('Luis Pérez');
        expect(latest.text()).toContain('1 hour ago');
    });

    it('says when a category has no threads yet', () => {
        const wrapper = mount(CategoryCard, {
            props: { category: category({ latest_thread: null }) },
        });

        expect(wrapper.find('[data-test="latest-thread"]').exists()).toBe(
            false,
        );
        expect(wrapper.text()).toContain('No threads yet');
    });

    it('marks locked categories', () => {
        const wrapper = mount(CategoryCard, {
            props: { category: category({ is_locked: true }) },
        });

        expect(
            wrapper
                .get('[data-test="category-locked"]')
                .attributes('aria-label'),
        ).toBe('Only staff can start threads here');
    });
});
