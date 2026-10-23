import { mount } from '@vue/test-utils';
import NotificationItem from '@/components/forum/NotificationItem.vue';
import type { ForumNotification } from '@/types/forum';

const router = vi.hoisted(() => ({ post: vi.fn() }));
const page = vi.hoisted(() => ({ props: { locale: 'en' } }));

vi.mock('@inertiajs/vue3', () => ({ router, usePage: () => page }));

const ID = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';

function notification(
    overrides: Partial<ForumNotification> = {},
): ForumNotification {
    return {
        id: ID,
        type: 'thread_replied',
        title: 'Ana López replied to "Colas en Laravel"',
        excerpt: 'Prueba con esto',
        url: '/t/colas-en-laravel?post=3#post-3',
        post_id: 3,
        actor: { username: 'ana', avatar_url: null },
        read_at: null,
        created_at: '2026-10-22T10:00:00+00:00',
        ...overrides,
    };
}

describe('NotificationItem', () => {
    beforeEach(() => {
        router.post.mockReset();
    });

    it('links to where the notification leads and says what happened', () => {
        const wrapper = mount(NotificationItem, {
            props: { notification: notification() },
        });

        expect(
            wrapper.get('a[data-test="notification"]').attributes('href'),
        ).toBe('/t/colas-en-laravel?post=3#post-3');
        expect(wrapper.get('[data-test="notification-title"]').text()).toBe(
            'Ana López replied to "Colas en Laravel"',
        );
        expect(wrapper.get('[data-test="notification-excerpt"]').text()).toBe(
            'Prueba con esto',
        );
    });

    it('sets unread notifications apart', () => {
        const unread = mount(NotificationItem, {
            props: { notification: notification() },
        });
        const read = mount(NotificationItem, {
            props: {
                notification: notification({
                    read_at: '2026-10-22T11:00:00+00:00',
                }),
            },
        });

        expect(
            unread.get('[data-test="notification"]').attributes('data-unread'),
        ).toBe('true');
        expect(unread.text()).toContain('Unread');
        expect(
            read.get('[data-test="notification"]').attributes('data-unread'),
        ).toBeUndefined();
    });

    it('marks the notification read on the way to it', async () => {
        const wrapper = mount(NotificationItem, {
            props: { notification: notification() },
        });

        await wrapper.get('[data-test="notification"]').trigger('click');

        expect(router.post).toHaveBeenCalledWith(`/notifications/${ID}/read`);
    });

    it('leaves opening it in another tab to the browser', async () => {
        const wrapper = mount(NotificationItem, {
            props: { notification: notification() },
        });

        await wrapper
            .get('[data-test="notification"]')
            .trigger('click', { ctrlKey: true });
        await wrapper
            .get('[data-test="notification"]')
            .trigger('click', { metaKey: true });

        expect(router.post).not.toHaveBeenCalled();
    });

    it('only marks read a notification that no longer leads anywhere', async () => {
        const wrapper = mount(NotificationItem, {
            props: {
                notification: notification({
                    title: 'This content is no longer available.',
                    excerpt: null,
                    url: null,
                    actor: null,
                }),
            },
        });

        expect(wrapper.find('a').exists()).toBe(false);
        expect(
            wrapper.find('[data-test="notification-excerpt"]').exists(),
        ).toBe(false);

        await wrapper.get('button[data-test="notification"]').trigger('click');

        expect(router.post).toHaveBeenCalledWith(`/notifications/${ID}/read`);
    });
});
