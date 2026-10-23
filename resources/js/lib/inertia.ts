import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { i18nVue } from 'laravel-vue-i18n';
import type { App } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

/*
 * The createInertiaApp options that the browser entry (app.ts) and the
 * server entry (ssr.ts) share, so a page renders the same on both sides.
 */

export function title(pageTitle: string): string {
    const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

    return pageTitle ? `${pageTitle} - ${appName}` : appName;
}

export function layout(name: string) {
    if (name.startsWith('auth/')) {
        return AuthLayout;
    }

    if (name.startsWith('settings/')) {
        return [AppLayout, SettingsLayout];
    }

    return AppLayout;
}

export function withApp(
    app: App,
    { page }: { ssr: boolean; page: Page<PageProps & SharedPageProps> },
): void {
    app.use(i18nVue, {
        lang: page.props.locale,
        fallbackLang: 'en',
        // Eager so translations are available synchronously during SSR.
        resolve: (lang: string) => {
            const files = import.meta.glob<{
                default: Record<string, string>;
            }>('../../../lang/*.json', { eager: true });

            return files[`../../../lang/${lang}.json`]?.default ?? {};
        },
    });

    app.directive('focus', {
        mounted: (el: HTMLElement, shouldFocus) => {
            if (shouldFocus.value !== false) {
                el.focus();
            }
        },
    });
}
