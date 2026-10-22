import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
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
            command: process.env.WAYFINDER_COMMAND ?? 'php artisan wayfinder:generate',
        }),
    ],
    server: {
        watch: {
            ignored: ['**/vendor/**', '**/storage/**', '**/.superpowers/**'],
        },
    },
});
