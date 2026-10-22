/**
 * A counter in the reader's language, abbreviated past a thousand ("1.2K",
 * "1,2 mil") so that listings stay narrow.
 */
export function formatCount(value: number, locale: string): string {
    return new Intl.NumberFormat(locale, {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
}
