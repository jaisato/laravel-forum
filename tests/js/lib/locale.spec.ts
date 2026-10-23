import { initializeLocaleSync } from '@/lib/locale';

const handlers = vi.hoisted(() => new Map<string, (event: unknown) => void>());
const i18n = vi.hoisted(() => ({
    active: 'es',
    loadLanguageAsync: vi.fn(() => Promise.resolve()),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        on: (name: string, handler: (event: unknown) => void) => {
            handlers.set(name, handler);
        },
    },
}));

vi.mock('laravel-vue-i18n', () => ({
    getActiveLanguage: () => i18n.active,
    loadLanguageAsync: i18n.loadLanguageAsync,
}));

function navigate(locale: unknown): void {
    handlers.get('navigate')?.({ detail: { page: { props: { locale } } } });
}

describe('initializeLocaleSync', () => {
    beforeEach(() => {
        handlers.clear();
        i18n.active = 'es';
        i18n.loadLanguageAsync.mockClear();
        document.documentElement.lang = 'es';
        initializeLocaleSync();
    });

    it('loads the language of a page that arrives in another one', () => {
        navigate('en');

        expect(i18n.loadLanguageAsync).toHaveBeenCalledWith('en');
        expect(document.documentElement.lang).toBe('en');
    });

    it('does nothing when the language is the same or missing', () => {
        navigate('es');
        navigate(undefined);

        expect(i18n.loadLanguageAsync).not.toHaveBeenCalled();
        expect(document.documentElement.lang).toBe('es');
    });
});
