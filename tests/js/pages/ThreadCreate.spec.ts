import { flushPromises, mount } from '@vue/test-utils';
import type { FunctionalComponent } from 'vue';
import ThreadCreate from '@/pages/threads/Create.vue';
import { forms, succeed } from '../support/fakeForm';

const router = vi.hoisted(() => ({ reload: vi.fn(), visit: vi.fn() }));

vi.mock('@inertiajs/vue3', async () => {
    const { fakeUseForm } = await import('../support/fakeForm');
    const { defineComponent, h } = await import('vue');

    const Head: FunctionalComponent<{ title: string }> = (props) =>
        h('div', { 'data-test': 'head', title: props.title });
    Head.props = ['title'];

    return {
        router,
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

const categories = [
    {
        id: 1,
        name: 'Laravel',
        slug: 'laravel',
        color: '#ef4444',
        is_locked: false,
    },
    {
        id: 2,
        name: 'Anuncios',
        slug: 'anuncios',
        color: '#3b82f6',
        is_locked: true,
    },
];
const tags = [{ id: 7, name: 'colas', slug: 'colas', color: null }];

function create(props: Record<string, unknown> = {}) {
    return mount(ThreadCreate, {
        props: { categories, tags, category: 1, similar: [], ...props },
        attachTo: document.body,
    });
}

beforeEach(() => {
    forms.length = 0;
    router.reload.mockClear();
    window.localStorage.clear();
    document.body.replaceChildren();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('threads/Create', () => {
    it('publishes the thread in the preselected category', async () => {
        const wrapper = create();
        const form = forms.at(-1)!;

        expect(form.category_id).toBe(1);
        expect(wrapper.get('[data-test="head"]').attributes('title')).toBe(
            'New thread',
        );

        await wrapper.get('#thread-title').setValue('¿Cómo testear las colas?');
        await wrapper
            .get('textarea')
            .setValue('Uso Queue::fake() en los tests.');
        await wrapper.get('[data-test="tag-option-7"]').trigger('click');
        await wrapper.get('form').trigger('submit');

        expect(form.title).toBe('¿Cómo testear las colas?');
        expect(form.body).toBe('Uso Queue::fake() en los tests.');
        expect(form.tags).toEqual([7]);
        expect(form.post).toHaveBeenCalledWith('/threads', expect.anything());
    });

    it('keeps what the member writes until the thread is published', async () => {
        const first = create();

        await first.get('#thread-title').setValue('Una pregunta a medias');
        await first
            .get('textarea')
            .setValue('Un borrador que no quiero perder.');
        first.unmount();

        const again = create();
        await flushPromises();

        expect(
            (again.get('#thread-title').element as HTMLInputElement).value,
        ).toBe('Una pregunta a medias');
        expect(
            (again.get('textarea').element as HTMLTextAreaElement).value,
        ).toBe('Un borrador que no quiero perder.');

        await again.get('form').trigger('submit');
        succeed(forms.at(-1)!.post);

        expect(window.localStorage.length).toBe(0);
    });

    it('looks for similar threads once the title says enough', async () => {
        vi.useFakeTimers();
        const wrapper = create();

        await wrapper.get('#thread-title').setValue('Colas');
        vi.runAllTimers();

        expect(router.reload).not.toHaveBeenCalled();

        await wrapper.get('#thread-title').setValue('¿Cómo testear las colas?');
        vi.runAllTimers();

        expect(router.reload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ['similar'],
                data: { title: '¿Cómo testear las colas?' },
                preserveUrl: true,
            }),
        );
    });

    it('shows the similar threads it found', () => {
        const wrapper = create({
            similar: [
                {
                    id: 4,
                    title: '¿Cómo testear colas?',
                    slug: 'como-testear-colas',
                    replies_count: 2,
                    is_solved: false,
                },
            ],
        });

        expect(wrapper.get('[data-test="similar-threads"]').text()).toContain(
            '¿Cómo testear colas?',
        );
    });

    it('shows what the server refused', async () => {
        const wrapper = create({ category: null });
        const form = forms.at(-1)!;

        form.errors = {
            category_id: 'The category id field is required.',
            title: 'The title field must be at least 8 characters.',
            tags: 'The tags field must not have more than 5 items.',
        };
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('The category id field is required.');
        expect(wrapper.text()).toContain(
            'The title field must be at least 8 characters.',
        );
        expect(wrapper.text()).toContain(
            'The tags field must not have more than 5 items.',
        );
    });
});
