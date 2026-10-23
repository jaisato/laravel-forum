import { flushPromises, mount } from '@vue/test-utils';
import MoveThreadDialog from '@/components/forum/MoveThreadDialog.vue';
import PostActions from '@/components/forum/PostActions.vue';
import PostCard from '@/components/forum/PostCard.vue';
import SubscriptionButton from '@/components/forum/SubscriptionButton.vue';
import ThreadModerationMenu from '@/components/forum/ThreadModerationMenu.vue';
import ThreadStatusBadges from '@/components/forum/ThreadStatusBadges.vue';
import type { Post } from '@/types/forum';
import { forms, succeed } from '../support/fakeForm';

const router = vi.hoisted(() => ({ post: vi.fn(), delete: vi.fn() }));

vi.mock('@inertiajs/vue3', async () => {
    const { fakeUseForm } = await import('../support/fakeForm');
    const { defineComponent, h } = await import('vue');

    return {
        router,
        useForm: fakeUseForm,
        useHttp: () => ({ body: '', post: vi.fn() }),
        usePage: () => ({ props: { locale: 'en' } }),
        Link: defineComponent({
            props: { href: { type: String, required: true } },
            setup:
                (props, { slots, attrs }) =>
                () =>
                    h('a', { ...attrs, href: props.href }, slots.default?.()),
        }),
    };
});

function post(overrides: Partial<Post> = {}): Post {
    return {
        id: 9,
        thread_id: 4,
        body_html: '<p>Prueba con <code>queue:work</code>.</p>',
        body: 'Prueba con `queue:work`.',
        status: 'published',
        is_first: false,
        is_solution: false,
        is_deleted: false,
        created_at: '2026-10-22T10:00:00+00:00',
        edited_at: null,
        author: {
            id: 2,
            name: 'Luis Pérez',
            username: 'luis',
            avatar_url: null,
            role: 'moderator',
        },
        reply_to: null,
        reactions: [],
        can: {
            update: false,
            quote: false,
            delete: false,
            restore: false,
            react: false,
            report: false,
            hide: false,
            approve: false,
            mark_solution: false,
        },
        ...overrides,
    };
}

const allowed = (...abilities: (keyof Post['can'])[]) =>
    Object.fromEntries(
        Object.keys(post().can).map((ability) => [
            ability,
            abilities.includes(ability as keyof Post['can']),
        ]),
    ) as Post['can'];

beforeEach(() => {
    router.post.mockClear();
    router.delete.mockClear();
    forms.length = 0;
});

describe('ThreadStatusBadges', () => {
    it('names every state worth knowing', () => {
        const wrapper = mount(ThreadStatusBadges, {
            props: {
                thread: {
                    is_pinned: true,
                    is_locked: true,
                    is_solved: true,
                    status: 'hidden',
                    is_deleted: true,
                },
            },
        });

        expect(
            wrapper
                .findAll('[data-test="status-badge"]')
                .map((badge) => badge.text()),
        ).toEqual(['Pinned', 'Locked', 'Solved', 'Hidden', 'Deleted']);
    });

    it('stays quiet for an ordinary thread', () => {
        const wrapper = mount(ThreadStatusBadges, {
            props: {
                thread: {
                    is_pinned: false,
                    is_locked: false,
                    is_solved: false,
                    status: 'pending_review',
                    is_deleted: false,
                },
            },
        });

        expect(
            wrapper
                .findAll('[data-test="status-badge"]')
                .map((badge) => badge.text()),
        ).toEqual(['Pending review']);
    });
});

describe('SubscriptionButton', () => {
    it('follows a thread', async () => {
        const wrapper = mount(SubscriptionButton, {
            props: { threadSlug: 'hilo', subscribed: false },
        });

        expect(wrapper.text()).toBe('Follow');
        expect(wrapper.get('button').attributes('aria-pressed')).toBe('false');

        await wrapper.get('button').trigger('click');

        expect(router.post).toHaveBeenCalledWith(
            '/t/hilo/subscription',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('stops following it', async () => {
        const wrapper = mount(SubscriptionButton, {
            props: { threadSlug: 'hilo', subscribed: true },
        });

        expect(wrapper.text()).toBe('Following');

        await wrapper.get('button').trigger('click');

        expect(router.delete).toHaveBeenCalledWith(
            '/t/hilo/subscription',
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});

describe('PostActions', () => {
    function actions(overrides: Partial<Post> = {}, props = {}) {
        return mount(PostActions, {
            props: {
                post: post(overrides),
                threadSlug: 'hilo',
                canManageSolution: false,
                ...props,
            },
            global: { stubs: { ReportDialog: true } },
        });
    }

    it('offers nothing to someone who may do nothing', () => {
        expect(actions().findAll('button')).toHaveLength(0);
    });

    it('quotes and edits through events', async () => {
        const wrapper = actions({ can: allowed('quote', 'update') });

        await wrapper.get('[data-test="post-action-quote"]').trigger('click');
        await wrapper.get('[data-test="post-action-edit"]').trigger('click');

        expect(wrapper.emitted('quote')).toHaveLength(1);
        expect(wrapper.emitted('edit')).toHaveLength(1);
    });

    it('marks and unmarks the solution', async () => {
        const marking = actions({ can: allowed('mark_solution') });
        await marking
            .get('[data-test="post-action-solution"]')
            .trigger('click');

        expect(router.post).toHaveBeenCalledWith(
            '/t/hilo/solution',
            { post_id: 9 },
            expect.objectContaining({ preserveScroll: true }),
        );

        const unmarking = actions(
            { is_solution: true },
            { canManageSolution: true },
        );
        await unmarking
            .get('[data-test="post-action-unsolve"]')
            .trigger('click');

        expect(router.delete).toHaveBeenCalledWith(
            '/t/hilo/solution',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('moderates the post', async () => {
        const wrapper = actions({ can: allowed('hide', 'approve', 'restore') });

        await wrapper.get('[data-test="post-action-hide"]').trigger('click');
        await wrapper.get('[data-test="post-action-approve"]').trigger('click');
        await wrapper.get('[data-test="post-action-restore"]').trigger('click');

        expect(router.post.mock.calls.map((call) => call[0])).toEqual([
            '/moderation/posts/9/hide',
            '/moderation/posts/9/approve',
            '/moderation/posts/9/restore',
        ]);
    });

    it('deletes after a confirmation', async () => {
        // happy-dom has no window.confirm to spy on.
        const confirm = vi.fn();
        vi.stubGlobal('confirm', confirm);
        const wrapper = actions({ can: allowed('delete') });

        confirm.mockReturnValueOnce(false);
        await wrapper.get('[data-test="post-action-delete"]').trigger('click');

        expect(confirm).toHaveBeenCalledWith('Delete this post?');
        expect(router.delete).not.toHaveBeenCalled();

        confirm.mockReturnValueOnce(true);
        await wrapper.get('[data-test="post-action-delete"]').trigger('click');

        expect(router.delete).toHaveBeenCalledWith(
            '/posts/9',
            expect.objectContaining({ preserveScroll: true }),
        );

        vi.unstubAllGlobals();
    });

    it('asks before deleting the whole thread, through the thread', async () => {
        const confirm = vi.fn(() => false);
        vi.stubGlobal('confirm', confirm);
        const wrapper = actions({ is_first: true, can: allowed('delete') });

        await wrapper.get('[data-test="post-action-delete"]').trigger('click');

        expect(confirm).toHaveBeenCalledWith('Delete the whole thread?');
        expect(router.delete).not.toHaveBeenCalled();

        confirm.mockReturnValueOnce(true);
        await wrapper.get('[data-test="post-action-delete"]').trigger('click');

        expect(router.delete).toHaveBeenCalledWith(
            '/t/hilo',
            expect.anything(),
        );

        vi.unstubAllGlobals();
    });

    it('opens the report form', async () => {
        const wrapper = actions({ can: allowed('report') });

        expect(
            wrapper.getComponent({ name: 'ReportDialog' }).props('open'),
        ).toBe(false);

        await wrapper.get('[data-test="post-action-report"]').trigger('click');

        expect(
            wrapper.getComponent({ name: 'ReportDialog' }).props('open'),
        ).toBe(true);
    });
});

describe('ThreadModerationMenu', () => {
    const thread = {
        slug: 'hilo',
        is_pinned: false,
        is_locked: true,
        is_deleted: false,
        category: { id: 1, name: 'General', slug: 'general', color: '#000' },
    };
    const can = { pin: true, lock: true, move: true, restore: false };

    it('pins, unlocks and opens the move dialog', async () => {
        const wrapper = mount(ThreadModerationMenu, {
            props: { thread, can, moveTargets: [{ id: 1, name: 'General' }] },
            global: { stubs: { MoveThreadDialog: true } },
        });

        expect(
            wrapper
                .findAll('[data-test="moderation-action"]')
                .map((action) => action.text()),
        ).toEqual(['Pin', 'Unlock', 'Move']);

        await wrapper
            .findAll('[data-test="moderation-action"]')[0]
            .trigger('click');
        await wrapper
            .findAll('[data-test="moderation-action"]')[1]
            .trigger('click');

        expect(router.post.mock.calls.map((call) => call[0])).toEqual([
            '/moderation/threads/hilo/pin',
            '/moderation/threads/hilo/unlock',
        ]);

        await wrapper
            .findAll('[data-test="moderation-action"]')[2]
            .trigger('click');

        expect(
            wrapper.getComponent({ name: 'MoveThreadDialog' }).props('open'),
        ).toBe(true);
    });

    it('restores a deleted thread', async () => {
        const wrapper = mount(ThreadModerationMenu, {
            props: {
                thread: { ...thread, is_pinned: true, is_deleted: true },
                can: { pin: false, lock: false, move: false, restore: true },
                moveTargets: [],
            },
        });

        expect(
            wrapper
                .findAll('[data-test="moderation-action"]')
                .map((action) => action.text()),
        ).toEqual(['Restore']);

        await wrapper.get('[data-test="moderation-action"]').trigger('click');

        expect(router.post).toHaveBeenCalledWith(
            '/moderation/threads/hilo/restore',
            {},
            expect.anything(),
        );
    });

    it('is not there for members', () => {
        const wrapper = mount(ThreadModerationMenu, {
            props: {
                thread,
                can: { pin: false, lock: false, move: false, restore: false },
                moveTargets: [],
            },
        });

        expect(wrapper.find('[data-test="moderation-action"]').exists()).toBe(
            false,
        );
    });
});

describe('MoveThreadDialog', () => {
    it('moves the thread to the chosen category', async () => {
        document.body.innerHTML = '';
        const wrapper = mount(MoveThreadDialog, {
            props: {
                open: true,
                threadSlug: 'hilo',
                currentCategoryId: 1,
                targets: [
                    { id: 1, name: 'General' },
                    { id: 2, name: 'Laravel' },
                ],
                'onUpdate:open': () => {},
            },
            attachTo: document.body,
        });
        await flushPromises();

        const select = document.querySelector<HTMLSelectElement>(
            '[data-test="move-target"]',
        )!;

        expect(
            Array.from(select.options).map((option) => [
                option.text.trim(),
                option.disabled,
            ]),
        ).toEqual([
            ['General', true],
            ['Laravel', false],
        ]);

        select.value = '2';
        select.dispatchEvent(new Event('change'));
        await flushPromises();
        document
            .querySelector<HTMLFormElement>('[data-test="move-form"]')!
            .dispatchEvent(new Event('submit'));
        await flushPromises();

        const form = forms.at(-1)!;

        expect(form.category_id).toBe(2);
        expect(form.post).toHaveBeenCalledWith(
            '/moderation/threads/hilo/move',
            expect.objectContaining({ preserveScroll: true }),
        );

        succeed(form.post);

        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);

        wrapper.unmount();
    });
});

describe('PostCard', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-10-22T12:00:00Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    function card(overrides: Partial<Post> = {}, props = {}) {
        return mount(PostCard, {
            props: {
                post: post(overrides),
                threadSlug: 'hilo',
                canManageSolution: false,
                focused: false,
                ...props,
            },
            global: { stubs: { ReportDialog: true, PostReactions: true } },
        });
    }

    it('shows the post with its author', () => {
        const wrapper = card({ edited_at: '2026-10-22T11:00:00+00:00' });

        expect(wrapper.attributes('id')).toBe('post-9');
        expect(wrapper.get('[data-test="post-author"]').text()).toBe(
            'Luis Pérez',
        );
        expect(
            wrapper.get('[data-test="post-author"]').attributes('href'),
        ).toBe('/u/luis');
        expect(wrapper.text()).toContain('Moderator');
        expect(wrapper.text()).toContain('2 hours ago');
        expect(wrapper.get('[data-test="edited"]').text()).toBe('edited');
        expect(wrapper.get('[data-test="post-body"]').html()).toContain(
            '<code>queue:work</code>',
        );
    });

    it('credits erased accounts to «Deleted user», without a link', () => {
        const wrapper = card({ author: null });

        expect(wrapper.find('a[data-test="post-author"]').exists()).toBe(false);
        expect(wrapper.get('[data-test="post-author"]').text()).toBe(
            'Deleted user',
        );
    });

    it('marks the solution, the answered post and what the public cannot see', () => {
        const wrapper = card({
            is_solution: true,
            status: 'hidden',
            is_deleted: true,
            author: null,
            reply_to: { id: 3, author: null },
        });

        expect(wrapper.get('[data-test="solution-badge"]').text()).toBe(
            'Solution',
        );
        expect(
            wrapper
                .findAll('[data-test="post-state"]')
                .map((state) => state.text()),
        ).toEqual(['Hidden', 'Deleted']);
        expect(wrapper.text()).toContain('Deleted user');
        expect(wrapper.get('[data-test="reply-to"]').attributes('href')).toBe(
            '#post-3',
        );
    });

    it('highlights the post it was opened at', () => {
        expect(card({}, { focused: true }).attributes('data-focused')).toBe(
            'true',
        );
    });

    it('edits the post in place', async () => {
        const wrapper = card({ can: allowed('update') });

        await wrapper.get('[data-test="post-action-edit"]').trigger('click');
        const form = forms.at(-1)!;

        expect(wrapper.get('textarea').element.value).toBe(
            'Prueba con `queue:work`.',
        );

        await wrapper.get('textarea').setValue('Texto corregido');
        await wrapper.get('[data-test="edit-form"]').trigger('submit');

        expect(form.body).toBe('Texto corregido');
        expect(form.patch).toHaveBeenCalledWith(
            '/posts/9',
            expect.objectContaining({ preserveScroll: true }),
        );

        succeed(form.patch);
        await flushPromises();

        expect(wrapper.find('[data-test="edit-form"]').exists()).toBe(false);

        await wrapper.get('[data-test="post-action-edit"]').trigger('click');
        await wrapper.get('[data-test="cancel-edit"]').trigger('click');

        expect(wrapper.find('[data-test="edit-form"]').exists()).toBe(false);
    });

    it('passes a quote request up with the post', async () => {
        const wrapper = card({ can: allowed('quote') });

        await wrapper.get('[data-test="post-action-quote"]').trigger('click');

        expect(wrapper.emitted('quote')?.[0]?.[0]).toMatchObject({ id: 9 });
    });
});
