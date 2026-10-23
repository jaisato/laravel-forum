import { config } from '@vue/test-utils';

// Components translate their copy with laravel-vue-i18n's `$t`/`trans`.
// Tests assert on the English keys, which is exactly what the library
// returns for a key that has no translation loaded.
config.global.mocks = {
    $t: (key: string, replacements: Record<string, string | number> = {}) =>
        Object.entries(replacements).reduce(
            (text, [name, value]) => text.replace(`:${name}`, String(value)),
            key,
        ),
};
