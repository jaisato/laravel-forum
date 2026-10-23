import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import type { Ref } from 'vue';
import {
    UNREAD_POLL_INTERVAL,
    useUnreadNotifications,
} from '@/composables/useUnreadNotifications';

type Page = {
    props: { auth: { user: { unread_notifications: number } | null } };
};

const state = vi.hoisted(() => ({ page: null as unknown as Page }));
const http = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    state.page = reactive({
        props: { auth: { user: { unread_notifications: 2 } } },
    }) as Page;

    return { usePage: () => state.page, useHttp: () => http };
});

// Mounted components keep listening to the document until unmounted.
enableAutoUnmount(afterEach);

function setVisibility(value: DocumentVisibilityState): void {
    Object.defineProperty(document, 'visibilityState', {
        value,
        configurable: true,
    });
}

function withCount(): { count: Ref<number> } {
    let count!: Ref<number>;

    mount(
        defineComponent({
            setup() {
                count = useUnreadNotifications().count;

                return () => h('span', count.value);
            },
        }),
    );

    return { count };
}

describe('useUnreadNotifications', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        http.get.mockReset();
        http.get.mockResolvedValue({ count: 5 });
        state.page.props.auth.user = { unread_notifications: 2 };
        setVisibility('visible');
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('starts from the count the page brings', () => {
        expect(withCount().count.value).toBe(2);
        expect(http.get).not.toHaveBeenCalled();
    });

    it('asks the server again every now and then', async () => {
        const { count } = withCount();

        vi.advanceTimersByTime(UNREAD_POLL_INTERVAL);
        await flushPromises();

        expect(http.get).toHaveBeenCalledWith('/notifications/unread');
        expect(count.value).toBe(5);
    });

    it('takes the count of every new page', async () => {
        const { count } = withCount();

        state.page.props.auth.user = { unread_notifications: 0 };
        await nextTick();

        expect(count.value).toBe(0);
    });

    it('does not ask while the page is hidden, and asks once it is back', async () => {
        withCount();
        setVisibility('hidden');

        vi.advanceTimersByTime(UNREAD_POLL_INTERVAL * 3);
        await flushPromises();

        expect(http.get).not.toHaveBeenCalled();

        setVisibility('visible');
        document.dispatchEvent(new Event('visibilitychange'));
        await flushPromises();

        expect(http.get).toHaveBeenCalledTimes(1);
    });

    it('keeps the last count when the server does not answer', async () => {
        http.get.mockRejectedValue(new Error('Network error'));
        const { count } = withCount();

        vi.advanceTimersByTime(UNREAD_POLL_INTERVAL);
        await flushPromises();

        expect(count.value).toBe(2);
    });

    it('asks nothing once the member signs out', async () => {
        withCount();
        state.page.props.auth.user = null;
        await nextTick();

        vi.advanceTimersByTime(UNREAD_POLL_INTERVAL);
        await flushPromises();

        expect(http.get).not.toHaveBeenCalled();
    });
});
