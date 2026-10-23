import { mount } from '@vue/test-utils';
import type { FunctionalComponent } from 'vue';
import SettingsNotifications from '@/pages/settings/Notifications.vue';
import { forms } from '../support/fakeForm';

vi.mock('@inertiajs/vue3', async () => {
    const { fakeUseForm } = await import('../support/fakeForm');
    const { h } = await import('vue');

    const Head: FunctionalComponent<{ title: string }> = (props) =>
        h('div', { 'data-test': 'head', title: props.title });
    Head.props = ['title'];

    return { useForm: fakeUseForm, Head };
});

const preferences = {
    email_replies: true,
    email_mentions: false,
    email_solutions: true,
    email_messages: true,
    email_moderation: true,
    allow_private_messages: true,
};

beforeEach(() => {
    forms.length = 0;
});

describe('settings/Notifications', () => {
    it('shows each preference as it is', () => {
        const wrapper = mount(SettingsNotifications, {
            props: { preferences },
        });

        expect(wrapper.get('[data-test="head"]').attributes('title')).toBe(
            'Notification settings',
        );
        expect(
            (wrapper.get('#email_replies').element as HTMLInputElement).checked,
        ).toBe(true);
        expect(
            (wrapper.get('#email_mentions').element as HTMLInputElement)
                .checked,
        ).toBe(false);
        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(6);
    });

    it('saves the preferences', async () => {
        const wrapper = mount(SettingsNotifications, {
            props: { preferences },
        });
        const form = forms.at(-1)!;

        await wrapper.get('#allow_private_messages').setValue(false);
        await wrapper.get('form').trigger('submit');

        expect(form.allow_private_messages).toBe(false);
        expect(form.put).toHaveBeenCalledWith(
            '/settings/notifications',
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
