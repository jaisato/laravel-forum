import { router } from '@inertiajs/vue3';
import { getActiveLanguage, loadLanguageAsync } from 'laravel-vue-i18n';

/**
 * Keeps the translations in step with the page: switching the language
 * (LocaleSwitcher) or signing in with another preferred language returns
 * pages with a new `locale` prop.
 */
export function initializeLocaleSync(): void {
    if (typeof window === 'undefined') {
        return;
    }

    router.on('navigate', (event) => {
        const locale = event.detail.page.props.locale;

        if (typeof locale === 'string' && locale !== getActiveLanguage()) {
            document.documentElement.lang = locale;
            void loadLanguageAsync(locale);
        }
    });
}
