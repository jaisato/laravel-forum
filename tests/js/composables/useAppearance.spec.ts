import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import {
    initializeTheme,
    updateTheme,
    useAppearance,
} from '@/composables/useAppearance';
import type { UseAppearanceReturn } from '@/composables/useAppearance';

const listeners: Array<() => void> = [];
let systemDark = false;

function mockSystemTheme(): void {
    window.matchMedia = vi.fn().mockImplementation(() => ({
        get matches() {
            return systemDark;
        },
        addEventListener: (_: string, listener: () => void) =>
            listeners.push(listener),
    }));
}

function withAppearance(): UseAppearanceReturn {
    let appearance!: UseAppearanceReturn;

    mount(
        defineComponent({
            setup() {
                appearance = useAppearance();

                return () => h('div');
            },
        }),
    );

    return appearance;
}

const isDark = () => document.documentElement.classList.contains('dark');

describe('updateTheme', () => {
    beforeEach(() => {
        systemDark = false;
        mockSystemTheme();
        document.documentElement.classList.remove('dark');
    });

    it('applies an explicit choice', () => {
        updateTheme('dark');
        expect(isDark()).toBe(true);

        updateTheme('light');
        expect(isDark()).toBe(false);
    });

    it('follows the system preference', () => {
        systemDark = true;
        updateTheme('system');
        expect(isDark()).toBe(true);

        systemDark = false;
        updateTheme('system');
        expect(isDark()).toBe(false);
    });
});

describe('initializeTheme', () => {
    beforeEach(() => {
        systemDark = false;
        listeners.length = 0;
        mockSystemTheme();
        localStorage.clear();
        document.documentElement.classList.remove('dark');
    });

    it('restores the saved choice and keeps up with system changes', () => {
        localStorage.setItem('appearance', 'dark');
        initializeTheme();
        expect(isDark()).toBe(true);

        localStorage.removeItem('appearance');
        systemDark = false;
        listeners.forEach((listener) => listener());
        expect(isDark()).toBe(false);
    });
});

describe('useAppearance', () => {
    beforeEach(() => {
        systemDark = false;
        mockSystemTheme();
        localStorage.clear();
        document.cookie = 'appearance=; max-age=0; path=/';
    });

    it('starts from the saved choice', () => {
        localStorage.setItem('appearance', 'light');

        const { appearance, resolvedAppearance } = withAppearance();

        expect(appearance.value).toBe('light');
        expect(resolvedAppearance.value).toBe('light');
    });

    it('resolves the system choice and remembers a new one', () => {
        const { appearance, resolvedAppearance, updateAppearance } =
            withAppearance();

        appearance.value = 'system';
        systemDark = true;
        expect(resolvedAppearance.value).toBe('dark');

        updateAppearance('dark');

        expect(localStorage.getItem('appearance')).toBe('dark');
        expect(document.cookie).toContain('appearance=dark');
        expect(isDark()).toBe(true);
    });
});
