import type { Ref } from 'vue';
import { onMounted, ref, watch } from 'vue';

export type Draft = {
    text: Ref<string>;
    clear: () => void;
};

/**
 * Text that survives a reload or a closed tab: it is kept in localStorage
 * under the key until clear() (after a successful submit). The saved draft
 * is restored once mounted, so the server and the first client render
 * produce the same HTML.
 */
export function useDraft(key: string, initial = ''): Draft {
    const storageKey = `forum:draft:${key}`;
    const text = ref(initial);

    watch(text, (value) => {
        if (value === initial || value === '') {
            window.localStorage.removeItem(storageKey);
        } else {
            window.localStorage.setItem(storageKey, value);
        }
    });

    onMounted(() => {
        const saved = window.localStorage.getItem(storageKey);

        if (saved !== null) {
            text.value = saved;
        }
    });

    function clear(): void {
        text.value = initial;
        window.localStorage.removeItem(storageKey);
    }

    return { text, clear };
}
