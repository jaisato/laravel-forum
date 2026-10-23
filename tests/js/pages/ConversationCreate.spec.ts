import { mount } from '@vue/test-utils';
import type { FunctionalComponent } from 'vue';
import { nextTick } from 'vue';
import ConversationCreate from '@/pages/conversations/Create.vue';
import { forms } from '../support/fakeForm';

vi.mock('@inertiajs/vue3', async () => {
    const { fakeUseForm } = await import('../support/fakeForm');
    const { defineComponent, h } = await import('vue');

    const Head: FunctionalComponent<{ title: string }> = (props) =>
        h('div', { 'data-test': 'head', title: props.title });
    Head.props = ['title'];

    return {
        router: { visit: vi.fn() },
        useForm: fakeUseForm,
        useHttp: () => ({ body: '', post: vi.fn() }),
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

const ana = {
    id: 2,
    name: 'Ana García',
    username: 'ana',
    avatar_url: null,
    role: 'member' as const,
};

beforeEach(() => {
    forms.length = 0;
});

describe('conversations/Create', () => {
    it('starts with the member a profile sent them to write to', () => {
        const wrapper = mount(ConversationCreate, {
            props: { recipients: [ana] },
        });

        expect(
            (
                wrapper.get('#conversation-recipients')
                    .element as HTMLInputElement
            ).value,
        ).toBe('ana');
    });

    it('sends the conversation to every username given', async () => {
        const wrapper = mount(ConversationCreate, {
            props: { recipients: [] },
        });
        const form = forms.at(-1)!;

        await wrapper
            .get('#conversation-recipients')
            .setValue(' ana, @luis  carol,, ');
        await wrapper.get('#conversation-subject').setValue('Una pregunta');
        await wrapper.get('textarea').setValue('¿Usáis Horizon?');
        await wrapper.get('form').trigger('submit');

        expect(form.recipients).toEqual(['ana', 'luis', 'carol']);
        expect(form.subject).toBe('Una pregunta');
        expect(form.body).toBe('¿Usáis Horizon?');
        expect(form.post).toHaveBeenCalledWith('/messages', expect.anything());
    });

    it('shows what the server refused', async () => {
        const wrapper = mount(ConversationCreate, {
            props: { recipients: [] },
        });

        forms.at(-1)!.errors = { recipients: 'No member is called nadie.' };
        await nextTick();

        expect(wrapper.text()).toContain('No member is called nadie.');
    });
});
