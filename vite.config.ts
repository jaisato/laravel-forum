import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig(({ isSsrBuild }) => ({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
            // The Docker asset stage has no PHP: it receives the generated
            // files from the PHP stage and sets WAYFINDER_COMMAND to a no-op.
            command:
                process.env.WAYFINDER_COMMAND ??
                'php artisan wayfinder:generate',
        }),
    ],
    build: {
        rolldownOptions: {
            output: isSsrBuild
                ? {}
                : {
                      // Vue, Inertia and their helpers change far less often than
                      // the forum: in a chunk of their own they stay cached in
                      // browsers across deployments.
                      codeSplitting: {
                          groups: [
                              {
                                  name: 'vendor',
                                  test: /node_modules[\\/](vue|@vue|@inertiajs|es-toolkit|laravel-precognition)[\\/]/,
                              },
                          ],
                      },
                  },
        },
    },
    ssr: {
        // A self-contained bundle: the ssr image ships bootstrap/ssr alone,
        // without node_modules.
        noExternal: true,
    },
    server: {
        // compose.yaml runs Vite on 0.0.0.0 inside its container: the browser
        // reaches it through the published port on VITE_HMR_HOST (localhost),
        // the address laravel-vite-plugin writes to public/hot.
        hmr: process.env.VITE_HMR_HOST
            ? { host: process.env.VITE_HMR_HOST }
            : undefined,
        watch: {
            ignored: ['**/vendor/**', '**/storage/**', '**/.superpowers/**'],
        },
    },
}));
