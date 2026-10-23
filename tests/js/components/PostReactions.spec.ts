import { mount } from '@vue/test-utils';
import PostReactions from '@/components/forum/PostReactions.vue';
import type { ReactionSummary } from '@/types/forum';

const router = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({ router }));

function reactions(
    overrides: Partial<Record<string, Partial<ReactionSummary>>> = {},
): ReactionSummary[] {
    const emojis = {
        like: '👍',
        love: '❤️',
        laugh: '😂',
        insightful: '💡',
        celebrate: '🎉',
    } as const;

    return (Object.keys(emojis) as (keyof typeof emojis)[]).map((type) => ({
        type,
        emoji: emojis[type],
        count: 0,
        reacted: false,
        ...overrides[type],
    }));
}

describe('PostReactions', () => {
    beforeEach(() => router.post.mockClear());

    it("shows the reactions the post has, with the viewer's own pressed", () => {
        const wrapper = mount(PostReactions, {
            props: {
                postId: 7,
                canReact: true,
                reactions: reactions({
                    like: { count: 3, reacted: true },
                    insightful: { count: 1 },
                }),
            },
        });

        const chips = wrapper.findAll('[data-test="reaction"]');

        expect(chips.map((chip) => chip.text())).toEqual(['👍 3', '💡 1']);
        expect(chips[0].attributes('aria-pressed')).toBe('true');
        expect(chips[1].attributes('aria-pressed')).toBe('false');
    });

    it('toggles a reaction without leaving the page', async () => {
        const wrapper = mount(PostReactions, {
            props: {
                postId: 7,
                canReact: true,
                reactions: reactions({ like: { count: 1 } }),
            },
        });

        await wrapper.get('[data-test="reaction"]').trigger('click');

        expect(router.post).toHaveBeenCalledWith(
            '/posts/7/reactions',
            { type: 'like' },
            expect.objectContaining({ preserveScroll: true, only: ['posts'] }),
        );
    });

    it('offers every reaction to those who may react', async () => {
        const wrapper = mount(PostReactions, {
            props: { postId: 7, canReact: true, reactions: reactions() },
        });

        const options = wrapper.findAll('[data-test="reaction-option"]');

        expect(options.map((option) => option.text())).toEqual([
            '👍',
            '❤️',
            '😂',
            '💡',
            '🎉',
        ]);
        expect(options[2].attributes('aria-label')).toBe('Funny');

        await options[4].trigger('click');

        expect(router.post).toHaveBeenCalledWith(
            '/posts/7/reactions',
            { type: 'celebrate' },
            expect.anything(),
        );
    });

    it('only shows the counters to those who may not react', async () => {
        const wrapper = mount(PostReactions, {
            props: {
                postId: 7,
                canReact: false,
                reactions: reactions({ love: { count: 2 } }),
            },
        });

        expect(wrapper.find('[data-test="reaction-option"]').exists()).toBe(
            false,
        );

        const chip = wrapper.get('[data-test="reaction"]');

        expect(chip.attributes('disabled')).toBeDefined();

        await chip.trigger('click');

        expect(router.post).not.toHaveBeenCalled();
    });

    it('stays empty when nobody reacted and the viewer may not', () => {
        const wrapper = mount(PostReactions, {
            props: { postId: 7, canReact: false, reactions: reactions() },
        });

        expect(wrapper.find('[data-test="reaction"]').exists()).toBe(false);
    });
});
