import { flushPromises, mount } from '@vue/test-utils';
import ReplyForm from '@/components/forum/ReplyForm.vue';
import { forms, succeed } from '../support/fakeForm';

vi.mock('@inertiajs/vue3', async () => {
    const { fakeUseForm } = await import('../support/fakeForm');

    return {
        useForm: fakeUseForm,
        useHttp: () => ({ body: '', post: vi.fn() }),
        router: { visit: vi.fn() },
    };
});

const thread = { id: 4, slug: 'como-testear-colas' };

describe('ReplyForm', () => {
    beforeEach(() => {
        forms.length = 0;
        window.localStorage.clear();
    });

    it('posts the reply and forgets the draft once published', async () => {
        const wrapper = mount(ReplyForm, { props: { thread } });
        await wrapper.get('textarea').setValue('Mi respuesta');

        expect(window.localStorage.getItem('forum:draft:reply-4')).toBe(
            'Mi respuesta',
        );

        await wrapper.get('form').trigger('submit');
        const form = forms.at(-1)!;

        expect(form.body).toBe('Mi respuesta');
        expect(form.post).toHaveBeenCalledWith(
            '/t/como-testear-colas/posts',
            expect.objectContaining({ preserveScroll: true }),
        );

        succeed(form.post);
        await flushPromises();

        expect(window.localStorage.getItem('forum:draft:reply-4')).toBeNull();
        expect(wrapper.get('textarea').element.value).toBe('');
    });

    it('restores a saved draft', async () => {
        window.localStorage.setItem('forum:draft:reply-4', 'Texto guardado');

        const wrapper = mount(ReplyForm, { props: { thread } });
        await flushPromises();

        expect(wrapper.get('textarea').element.value).toBe('Texto guardado');
    });

    it('does not send an empty reply', async () => {
        const wrapper = mount(ReplyForm, { props: { thread } });

        expect(
            wrapper.get('[data-test="submit-reply"]').attributes('disabled'),
        ).toBeDefined();

        await wrapper.get('form').trigger('submit');

        expect(forms.at(-1)!.post).not.toHaveBeenCalled();
    });

    it('quotes a post and lets the member drop the quote', async () => {
        const wrapper = mount(ReplyForm, { props: { thread } });

        wrapper.vm.quote({
            id: 9,
            body: 'Primera línea\nSegunda línea',
            author: {
                id: 2,
                name: 'Luis Pérez',
                username: 'luis',
                avatar_url: null,
                role: 'member',
            },
        });
        await flushPromises();

        expect(wrapper.get('textarea').element.value).toBe(
            '> Primera línea\n> Segunda línea\n\n',
        );
        expect(wrapper.get('[data-test="replying-to"]').text()).toContain(
            'Luis Pérez',
        );
        expect(forms.at(-1)!.reply_to_id).toBe(9);

        await wrapper.get('[data-test="cancel-quote"]').trigger('click');

        expect(forms.at(-1)!.reply_to_id).toBeNull();
        expect(wrapper.find('[data-test="replying-to"]').exists()).toBe(false);
    });

    it('shows the errors of the server', async () => {
        const wrapper = mount(ReplyForm, { props: { thread } });
        forms.at(-1)!.errors = {
            body: 'The body field must be at least 2 characters.',
        };
        await flushPromises();

        expect(wrapper.text()).toContain(
            'The body field must be at least 2 characters.',
        );
    });
});
