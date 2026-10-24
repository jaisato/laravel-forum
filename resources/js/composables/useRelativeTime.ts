import { useIntervalFn, useNow } from '@vueuse/core';
import type { ComputedRef, MaybeRefOrGetter } from 'vue';
import { computed, toValue } from 'vue';

const UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 365 * 24 * 60 * 60],
    ['month', 30 * 24 * 60 * 60],
    ['week', 7 * 24 * 60 * 60],
    ['day', 24 * 60 * 60],
    ['hour', 60 * 60],
    ['minute', 60],
];

/**
 * "5 minutes ago" in the given language, with "yesterday" and "now" where
 * the language has them.
 */
export function formatRelativeTime(
    date: Date,
    now: Date,
    locale: string,
): string {
    const seconds = Math.round((date.getTime() - now.getTime()) / 1000);
    const formatter = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });

    for (const [unit, size] of UNITS) {
        if (Math.abs(seconds) >= size) {
            return formatter.format(Math.trunc(seconds / size), unit);
        }
    }

    return formatter.format(0, 'second');
}

/**
 * A relative date that keeps up with the clock (refreshed every minute),
 * plus the absolute date for tooltips.
 */
export function useRelativeTime(
    datetime: MaybeRefOrGetter<string>,
    locale: MaybeRefOrGetter<string>,
): { relative: ComputedRef<string>; absolute: ComputedRef<string> } {
    // vueuse 15 took the cadence out of useNow: it is a scheduler now.
    const now = useNow({
        scheduler: (update) => useIntervalFn(update, 60_000),
    });
    const date = computed(() => new Date(toValue(datetime)));

    return {
        relative: computed(() =>
            formatRelativeTime(date.value, now.value, toValue(locale)),
        ),
        absolute: computed(() =>
            new Intl.DateTimeFormat(toValue(locale), {
                dateStyle: 'long',
                timeStyle: 'short',
            }).format(date.value),
        ),
    };
}
