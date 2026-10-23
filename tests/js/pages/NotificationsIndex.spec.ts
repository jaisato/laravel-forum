import { mount } from '@vue/test-utils';
import type { FunctionalComponent } from 'vue';
import NotificationsIndex from '@/pages/notifications/Index.vue';
import type { ForumNotification, Paginated } from '@/types/forum';

const page = vi.hoisted(() => ({
    props: {
        auth: {
            user: { unread_notifications: 2 } as {
                unread_notifications: number;
            },
        },
        locale: 'en',
    },
}));
const router = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    const Head: FunctionalComponent<{ title: string }> = () => h('div');
    Head.props = ['title'];

    return {
        router,
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

function notifications(
    data: ForumNotification[],
): Paginated<ForumNotification> {
    return {
        data,
        links: { first: null, last: null, prev: null, next: null },
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: data.length,
            from: data.length > 0 ? 1 : null,
            to: data.length > 0 ? data.length : null,
            path: '/notifications',
            links: [],
        },
    };
}

const reply: ForumNotification = {
    id: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
    type: 'thread_replied',
    title: 'Ana López replied to "Colas en Laravel"',
    excerpt: 'Prueba con esto',
    url: '/t/colas-en-laravel?post=3#post-3',
    post_id: 3,
    actor: { username: 'ana', avatar_url: null },
    read_at: null,
    created_at: '2026-10-22T10:00:00+00:00',
};

describe('notifications/Index', () => {
    beforeEach(() => {
        router.post.mockReset();
        page.props.auth.user.unread_notifications = 2;
    });

    it('lists the notifications', () => {
        const wrapper = mount(NotificationsIndex, {
            props: {
                notifications: notifications([
                    reply,
                    { ...reply, id: '1b9d6bcd-bbfd-4b2d-9b5d-ab8dfbbd4bed' },
                ]),
            },
        });

        expect(wrapper.findAll('[data-test="notification"]')).toHaveLength(2);
    });

    it('marks every notification read', async () => {
        const wrapper = mount(NotificationsIndex, {
            props: { notifications: notifications([reply]) },
        });

        await wrapper.get('[data-test="mark-all-read"]').trigger('click');

        expect(router.post).toHaveBeenCalledWith(
            '/notifications/read-all',
            {},
            { preserveScroll: true },
        );
    });

    it('offers nothing to mark when everything is read', () => {
        page.props.auth.user.unread_notifications = 0;

        const wrapper = mount(NotificationsIndex, {
            props: {
                notifications: notifications([
                    { ...reply, read_at: '2026-10-22T11:00:00+00:00' },
                ]),
            },
        });

        expect(wrapper.find('[data-test="mark-all-read"]').exists()).toBe(
            false,
        );
    });

    it('says when there are no notifications', () => {
        page.props.auth.user.unread_notifications = 0;

        const wrapper = mount(NotificationsIndex, {
            props: { notifications: notifications([]) },
        });

        expect(
            wrapper.get('[data-test="empty-notifications"]').text(),
        ).toContain('No notifications yet');
    });
});
