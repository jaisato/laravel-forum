import { flushPromises, mount } from '@vue/test-utils';
import MarkdownEditor from '@/components/forum/MarkdownEditor.vue';

const preview = vi.hoisted(() => ({
    body: '',
    post: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => preview,
    router: { visit: vi.fn() },
}));

describe('MarkdownEditor', () => {
    beforeEach(() => {
        preview.body = '';
        preview.post.mockReset();
    });

    it('edits the Markdown source', async () => {
        const wrapper = mount(MarkdownEditor, {
            props: { modelValue: 'Hola', 'onUpdate:modelValue': () => {} },
        });

        await wrapper.get('textarea').setValue('Hola **mundo**');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([
            'Hola **mundo**',
        ]);
    });

    it('previews the text as the server renders it', async () => {
        preview.post.mockResolvedValue({
            html: '<p>Hola <strong>mundo</strong></p>',
        });
        const wrapper = mount(MarkdownEditor, {
            props: { modelValue: 'Hola **mundo**' },
        });

        await wrapper.get('[data-test="tab-preview"]').trigger('click');
        await flushPromises();

        expect(preview.post).toHaveBeenCalledWith('/markdown/preview');
        expect(preview.body).toBe('Hola **mundo**');
        expect(wrapper.get('[data-test="preview"]').html()).toContain(
            '<strong>mundo</strong>',
        );
        expect(wrapper.find('textarea').exists()).toBe(false);

        await wrapper.get('[data-test="tab-write"]').trigger('click');

        expect(wrapper.find('textarea').exists()).toBe(true);
    });

    it('ignores a preview that arrives after a newer one', async () => {
        let answerFirst: (value: { html: string }) => void = () => {};
        preview.post
            .mockReturnValueOnce(
                new Promise((resolve) => {
                    answerFirst = resolve;
                }),
            )
            .mockResolvedValueOnce({ html: '<p>Segundo</p>' });
        const wrapper = mount(MarkdownEditor, {
            props: { modelValue: 'Primero' },
        });

        await wrapper.get('[data-test="tab-preview"]').trigger('click');
        await wrapper.get('[data-test="tab-write"]').trigger('click');
        await wrapper.setProps({ modelValue: 'Segundo' });
        await wrapper.get('[data-test="tab-preview"]').trigger('click');
        await flushPromises();

        answerFirst({ html: '<p>Primero</p>' });
        await flushPromises();

        expect(wrapper.get('[data-test="preview"]').text()).toBe('Segundo');
    });

    it('has nothing to preview without text', async () => {
        const wrapper = mount(MarkdownEditor, { props: { modelValue: '  ' } });

        await wrapper.get('[data-test="tab-preview"]').trigger('click');

        expect(preview.post).not.toHaveBeenCalled();
        expect(wrapper.get('[data-test="preview"]').text()).toBe(
            'Nothing to preview.',
        );
    });

    it('says when the preview is not available', async () => {
        preview.post.mockRejectedValue(new Error('500'));
        const wrapper = mount(MarkdownEditor, {
            props: { modelValue: 'Hola' },
        });

        await wrapper.get('[data-test="tab-preview"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-test="preview"]').text()).toBe(
            'The preview is not available right now.',
        );
    });

    it('submits with Ctrl+Enter and counts characters near the limit', async () => {
        const wrapper = mount(MarkdownEditor, {
            props: { modelValue: 'a'.repeat(95), maxLength: 100 },
        });

        await wrapper.get('textarea').trigger('keydown', {
            key: 'Enter',
            ctrlKey: true,
        });

        expect(wrapper.emitted('submit')).toHaveLength(1);
        expect(wrapper.get('[data-test="char-count"]').text()).toBe('95 / 100');

        await wrapper.setProps({ modelValue: 'short' });

        expect(wrapper.find('[data-test="char-count"]').exists()).toBe(false);
    });

    it('shows a validation error', () => {
        const wrapper = mount(MarkdownEditor, {
            props: { modelValue: '', error: 'The body field is required.' },
        });

        expect(wrapper.text()).toContain('The body field is required.');
        expect(wrapper.get('textarea').attributes('aria-invalid')).toBe('true');
    });
});
