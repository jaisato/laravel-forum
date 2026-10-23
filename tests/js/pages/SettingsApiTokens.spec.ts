import { flushPromises, mount } from '@vue/test-utils';
import type { FunctionalComponent } from 'vue';
import SettingsApiTokens from '@/pages/settings/ApiTokens.vue';
import { forms, succeed } from '../support/fakeForm';

const page = vi.hoisted(() => ({
    props: { locale: 'en' },
    flash: {} as { token?: string },
}));
const router = vi.hoisted(() => ({ delete: vi.fn() }));

vi.mock('@inertiajs/vue3', async () => {
    const { fakeUseForm } = await import('../support/fakeForm');
    const { h } = await import('vue');

    const Head: FunctionalComponent<{ title: string }> = (props) =>
        h('div', { 'data-test': 'head', title: props.title });
    Head.props = ['title'];

    return { usePage: () => page, useForm: fakeUseForm, router, Head };
});

const tokens = [
    {
        id: 3,
        name: 'Mi script',
        abilities: ['read', 'write'],
        last_used_at: null,
        created_at: '2026-10-22T10:00:00+00:00',
    },
];

function settings(abilities = ['read', 'write', 'messages']) {
    return mount(SettingsApiTokens, { props: { tokens, abilities } });
}

beforeEach(() => {
    forms.length = 0;
    page.flash = {};
    router.delete.mockReset();
});

describe('settings/ApiTokens', () => {
    it('lists the tokens with what they may do', () => {
        const wrapper = settings();
        const token = wrapper.get('[data-test="api-token"]');

        expect(wrapper.get('[data-test="head"]').attributes('title')).toBe(
            'API tokens',
        );
        expect(token.text()).toContain('Mi script');
        expect(token.text()).toContain('Read');
        expect(token.text()).toContain('Write');
        expect(token.text()).toContain('Never used');
    });

    it('offers the abilities the member may give, reading by default', () => {
        const member = settings();
        const moderator = settings(['read', 'write', 'messages', 'moderate']);

        expect(member.find('#ability-moderate').exists()).toBe(false);
        expect(moderator.find('#ability-moderate').exists()).toBe(true);
        expect(forms.at(-1)!.abilities).toEqual(['read']);
    });

    it('creates a token with the chosen abilities', async () => {
        const wrapper = settings();
        const form = forms.at(-1)!;

        await wrapper.get('#token-name').setValue('Mi script');
        await wrapper.get('#ability-write').setValue(true);
        await wrapper.get('form').trigger('submit');

        expect(form.name).toBe('Mi script');
        expect(form.abilities).toEqual(['read', 'write']);
        expect(form.post).toHaveBeenCalledWith(
            '/settings/api-tokens',
            expect.objectContaining({ preserveScroll: true }),
        );

        succeed(form.post);

        expect(form.reset).toHaveBeenCalled();
    });

    it('shows a new token once, ready to copy', async () => {
        const writeText = vi.fn(() => Promise.resolve());
        Object.defineProperty(navigator, 'clipboard', {
            value: { writeText },
            configurable: true,
        });
        page.flash = { token: '7|abcdef' };

        const wrapper = settings();

        expect(wrapper.get('[data-test="new-token"]').text()).toContain(
            '7|abcdef',
        );

        await wrapper.get('[data-test="copy-token"]').trigger('click');
        await flushPromises();

        expect(writeText).toHaveBeenCalledWith('7|abcdef');
    });

    it('revokes a token after a confirmation', async () => {
        // happy-dom has no window.confirm to spy on.
        const confirm = vi.fn(() => false);
        vi.stubGlobal('confirm', confirm);
        const wrapper = settings();

        await wrapper.get('[data-test="revoke-token"]').trigger('click');

        expect(confirm).toHaveBeenCalledWith(
            'Revoke the token “Mi script”? Whatever uses it will stop working.',
        );
        expect(router.delete).not.toHaveBeenCalled();

        confirm.mockReturnValueOnce(true);
        await wrapper.get('[data-test="revoke-token"]').trigger('click');

        expect(router.delete).toHaveBeenCalledWith('/settings/api-tokens/3', {
            preserveScroll: true,
        });

        vi.unstubAllGlobals();
    });
});
