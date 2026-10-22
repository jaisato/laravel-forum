import { mount } from '@vue/test-utils';
import CategoryBadge from '@/components/forum/CategoryBadge.vue';
import EmptyState from '@/components/forum/EmptyState.vue';
import RoleBadge from '@/components/forum/RoleBadge.vue';
import TagBadge from '@/components/forum/TagBadge.vue';
import UserAvatar from '@/components/forum/UserAvatar.vue';

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

describe('UserAvatar', () => {
    it('shows the initials of members without a picture', () => {
        const wrapper = mount(UserAvatar, {
            props: { user: { name: 'Ana García', avatar_url: null } },
        });

        expect(wrapper.text()).toBe('AG');
    });

    it('shows a neutral icon for erased accounts', () => {
        const wrapper = mount(UserAvatar, { props: { user: null } });

        expect(wrapper.get('[role="img"]').attributes('aria-label')).toBe(
            'Deleted user',
        );
    });
});

describe('RoleBadge', () => {
    it('names staff roles', () => {
        expect(mount(RoleBadge, { props: { role: 'admin' } }).text()).toBe(
            'Administrator',
        );
        expect(mount(RoleBadge, { props: { role: 'moderator' } }).text()).toBe(
            'Moderator',
        );
    });

    it('stays out of the way for members', () => {
        expect(mount(RoleBadge, { props: { role: 'member' } }).html()).toBe(
            '<!--v-if-->',
        );
    });
});

describe('CategoryBadge and TagBadge', () => {
    it('link when given an address', () => {
        const category = mount(CategoryBadge, {
            props: {
                category: { name: 'Laravel', color: '#f97316' },
                href: '/c/laravel',
            },
        });
        const tag = mount(TagBadge, {
            props: { tag: { name: 'vue', color: null }, href: '/tags/vue' },
        });

        expect(category.get('a').attributes('href')).toBe('/c/laravel');
        expect(category.text()).toBe('Laravel');
        expect(tag.get('a').text()).toBe('#vue');
    });

    it('are plain text otherwise', () => {
        const category = mount(CategoryBadge, {
            props: { category: { name: 'Laravel', color: '#f97316' } },
        });

        expect(category.find('a').exists()).toBe(false);
        expect(
            category.get('[aria-hidden="true"]').attributes('style'),
        ).toContain('background-color');
    });
});

describe('EmptyState', () => {
    it('explains what is missing and offers an action', () => {
        const wrapper = mount(EmptyState, {
            props: {
                title: 'No threads yet',
                description: 'Be the first to ask.',
            },
            slots: { default: '<button>New thread</button>' },
        });

        expect(wrapper.get('h3').text()).toBe('No threads yet');
        expect(wrapper.text()).toContain('Be the first to ask.');
        expect(wrapper.get('button').text()).toBe('New thread');
    });
});
