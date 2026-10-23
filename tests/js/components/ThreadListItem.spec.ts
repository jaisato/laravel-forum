import { mount } from '@vue/test-utils';
import ThreadListItem from '@/components/forum/ThreadListItem.vue';
import type { ThreadSummary } from '@/types/forum';

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

function summary(overrides: Partial<ThreadSummary> = {}): ThreadSummary {
    return {
        id: 7,
        title: 'Mis colas no procesan los jobs retrasados',
        slug: 'mis-colas-no-procesan-los-jobs-retrasados',
        status: 'published',
        is_pinned: false,
        is_locked: false,
        is_solved: false,
        is_unread: false,
        replies_count: 0,
        views_count: 3,
        created_at: '2026-10-22T10:00:00+00:00',
        last_posted_at: '2026-10-22T10:00:00+00:00',
        excerpt: 'Tengo un job con delay que nunca se ejecuta.',
        author: {
            id: 1,
            name: 'Ana García',
            username: 'ana',
            avatar_url: null,
            role: 'member',
        },
        last_poster: {
            id: 1,
            name: 'Ana García',
            username: 'ana',
            avatar_url: null,
            role: 'member',
        },
        category: { id: 2, name: 'Laravel', slug: 'laravel', color: '#f97316' },
        tags: [{ id: 3, name: 'colas', slug: 'colas', color: null }],
        ...overrides,
    };
}

describe('ThreadListItem', () => {
    beforeEach(() => {
        page.props.locale = 'en';
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-10-22T12:00:00Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('links the title to the thread and shows its excerpt', () => {
        const wrapper = mount(ThreadListItem, {
            props: { thread: summary() },
        });

        const link = wrapper.get('[data-test="thread-link"]');

        expect(link.attributes('href')).toBe(
            '/t/mis-colas-no-procesan-los-jobs-retrasados',
        );
        expect(link.text()).toBe('Mis colas no procesan los jobs retrasados');
        expect(wrapper.get('[data-test="thread-excerpt"]').text()).toBe(
            'Tengo un job con delay que nunca se ejecuta.',
        );
        expect(wrapper.find('[data-test="unread-badge"]').exists()).toBe(false);
    });

    it('marks unread threads and sends members to the first unread post', () => {
        const wrapper = mount(ThreadListItem, {
            props: { thread: summary({ is_unread: true }) },
        });

        expect(wrapper.get('[data-test="unread-badge"]').text()).toBe('New');
        expect(
            wrapper.get('[data-test="thread-link"]').attributes('href'),
        ).toBe('/t/mis-colas-no-procesan-los-jobs-retrasados?unread=1');
    });

    it('shows the state of the thread', () => {
        const wrapper = mount(ThreadListItem, {
            props: {
                thread: summary({
                    is_pinned: true,
                    is_locked: true,
                    is_solved: true,
                }),
            },
        });

        const states = wrapper
            .findAll('[data-test="thread-state"]')
            .map((state) => state.attributes('aria-label'));

        expect(states).toEqual(['Pinned', 'Locked', 'Solved']);
    });

    it('warns staff and authors about threads the public cannot see', () => {
        const hidden = mount(ThreadListItem, {
            props: { thread: summary({ status: 'hidden' }) },
        });
        const pending = mount(ThreadListItem, {
            props: { thread: summary({ status: 'pending_review' }) },
        });
        const published = mount(ThreadListItem, {
            props: { thread: summary() },
        });

        expect(hidden.get('[data-test="thread-status"]').text()).toBe('Hidden');
        expect(pending.get('[data-test="thread-status"]').text()).toBe(
            'Pending review',
        );
        expect(published.find('[data-test="thread-status"]').exists()).toBe(
            false,
        );
    });

    it('credits the author and the start of an unanswered thread', () => {
        const wrapper = mount(ThreadListItem, {
            props: { thread: summary() },
        });

        const activity = wrapper.get('[data-test="thread-activity"]');

        expect(activity.text()).toContain('Ana García');
        expect(activity.text()).toContain('2 hours ago');
        expect(activity.text()).not.toContain('Last reply');
    });

    it('links the author to their profile', () => {
        const wrapper = mount(ThreadListItem, {
            props: { thread: summary() },
        });

        expect(
            wrapper.get('[data-test="thread-author"]').attributes('href'),
        ).toBe('/u/ana');
    });

    it('credits the last reply of an answered thread', () => {
        const wrapper = mount(ThreadListItem, {
            props: {
                thread: summary({
                    replies_count: 4,
                    last_posted_at: '2026-10-22T11:55:00+00:00',
                    last_poster: {
                        id: 9,
                        name: 'Luis Pérez',
                        username: 'luis',
                        avatar_url: null,
                        role: 'moderator',
                    },
                }),
            },
        });

        const activity = wrapper.get('[data-test="thread-activity"]');

        expect(activity.text()).toContain('Last reply by Luis Pérez');
        expect(activity.text()).toContain('5 minutes ago');
    });

    it('credits erased accounts as deleted users', () => {
        const wrapper = mount(ThreadListItem, {
            props: {
                thread: summary({
                    replies_count: 1,
                    author: null,
                    last_poster: null,
                }),
            },
        });

        const activity = wrapper.get('[data-test="thread-activity"]');

        expect(activity.text()).toContain('Deleted user');
        expect(activity.text()).toContain('Last reply by Deleted user');
        expect(wrapper.find('a[data-test="thread-author"]').exists()).toBe(
            false,
        );
    });

    it('links the category and the tags', () => {
        const wrapper = mount(ThreadListItem, {
            props: { thread: summary() },
        });

        const hrefs = wrapper.findAll('a').map((a) => a.attributes('href'));

        expect(hrefs).toContain('/c/laravel');
        expect(hrefs).toContain('/tags/colas');
    });

    it('leaves the category out inside its own page', () => {
        const wrapper = mount(ThreadListItem, {
            props: { thread: summary(), hideCategory: true },
        });

        const hrefs = wrapper.findAll('a').map((a) => a.attributes('href'));

        expect(hrefs).not.toContain('/c/laravel');
        expect(hrefs).toContain('/tags/colas');
    });

    it('abbreviates large counters in the reader language', () => {
        const wrapper = mount(ThreadListItem, {
            props: {
                thread: summary({ replies_count: 12, views_count: 1234 }),
            },
        });

        expect(wrapper.get('[data-test="replies-count"]').text()).toContain(
            '12',
        );
        expect(wrapper.get('[data-test="views-count"]').text()).toContain(
            '1.2K',
        );

        page.props.locale = 'es';

        const spanish = mount(ThreadListItem, {
            props: { thread: summary({ views_count: 25000 }) },
        });

        expect(spanish.get('[data-test="views-count"]').text()).toContain(
            '25\u00a0mil',
        );
    });
});
