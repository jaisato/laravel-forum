<?php

declare(strict_types=1);

namespace App\Support;

final class Locale
{
    /**
     * The interface languages the forum ships translations for.
     *
     * @return list<string>
     */
    public static function supported(): array
    {
        /** @var list<string> $locales */
        $locales = config('forum.locales', ['es', 'en']);

        return $locales;
    }

    /**
     * The language used when no supported preference is expressed.
     */
    public static function default(): string
    {
        $configured = (string) config('app.locale');

        return self::isSupported($configured) ? $configured : self::supported()[0];
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::supported(), true);
    }
}
