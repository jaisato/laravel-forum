import { mount } from '@vue/test-utils';
import PostSummaryItem from '@/components/forum/PostSummaryItem.vue';
import PostSummaryList from '@/components/forum/PostSummaryList.vue';
import type { PostSummary } from '@/types/forum';

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

function summary(overrides: Partial<PostSummary> = {}): PostSummary {
    return {
        id: 3,
        excerpt: 'Usa Queue::fake() en el test.',
        status: 'published',
        created_at: '2026-10-22T10:00:00+00:00',
        author: {
            id: 1,
            name: 'Ana García',
            username: 'ana',
            avatar_url: null,
            role: 'member',
        },
        thread: { id: 7, title: 'Colas en Laravel', slug: 'colas-en-laravel' },
        ...overrides,
    };
}

describe('PostSummaryItem', () => {
    it('links the excerpt to the post in its thread', () => {
        const wrapper = mount(PostSummaryItem, { props: { post: summary() } });

        const post = wrapper.get('[data-test="post-link"]');
        const thread = wrapper.get('[data-test="post-thread"]');

        expect(post.attributes('href')).toBe(
            '/t/colas-en-laravel?post=3#post-3',
        );
        expect(post.text()).toBe('Usa Queue::fake() en el test.');
        expect(thread.attributes('href')).toBe('/t/colas-en-laravel');
        expect(thread.text()).toBe('Colas en Laravel');
    });

    it('links the author to their profile, unless the list is theirs', () => {
        const wrapper = mount(PostSummaryItem, { props: { post: summary() } });

        expect(
            wrapper.get('[data-test="post-author"]').attributes('href'),
        ).toBe('/u/ana');
        expect(
            mount(PostSummaryItem, {
                props: { post: summary(), hideAuthor: true },
            })
                .find('[data-test="post-author"]')
                .exists(),
        ).toBe(false);
    });

    it('credits erased accounts to «Deleted user»', () => {
        const wrapper = mount(PostSummaryItem, {
            props: { post: summary({ author: null }) },
        });

        expect(wrapper.find('a[data-test="post-author"]').exists()).toBe(false);
        expect(wrapper.get('[data-test="post-author"]').text()).toBe(
            'Deleted user',
        );
    });

    it('flags posts the public cannot see', () => {
        const wrapper = mount(PostSummaryItem, {
            props: { post: summary({ status: 'pending_review' }) },
        });

        expect(wrapper.get('[data-test="post-status"]').text()).toBe(
            'Pending review',
        );
        expect(
            mount(PostSummaryItem, { props: { post: summary() } })
                .find('[data-test="post-status"]')
                .exists(),
        ).toBe(false);
    });
});

describe('PostSummaryList', () => {
    it('lists the posts', () => {
        const wrapper = mount(PostSummaryList, {
            props: { posts: [summary(), summary({ id: 4 })] },
        });

        expect(wrapper.findAll('[data-test="post-summary"]')).toHaveLength(2);
    });

    it('says when there is nothing to list', () => {
        const wrapper = mount(PostSummaryList, {
            props: { posts: [], emptyTitle: 'No replies yet' },
        });

        expect(wrapper.get('[data-test="empty-posts"]').text()).toContain(
            'No replies yet',
        );
    });
});
