import { mount } from '@vue/test-utils';
import AiSummaryCard from '@/components/forum/AiSummaryCard.vue';
import type { AiSummary } from '@/types/forum';

const router = vi.hoisted(() => ({ post: vi.fn(), reload: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({ router }));

function summary(overrides: Partial<AiSummary> = {}): AiSummary {
    return {
        html: '<p><strong>TL;DR:</strong> usa Horizon.</p>',
        generated_at: '2026-10-22T10:00:00+00:00',
        stale: false,
        ...overrides,
    };
}

function card(props: { summary: AiSummary | null; canSummarize: boolean }) {
    return mount(AiSummaryCard, {
        props: { threadSlug: 'colas-en-produccion', ...props },
    });
}

describe('AiSummaryCard', () => {
    beforeEach(() => {
        router.post.mockReset();
        router.reload.mockReset();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    /** Asks for a summary; the request succeeds with the given page. */
    async function askFor(
        wrapper: ReturnType<typeof card>,
        page: { flash?: Record<string, unknown> } = {},
    ): Promise<void> {
        await wrapper.get('[data-test="ai-summary-ask"]').trigger('click');
        router.post.mock.calls[0][2].onSuccess({ flash: {}, ...page });
    }

    it('looks for the summary it asked for until it arrives', async () => {
        vi.useFakeTimers();
        const wrapper = card({ summary: null, canSummarize: true });

        await askFor(wrapper);
        await vi.advanceTimersByTimeAsync(5000);

        expect(router.reload).toHaveBeenCalledTimes(1);
        expect(router.reload).toHaveBeenCalledWith({ only: ['summary'] });

        await wrapper.setProps({ summary: summary() });
        await vi.advanceTimersByTimeAsync(30000);

        expect(router.reload).toHaveBeenCalledTimes(1);
    });

    it('stops looking after a minute', async () => {
        vi.useFakeTimers();
        const wrapper = card({ summary: null, canSummarize: true });

        await askFor(wrapper);
        await vi.advanceTimersByTimeAsync(120000);

        expect(router.reload).toHaveBeenCalledTimes(12);
    });

    it('does not look when the request was turned down', async () => {
        vi.useFakeTimers();
        const wrapper = card({ summary: null, canSummarize: true });

        await askFor(wrapper, {
            flash: {
                toast: {
                    type: 'error',
                    message: 'Too many attempts. Please wait a moment.',
                },
            },
        });
        await vi.advanceTimersByTimeAsync(60000);

        expect(router.reload).not.toHaveBeenCalled();
    });

    it('stops looking when it leaves the page', async () => {
        vi.useFakeTimers();
        const wrapper = card({ summary: null, canSummarize: true });

        await askFor(wrapper);
        wrapper.unmount();
        await vi.advanceTimersByTimeAsync(60000);

        expect(router.reload).not.toHaveBeenCalled();
    });

    it('shows the summary and says an AI wrote it', () => {
        const wrapper = card({ summary: summary(), canSummarize: true });

        expect(wrapper.get('[data-test="ai-summary"]').html()).toContain(
            '<strong>TL;DR:</strong> usa Horizon.',
        );
        expect(wrapper.text()).toContain(
            'Written by an AI: it can make mistakes.',
        );
        // Fresh: nothing to ask for.
        expect(wrapper.find('[data-test="ai-summary-ask"]').exists()).toBe(
            false,
        );
    });

    it('lets members ask for one when there is none', async () => {
        const wrapper = card({ summary: null, canSummarize: true });

        const button = wrapper.get('[data-test="ai-summary-ask"]');
        expect(button.text()).toBe('Summarize the thread');

        await button.trigger('click');

        expect(router.post).toHaveBeenCalledWith(
            '/t/colas-en-produccion/summary',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('lets members update a stale one', () => {
        const wrapper = card({
            summary: summary({ stale: true }),
            canSummarize: true,
        });

        expect(wrapper.get('[data-test="ai-summary-ask"]').text()).toBe(
            'Update the summary',
        );
    });

    it('shows the summary alone to whoever may not ask for one', () => {
        const wrapper = card({
            summary: summary({ stale: true }),
            canSummarize: false,
        });

        expect(wrapper.find('[data-test="ai-summary"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="ai-summary-ask"]').exists()).toBe(
            false,
        );
    });

    it('shows nothing without a summary nor a way to ask for one', () => {
        const wrapper = card({ summary: null, canSummarize: false });

        expect(wrapper.find('[data-test="ai-summary"]').exists()).toBe(false);
    });
});
