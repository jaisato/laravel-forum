import { mount } from '@vue/test-utils';
import type { FunctionalComponent } from 'vue';
import ConversationShow from '@/pages/conversations/Show.vue';
import type { ConversationAbilities, Message, Paginated } from '@/types/forum';
import { forms, succeed } from '../support/fakeForm';

const page = vi.hoisted(() => ({ props: { locale: 'en' } }));
const router = vi.hoisted(() => ({ post: vi.fn(), visit: vi.fn() }));

vi.mock('@inertiajs/vue3', async () => {
    const { fakeUseForm } = await import('../support/fakeForm');
    const { defineComponent, h } = await import('vue');

    const Head: FunctionalComponent<{ title: string }> = (props) =>
        h('div', { 'data-test': 'head', title: props.title });
    Head.props = ['title'];

    return {
        router,
        usePage: () => page,
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
const luis = { ...ana, id: 3, name: 'Luis Pérez', username: 'luis' };

const messages: Message[] = [
    {
        id: 1,
        body_html: '<p>Hola, ¿tienes un <strong>minuto</strong>?</p>',
        created_at: '2026-10-22T10:00:00+00:00',
        author: ana,
        is_own: false,
    },
    {
        id: 2,
        body_html: '<p>Claro.</p>',
        created_at: '2026-10-22T10:05:00+00:00',
        author: luis,
        is_own: true,
    },
];

function show(can: ConversationAbilities = { reply: true, leave: true }) {
    const paginated: Paginated<Message> = {
        data: messages,
        links: { first: null, last: null, prev: null, next: null },
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 30,
            total: 2,
            from: 1,
            to: 2,
            path: '/messages/5',
            links: [],
        },
    };

    return mount(ConversationShow, {
        props: {
            conversation: {
                id: 5,
                subject: 'Una pregunta sobre colas',
                participants: [ana, luis],
            },
            messages: paginated,
            can,
        },
    });
}

beforeEach(() => {
    forms.length = 0;
    router.post.mockClear();
});

describe('conversations/Show', () => {
    it('shows the conversation, setting apart the member’s own messages', () => {
        const wrapper = show();

        expect(wrapper.get('[data-test="head"]').attributes('title')).toBe(
            'Una pregunta sobre colas',
        );
        expect(wrapper.get('h1').text()).toBe('Una pregunta sobre colas');
        expect(
            wrapper.get('[data-test="conversation-participants"]').text(),
        ).toContain('Ana García, Luis Pérez');

        const cards = wrapper.findAll('[data-test="message"]');

        expect(cards.map((card) => card.attributes('id'))).toEqual([
            'message-1',
            'message-2',
        ]);
        expect(cards[0].attributes('data-own')).toBeUndefined();
        expect(cards[1].attributes('data-own')).toBe('true');
        expect(cards[0].html()).toContain('<strong>minuto</strong>');
    });

    it('sends a reply and starts over', async () => {
        const wrapper = show();
        const form = forms.at(-1)!;

        await wrapper.get('textarea').setValue('  Ya está resuelto.  ');
        await wrapper.get('form[data-test="reply-form"]').trigger('submit');

        expect(form.body).toBe('  Ya está resuelto.  ');
        expect(form.post).toHaveBeenCalledWith(
            '/messages/5',
            expect.objectContaining({ preserveScroll: true }),
        );

        succeed(form.post);

        expect(form.reset).toHaveBeenCalled();
    });

    it('sends nothing empty', async () => {
        const wrapper = show();

        await wrapper.get('textarea').setValue('   ');
        await wrapper.get('form[data-test="reply-form"]').trigger('submit');

        expect(forms.at(-1)!.post).not.toHaveBeenCalled();
    });

    it('tells participants who may not write why there is no reply box', () => {
        const wrapper = show({ reply: false, leave: true });

        expect(wrapper.find('form[data-test="reply-form"]').exists()).toBe(
            false,
        );
        expect(wrapper.find('[data-test="reply-closed"]').exists()).toBe(true);
    });

    it('leaves after a confirmation', async () => {
        // happy-dom has no window.confirm to spy on.
        const confirm = vi.fn(() => false);
        vi.stubGlobal('confirm', confirm);
        const wrapper = show();

        await wrapper.get('[data-test="leave-conversation"]').trigger('click');

        expect(confirm).toHaveBeenCalledWith(
            'Leave this conversation? You will no longer be able to read it.',
        );
        expect(router.post).not.toHaveBeenCalled();

        confirm.mockReturnValueOnce(true);
        await wrapper.get('[data-test="leave-conversation"]').trigger('click');

        expect(router.post).toHaveBeenCalledWith('/messages/5/leave');

        vi.unstubAllGlobals();
    });
});
