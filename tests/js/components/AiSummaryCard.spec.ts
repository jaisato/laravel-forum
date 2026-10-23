import { mount } from '@vue/test-utils';
import AiSummaryCard from '@/components/forum/AiSummaryCard.vue';
import type { AiSummary } from '@/types/forum';

const router = vi.hoisted(() => ({ post: vi.fn() }));

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
