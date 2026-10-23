import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'happy-dom',
        globals: true,
        include: ['tests/js/**/*.spec.ts'],
        setupFiles: ['tests/js/setup.ts'],
        restoreMocks: true,
        coverage: {
            provider: 'v8',
            reporter: ['text-summary', 'html', 'clover'],
            reportsDirectory: 'coverage/js',
            include: [
                'resources/js/components/forum/**',
                'resources/js/composables/**',
                'resources/js/lib/**',
            ],
            thresholds: {
                lines: 90,
                statements: 90,
                functions: 90,
                branches: 85,
            },
        },
    },
});
