<?php

namespace fbt\Runtime\Shared;

class IntlPunctuation
{
    /**
     * Regular expression snippet containing all the characters that we
     * count as sentence-final punctuation.
     */
    public const PUNCT_CHAR_CLASS = '[.!?' .
        "\u{3002}" . // Chinese/Japanese period
        "\u{FF01}" . // Fullwidth exclamation point
        "\u{FF1F}" . // Fullwidth question mark
        "\u{0964}" . // Hindi "full stop"
        "\u{2026}" . // Chinese ellipsis
        "\u{0EAF}" . // Laotian ellipsis
        "\u{1801}" . // Mongolian ellipsis
        "\u{0E2F}" . // Thai ellipsis
        "\u{FF0E}" . // Fullwidth full stop
        ']';

    public const ENDS_IN_PUNCT_REGEXP = '/' .
        self::PUNCT_CHAR_CLASS .
        "[)\"'" .
        // JavaScript doesn't support Unicode character
        // properties in regexes, so we have to list
        // all of these individually. This is an
        // abbreviated list of the "final punctuation"
        // and "close punctuation" Unicode codepoints,
        // excluding symbols we're unlikely to ever
        // see (mathematical notation, etc.)
        "\u{00BB}" . // Double angle quote
        "\u{0F3B}" . // Tibetan close quote
        "\u{0F3D}" . // Tibetan right paren
        "\u{2019}" . // Right single quote
        "\u{201D}" . // Right double quote
        "\u{203A}" . // Single right angle quote
        "\u{3009}" . // Right angle bracket
        "\u{300B}" . // Right double angle bracket
        "\u{300D}" . // Right corner bracket
        "\u{300F}" . // Right hollow corner bracket
        "\u{3011}" . // Right lenticular bracket
        "\u{3015}" . // Right tortoise shell bracket
        "\u{3017}" . // Right hollow lenticular bracket
        "\u{3019}" . // Right hollow tortoise shell
        "\u{301B}" . // Right hollow square bracket
        "\u{301E}" . // Double prime quote
        "\u{301F}" . // Low double prime quote
        "\u{FD3F}" . // Ornate right parenthesis
        "\u{FF07}" . // Fullwidth apostrophe
        "\u{FF09}" . // Fullwidth right parenthesis
        "\u{FF3D}" . // Fullwidth right square bracket
        "\\s" .
        "]*$/";

    /**
     * Checks whether a string ends in sentence-final punctuation. This logic is
     * about the same as the PHP ends_in_punct() function; it takes into account
     * the fact that we consider a string like "foo." to end with a period even
     * though there's a quote mark afterward.
     */
    public static function endsInPunct($str): bool
    {
        if (! is_string($str)) {
            return false;
        }

        return preg_match(self::ENDS_IN_PUNCT_REGEXP, $str);
    }
}
