import { useHttp, usePage } from '@inertiajs/vue3';
import { defaultDocument, useEventListener, useIntervalFn } from '@vueuse/core';
import type { Ref } from 'vue';
import { ref, watch } from 'vue';
import { unread } from '@/routes/notifications';

/** How often the bell asks for the unread count while the page is visible. */
export const UNREAD_POLL_INTERVAL = 60_000;

/**
 * The signed in member's unread notifications, for the bell. Every page
 * brings the count (auth.user); in between, a light request refreshes it
 * every minute while the page is visible, instead of WebSockets (spec
 * §15). Partial reloads of the page would rerun its controller, so the
 * bell asks an endpoint of its own.
 */
export function useUnreadNotifications(): { count: Ref<number> } {
    const page = usePage();
    const http = useHttp<Record<string, never>, { count: number }>({});
    const count = ref(page.props.auth.user?.unread_notifications ?? 0);

    watch(
        () => page.props.auth.user?.unread_notifications,
        (value) => {
            count.value = value ?? 0;
        },
    );

    async function refresh(): Promise<void> {
        if (
            page.props.auth.user === null ||
            document.visibilityState !== 'visible'
        ) {
            return;
        }

        try {
            count.value = (await http.get(unread.url())).count;
        } catch {
            // A missed refresh is made up by the next one.
        }
    }

    useIntervalFn(refresh, UNREAD_POLL_INTERVAL);

    // Back to a tab left in the background: catch up at once. (No document
    // while rendering on the server.)
    useEventListener(defaultDocument, 'visibilitychange', () => {
        void refresh();
    });

    return { count };
}
