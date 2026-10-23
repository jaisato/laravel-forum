import { mount } from '@vue/test-utils';
import type { FunctionalComponent } from 'vue';
import ConversationsIndex from '@/pages/conversations/Index.vue';
import type { Conversation, Paginated } from '@/types/forum';

const page = vi.hoisted(() => ({ props: { locale: 'en' } }));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    const Head: FunctionalComponent<{ title: string }> = () => h('div');
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

const ana = {
    id: 2,
    name: 'Ana García',
    username: 'ana',
    avatar_url: null,
    role: 'member' as const,
};
const luis = { ...ana, id: 3, name: 'Luis Pérez', username: 'luis' };

function conversation(overrides: Partial<Conversation> = {}): Conversation {
    return {
        id: 5,
        subject: 'Una pregunta sobre colas',
        last_message_at: '2026-10-22T10:00:00+00:00',
        is_unread: true,
        participants: [ana, luis],
        last_message: { excerpt: 'Te escribo por lo de Horizon', author: ana },
        ...overrides,
    };
}

function inbox(data: Conversation[]) {
    const conversations: Paginated<Conversation> = {
        data,
        links: { first: null, last: null, prev: null, next: null },
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: data.length,
            from: null,
            to: null,
            path: '/messages',
            links: [],
        },
    };

    return mount(ConversationsIndex, { props: { conversations } });
}

describe('conversations/Index', () => {
    it('lists the conversations with who is in them and the last message', () => {
        const wrapper = inbox([conversation()]);
        const link = wrapper.get('[data-test="conversation-link"]');

        expect(link.attributes('href')).toBe('/messages/5');
        expect(link.text()).toBe('Una pregunta sobre colas');
        expect(
            wrapper.get('[data-test="conversation"]').attributes('data-unread'),
        ).toBe('true');
        expect(
            wrapper.get('[data-test="conversation-participants"]').text(),
        ).toBe('Ana García, Luis Pérez');
        expect(
            wrapper.get('[data-test="conversation-last-message"]').text(),
        ).toBe('Ana García: Te escribo por lo de Horizon');
    });

    it('sets apart only the conversations with something new', () => {
        const wrapper = inbox([conversation({ is_unread: false })]);

        expect(
            wrapper.get('[data-test="conversation"]').attributes('data-unread'),
        ).toBeUndefined();
    });

    it('copes with members who are gone', () => {
        const wrapper = inbox([
            conversation({
                participants: [],
                last_message: { excerpt: 'Adiós', author: null },
            }),
        ]);

        expect(
            wrapper.get('[data-test="conversation-participants"]').text(),
        ).toBe('Nobody else');
        expect(
            wrapper.get('[data-test="conversation-last-message"]').text(),
        ).toBe('Deleted user: Adiós');
    });

    it('offers to start a conversation', () => {
        expect(
            inbox([]).get('[data-test="new-conversation"]').attributes('href'),
        ).toBe('/messages/create');
    });

    it('says when there are no conversations', () => {
        expect(
            inbox([]).get('[data-test="empty-conversations"]').text(),
        ).toContain('No conversations yet');
    });
});
