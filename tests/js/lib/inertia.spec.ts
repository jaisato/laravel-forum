import { flushPromises } from '@vue/test-utils';
import { trans } from 'laravel-vue-i18n';
import { createApp, h, resolveDirective, withDirectives } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { layout, title, withApp } from '@/lib/inertia';

type SharedPage = Parameters<typeof withApp>[1]['page'];

const page = (locale: string) =>
    ({ props: { locale } }) as unknown as SharedPage;

afterEach(() => {
    vi.unstubAllEnvs();
    document.body.replaceChildren();
});

describe('the options shared by the browser and the SSR entries', () => {
    it('appends the application name to the page title', () => {
        vi.stubEnv('VITE_APP_NAME', 'Foro');

        expect(title('Inicio')).toBe('Inicio - Foro');
        expect(title('')).toBe('Foro');
    });

    it('falls back to Laravel without an application name', () => {
        vi.stubEnv('VITE_APP_NAME', '');

        expect(title('')).toBe('Laravel');
    });

    it.each([
        ['auth/Login', AuthLayout],
        ['settings/Profile', [AppLayout, SettingsLayout]],
        ['threads/Show', AppLayout],
    ])('wraps %s in its layout', (name, expected) => {
        expect(layout(name)).toEqual(expected);
    });

    it('translates the interface into the language of the page', async () => {
        const app = createApp(() => h('p', trans('Reply')));
        const root = document.createElement('div');

        withApp(app, { ssr: false, page: page('es') });
        app.mount(root);
        // The browser loads the language in a promise (SSR does it at once).
        await flushPromises();

        expect(root.textContent).toBe('Responder');

        app.unmount();
    });

    it('focuses the fields marked with v-focus unless told not to', () => {
        const app = createApp(() => {
            const focus = resolveDirective('focus')!;

            return [
                withDirectives(h('input', { id: 'first' }), [[focus, true]]),
                withDirectives(h('input', { id: 'second' }), [[focus, false]]),
            ];
        });
        const root = document.createElement('div');
        document.body.append(root);

        withApp(app, { ssr: false, page: page('en') });
        app.mount(root);

        expect(document.activeElement?.id).toBe('first');

        app.unmount();
    });
});
