import { flushPromises, mount } from '@vue/test-utils';
import EditThreadDialog from '@/components/forum/EditThreadDialog.vue';
import { forms, succeed } from '../support/fakeForm';

vi.mock('@inertiajs/vue3', async () => {
    const { fakeUseForm } = await import('../support/fakeForm');

    return { useForm: fakeUseForm };
});

const tagOptions = [
    { id: 1, name: 'colas', slug: 'colas', color: null },
    { id: 2, name: 'redis', slug: 'redis', color: null },
];

function dialog() {
    return mount(EditThreadDialog, {
        props: {
            open: true,
            thread: {
                slug: 'como-testear-colas',
                title: '¿Cómo testear colas?',
                tags: [{ id: 1, name: 'colas', slug: 'colas', color: null }],
            },
            tagOptions,
        },
        attachTo: document.body,
    });
}

beforeEach(() => {
    forms.length = 0;
    document.body.replaceChildren();
});

describe('EditThreadDialog', () => {
    it('starts from the current title and tags and saves the changes', async () => {
        const wrapper = dialog();
        const form = forms.at(-1)!;
        // The dialog renders into the body once mounted.
        await flushPromises();

        expect(form.title).toBe('¿Cómo testear colas?');
        expect(form.tags).toEqual([1]);

        const title = document.querySelector<HTMLInputElement>(
            '[data-test="edit-thread-title"]',
        )!;
        title.value = '¿Cómo se testean las colas?';
        title.dispatchEvent(new Event('input'));
        // The input hands its value up in a watcher.
        await flushPromises();
        document
            .querySelector<HTMLButtonElement>('[data-test="tag-option-2"]')!
            .click();
        document
            .querySelector<HTMLFormElement>('[data-test="edit-thread-form"]')!
            .dispatchEvent(new Event('submit'));

        expect(form.title).toBe('¿Cómo se testean las colas?');
        expect(form.tags).toEqual([1, 2]);
        expect(form.patch).toHaveBeenCalledWith(
            '/t/como-testear-colas',
            expect.objectContaining({ preserveScroll: true }),
        );

        succeed(form.patch);

        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);
    });

    it('shows what the server refused', async () => {
        dialog();
        const form = forms.at(-1)!;

        form.errors = {
            title: 'The title field must be at least 8 characters.',
            tags: 'The tags field must not have more than 5 items.',
        };
        await flushPromises();

        expect(document.body.textContent).toContain(
            'The title field must be at least 8 characters.',
        );
        expect(document.body.textContent).toContain(
            'The tags field must not have more than 5 items.',
        );
    });
});
