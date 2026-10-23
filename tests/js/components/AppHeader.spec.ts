import { mount } from '@vue/test-utils';
import AppHeader from '@/components/AppHeader.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import type { AuthUser } from '@/types/forum';

type Page = {
    url: string;
    props: {
        auth: { user: AuthUser | null };
        locale: string;
        locales: string[];
    };
};

const state = vi.hoisted(() => ({ page: null as unknown as Page }));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h, reactive } = await import('vue');

    state.page = reactive({
        url: '/',
        props: { auth: { user: null }, locale: 'en', locales: ['es', 'en'] },
    }) as Page;

    return {
        usePage: () => state.page,
        useHttp: () => ({ get: vi.fn() }),
        router: { get: vi.fn(), flushAll: vi.fn() },
        Link: defineComponent({
            props: { href: { type: String, required: true } },
            setup:
                (props, { slots, attrs }) =>
                () =>
                    h('a', { ...attrs, href: props.href }, slots.default?.()),
        }),
    };
});

const member: AuthUser = {
    id: 1,
    name: 'Ana López',
    username: 'ana',
    avatar_url: null,
    role: 'member',
    email: 'ana@example.com',
    is_staff: false,
    is_banned: false,
    email_verified: true,
    unread_notifications: 3,
    unread_conversations: 2,
};

const slot = { template: '<div><slot /></div>' };

function header() {
    return mount(AppHeader, {
        global: {
            stubs: {
                AppLogo: true,
                LocaleSwitcher: true,
                Sheet: true,
                DropdownMenu: true,
                Breadcrumbs: true,
            },
        },
    });
}

describe('AppHeader', () => {
    beforeEach(() => {
        state.page.props.auth.user = null;
    });

    it('shows members how many notifications they have not read', () => {
        state.page.props.auth.user = member;

        const wrapper = header();

        const bell = wrapper.get('a[href="/notifications"]');

        expect(bell.attributes('aria-label')).toBe('Notifications (3 unread)');
        expect(bell.get('[data-test="unread-count"]').text()).toBe('3');
    });

    it('shows members how many conversations have something new', () => {
        state.page.props.auth.user = member;

        const link = header().get('a[href="/messages"]');

        expect(link.attributes('aria-label')).toBe('Messages (2 unread)');
        expect(link.get('[data-test="unread-count"]').text()).toBe('2');
    });

    it('has no bell nor messages for guests', () => {
        const wrapper = header();

        expect(wrapper.find('a[href="/notifications"]').exists()).toBe(false);
        expect(wrapper.find('a[href="/messages"]').exists()).toBe(false);
    });

    it('lets everybody search', () => {
        expect(header().find('form[role="search"]').exists()).toBe(true);
    });
});

describe('UserMenuContent', () => {
    it('links to the member’s own profile', () => {
        const wrapper = mount(UserMenuContent, {
            props: { user: member },
            global: {
                stubs: {
                    DropdownMenuLabel: slot,
                    DropdownMenuGroup: slot,
                    DropdownMenuItem: slot,
                    DropdownMenuSeparator: true,
                    UserInfo: true,
                },
            },
        });

        expect(
            wrapper.get('[data-test="profile-link"]').attributes('href'),
        ).toBe('/u/ana');
    });
});
