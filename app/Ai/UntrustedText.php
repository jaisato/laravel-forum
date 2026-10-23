<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * Members' text on its way to a model, between XML-like tags the
 * instructions tell the model to read as data, never as instructions (spec
 * §4.6). Every < and > of the text is escaped, so it can neither close its
 * tag nor open another.
 */
final class UntrustedText
{
    /**
     * @param  array<string, string>  $attributes  set by the forum, not by members
     */
    public static function wrap(string $tag, string $text, array $attributes = []): string
    {
        $attributes = implode('', array_map(
            fn (string $name, string $value): string => " {$name}=\"{$value}\"",
            array_keys($attributes),
            $attributes,
        ));

        return "<{$tag}{$attributes}>\n".self::escape($text)."\n</{$tag}>";
    }

    private static function escape(string $text): string
    {
        return str_replace(['<', '>'], ['&lt;', '&gt;'], $text);
    }
}
