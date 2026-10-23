import { mount } from '@vue/test-utils';
import NotificationBell from '@/components/forum/NotificationBell.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    return {
        Link: defineComponent({
            props: { href: { type: String, required: true } },
            setup:
                (props, { slots, attrs }) =>
                () =>
                    h('a', { ...attrs, href: props.href }, slots.default?.()),
        }),
    };
});

describe('NotificationBell', () => {
    it('shows how many notifications are unread', () => {
        const wrapper = mount(NotificationBell, {
            props: { href: '/notifications', count: 3 },
        });

        expect(wrapper.get('[data-test="unread-count"]').text()).toBe('3');
        expect(wrapper.get('a').attributes('aria-label')).toBe(
            'Notifications (3 unread)',
        );
        expect(wrapper.get('a').attributes('href')).toBe('/notifications');
    });

    it('caps large numbers', () => {
        const wrapper = mount(NotificationBell, {
            props: { href: '/notifications', count: 120 },
        });

        expect(wrapper.get('[data-test="unread-count"]').text()).toBe('99+');
    });

    it('shows no badge when everything is read', () => {
        const wrapper = mount(NotificationBell, {
            props: { href: '/notifications', count: 0 },
        });

        expect(wrapper.find('[data-test="unread-count"]').exists()).toBe(false);
        expect(wrapper.get('a').attributes('aria-label')).toBe('Notifications');
    });
});
