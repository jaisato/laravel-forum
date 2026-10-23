import { flushPromises, mount } from '@vue/test-utils';
import ReportDialog from '@/components/forum/ReportDialog.vue';
import { forms, succeed } from '../support/fakeForm';

vi.mock('@inertiajs/vue3', async () => {
    const { fakeUseForm } = await import('../support/fakeForm');

    return { useForm: fakeUseForm };
});

function open() {
    return mount(ReportDialog, {
        props: { open: true, postId: 12, 'onUpdate:open': () => {} },
        attachTo: document.body,
    });
}

describe('ReportDialog', () => {
    beforeEach(() => {
        forms.length = 0;
        document.body.innerHTML = '';
    });

    it('asks why the post breaks the rules', async () => {
        const wrapper = open();
        await flushPromises();

        const reasons = Array.from(
            document.querySelectorAll<HTMLInputElement>(
                '[data-test="report-reason"]',
            ),
        ).map((input) => input.value);

        expect(reasons).toEqual([
            'spam',
            'abuse',
            'off_topic',
            'illegal',
            'other',
        ]);
        expect(
            document
                .querySelector('[data-test="submit-report"]')
                ?.hasAttribute('disabled'),
        ).toBe(true);

        wrapper.unmount();
    });

    it('files the report and closes', async () => {
        const wrapper = open();
        await flushPromises();

        const spam = document.querySelector<HTMLInputElement>(
            '[data-test="report-reason"][value="abuse"]',
        )!;
        spam.checked = true;
        spam.dispatchEvent(new Event('change'));
        const details = document.querySelector<HTMLTextAreaElement>(
            '[data-test="report-details"]',
        )!;
        details.value = 'Insulta a otro miembro.';
        details.dispatchEvent(new Event('input'));
        await flushPromises();

        document
            .querySelector<HTMLFormElement>('[data-test="report-form"]')!
            .dispatchEvent(new Event('submit'));
        await flushPromises();

        const form = forms.at(-1)!;

        expect(form.reason).toBe('abuse');
        expect(form.details).toBe('Insulta a otro miembro.');
        expect(form.post).toHaveBeenCalledWith(
            '/posts/12/reports',
            expect.objectContaining({ preserveScroll: true }),
        );

        succeed(form.post);

        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);
        expect(form.reset).toHaveBeenCalled();

        wrapper.unmount();
    });
});
