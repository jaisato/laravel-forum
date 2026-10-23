import { mount } from '@vue/test-utils';
import type { FunctionalComponent } from 'vue';
import UserShow from '@/pages/users/Show.vue';
import type {
    Paginated,
    PostSummary,
    ThreadSummary,
    UserProfile,
} from '@/types/forum';

const page = vi.hoisted(() => ({ props: { locale: 'en' } }));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    const Head: FunctionalComponent<{ title: string }> = (_, { slots }) =>
        h('div', slots.default?.());
    Head.props = ['title'];

    return {
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

const profile: UserProfile = {
    id: 1,
    name: 'Ana López',
    username: 'ana',
    avatar_url: null,
    role: 'moderator',
    bio: 'Desarrolladora PHP en Sevilla.',
    location: 'Sevilla',
    website: 'https://ana.example.com/blog',
    reputation: 1234,
    threads_count: 12,
    posts_count: 340,
    created_at: '2026-01-15T10:00:00+00:00',
    last_seen_at: '2026-10-22T09:00:00+00:00',
};

function paginated<T>(data: T[], path: string): Paginated<T> {
    return {
        data,
        links: { first: null, last: null, prev: null, next: null },
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: data.length,
            from: null,
            to: null,
            path,
            links: [],
        },
    };
}

const meta = { title: 'Ana López (@ana)', description: 'Desarrolladora PHP.' };

function show(props: Record<string, unknown>) {
    return mount(UserShow, {
        props: { profile, tab: 'threads', meta, ...props },
        global: { stubs: { ThreadList: true, PostSummaryList: true } },
    });
}

describe('users/Show', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-10-22T12:00:00Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('introduces the member', () => {
        const wrapper = show({
            threads: paginated<ThreadSummary>([], '/u/ana'),
        });

        expect(wrapper.get('h1').text()).toBe('Ana López');
        expect(wrapper.text()).toContain('@ana');
        expect(wrapper.text()).toContain('Moderator');
        expect(wrapper.get('[data-test="profile-bio"]').text()).toBe(
            'Desarrolladora PHP en Sevilla.',
        );
        expect(wrapper.get('[data-test="profile-location"]').text()).toBe(
            'Sevilla',
        );
        expect(wrapper.get('[data-test="profile-joined"]').text()).toBe(
            'Joined January 2026',
        );
        expect(
            wrapper.get('[data-test="profile-reputation"]').text(),
        ).toContain('1.2K');
    });

    it('links the website without vouching for it', () => {
        const link = show({
            threads: paginated<ThreadSummary>([], '/u/ana'),
        }).get('[data-test="profile-website"]');

        expect(link.attributes('href')).toBe('https://ana.example.com/blog');
        expect(link.attributes('rel')).toBe('nofollow ugc noopener noreferrer');
        expect(link.attributes('target')).toBe('_blank');
        expect(link.text()).toBe('ana.example.com/blog');
    });

    it('leaves out what the member did not share', () => {
        const wrapper = mount(UserShow, {
            props: {
                profile: {
                    ...profile,
                    bio: null,
                    location: null,
                    website: null,
                    last_seen_at: null,
                },
                tab: 'threads',
                meta,
                threads: paginated<ThreadSummary>([], '/u/ana'),
            },
            global: { stubs: { ThreadList: true } },
        });

        expect(wrapper.find('[data-test="profile-bio"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="profile-location"]').exists()).toBe(
            false,
        );
        expect(wrapper.find('[data-test="profile-website"]').exists()).toBe(
            false,
        );
        expect(wrapper.find('[data-test="profile-seen"]').exists()).toBe(false);
    });

    it('links the tabs and marks the open one', () => {
        const wrapper = show({
            threads: paginated<ThreadSummary>([], '/u/ana'),
        });
        const tabs = wrapper.findAll('[data-test="profile-tab"]');

        expect(tabs.map((tab) => tab.attributes('href'))).toEqual([
            '/u/ana',
            '/u/ana?tab=posts',
        ]);
        expect(tabs[0].attributes('aria-current')).toBe('page');
        expect(tabs[1].attributes('aria-current')).toBeUndefined();
    });

    it('lists the threads on the threads tab', () => {
        const wrapper = show({
            threads: paginated<ThreadSummary>([], '/u/ana'),
        });

        expect(wrapper.findComponent({ name: 'ThreadList' }).exists()).toBe(
            true,
        );
        expect(
            wrapper.findComponent({ name: 'PostSummaryList' }).exists(),
        ).toBe(false);
    });

    it('lists the replies on the replies tab, without repeating the author', () => {
        const wrapper = show({
            tab: 'posts',
            posts: paginated<PostSummary>([], '/u/ana'),
        });

        const list = wrapper.findComponent({ name: 'PostSummaryList' });

        expect(list.exists()).toBe(true);
        expect(list.props('hideAuthor')).toBe(true);
        expect(wrapper.findComponent({ name: 'ThreadList' }).exists()).toBe(
            false,
        );
    });
});
