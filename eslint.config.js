import {
    defineConfigWithVueTs,
    vueTsConfigs,
} from '@vue/eslint-config-typescript';
import prettier from 'eslint-config-prettier';
import vue from 'eslint-plugin-vue';

export default defineConfigWithVueTs(
    {
        ignores: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'coverage/**',
            'storage/**',
            'resources/js/actions/**',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
            'resources/js/components/ui/**',
            'eslint.config.js',
        ],
    },
    vue.configs['flat/recommended'],
    vueTsConfigs.recommended,
    {
        rules: {
            // The starter kit's components use camelCase props and the
            // `#default` slot shorthand; keep them rather than churn its code.
            'vue/multi-word-component-names': 'off',
            'vue/require-default-prop': 'off',
            'vue/attribute-hyphenation': 'off',
            'vue/v-on-event-hyphenation': 'off',
            'vue/v-slot-style': 'off',
            '@typescript-eslint/no-explicit-any': 'error',
            '@typescript-eslint/consistent-type-imports': [
                'error',
                { prefer: 'type-imports', fixStyle: 'separate-type-imports' },
            ],
        },
    },
    prettier,
);
