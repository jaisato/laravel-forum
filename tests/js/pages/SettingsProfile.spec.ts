import { mount } from '@vue/test-utils';
import type { FunctionalComponent } from 'vue';
import { nextTick, toRaw } from 'vue';
import SettingsProfile from '@/pages/settings/Profile.vue';
import { forms, succeed } from '../support/fakeForm';

const page = vi.hoisted(() => ({
    props: {
        auth: {
            user: {
                id: 1,
                name: 'Ana López',
                username: 'ana',
                email: 'ana@example.com',
                avatar_url: null,
                email_verified: true,
            },
        },
        locale: 'en',
        locales: ['es', 'en'],
    },
}));

vi.mock('@inertiajs/vue3', async () => {
    const { fakeUseForm } = await import('../support/fakeForm');
    const { defineComponent, h } = await import('vue');

    const Head: FunctionalComponent<{ title: string }> = (props) =>
        h('div', { 'data-test': 'head', title: props.title });
    Head.props = ['title'];

    return {
        usePage: () => page,
        useForm: fakeUseForm,
        router: { delete: vi.fn() },
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

const profile = {
    username: 'ana',
    bio: 'Desarrolladora PHP.',
    location: 'Sevilla',
    website: 'https://ana.example.com',
    locale: 'en',
    avatar_url: '/storage/avatars/ana.webp',
};

function settings(overrides = {}) {
    return mount(SettingsProfile, {
        props: { status: null, profile, ...overrides },
        global: { stubs: { DeleteUser: true } },
    });
}

function profileForm() {
    return forms.find((form) => 'username' in form)!;
}

beforeEach(() => {
    forms.length = 0;
    URL.createObjectURL = vi.fn(() => 'blob:avatar');
    URL.revokeObjectURL = vi.fn();
});

describe('settings/Profile', () => {
    it('starts from what the member shares today', () => {
        const wrapper = settings();

        expect(wrapper.get('[data-test="head"]').attributes('title')).toBe(
            'Profile settings',
        );
        expect(profileForm()).toMatchObject({
            name: 'Ana López',
            username: 'ana',
            email: 'ana@example.com',
            bio: 'Desarrolladora PHP.',
            location: 'Sevilla',
            website: 'https://ana.example.com',
            locale: 'en',
            avatar: null,
            remove_avatar: false,
        });
        expect(
            wrapper.get('[data-test="avatar-preview"] img').attributes('src'),
        ).toBe('/storage/avatars/ana.webp');
    });

    it('sends the profile as form data, so a new picture can go with it', async () => {
        const wrapper = settings();
        const form = profileForm();
        const picture = new File(['png'], 'foto.png', { type: 'image/png' });
        const input = wrapper.get('input[type="file"]');

        Object.defineProperty(input.element, 'files', { value: [picture] });
        await input.trigger('change');
        await wrapper.get('#username').setValue('ana_lopez');
        await wrapper.get('form[data-test="profile-form"]').trigger('submit');

        expect(toRaw(form.avatar)).toBe(picture);
        expect(form.username).toBe('ana_lopez');
        expect(form.post).toHaveBeenCalledWith(
            '/settings/profile?_method=PATCH',
            expect.objectContaining({ forceFormData: true }),
        );

        succeed(form.post);

        expect(form.avatar).toBeNull();
    });

    it('takes the picture away', async () => {
        const wrapper = settings();

        await wrapper.get('[data-test="remove-avatar"]').trigger('click');

        expect(profileForm().remove_avatar).toBe(true);
        expect(wrapper.find('[data-test="avatar-preview"] img').exists()).toBe(
            false,
        );
    });

    it('offers no removal when there is no picture', () => {
        expect(
            settings({ profile: { ...profile, avatar_url: null } })
                .find('[data-test="remove-avatar"]')
                .exists(),
        ).toBe(false);
    });

    it('lets the member follow the browser’s language', () => {
        const wrapper = settings({ profile: { ...profile, locale: null } });
        const options = wrapper
            .findAll('#locale option')
            .map((option) => option.attributes('value'));

        expect(profileForm().locale).toBe('');
        expect(options).toEqual(['', 'es', 'en']);
    });

    it('shows what the server refused', async () => {
        const wrapper = settings();

        profileForm().errors = {
            username: 'The username has already been taken.',
        };
        await nextTick();

        expect(wrapper.text()).toContain(
            'The username has already been taken.',
        );
    });
});
