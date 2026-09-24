<?php

/**
 *  Core localization functions.
 */

namespace fbt\Runtime\Shared;

use fbt\Lib\IntlPhonologicalRewrites;
use fbt\Lib\IntlRedundantStops;

class IntlPunctuation
{
    /** @var array<string, array<int, array{0: string, 1: string|\Closure}>> */
    private static $rulesPerLocale = [];
    /** @var array<string, string>|null */
    private static $normalizedStops = null;
    /** @var array<string, array<string, true>>|null */
    private static $redundancies = null;

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
        "]*$/u";

    /**
     * Checks whether a string ends in sentence-final punctuation. This logic is
     * about the same as the PHP ends_in_punct() function; it takes into account
     * the fact that we consider a string like "foo." to end with a period even
     * though there's a quote mark afterward.
     *
     * @deprecated Removed from the upstream runtime; token substitution now
     *   uses self::dedupeStops() instead.
     */
    public static function endsInPunct(string $str): bool
    {
        if ($str === '') {
            return false;
        }

        return preg_match(self::ENDS_IN_PUNCT_REGEXP, $str) === 1;
    }

    /**
     * @return array<int, array{0: string, 1: string|\Closure}>
     */
    private static function _getMemoizedRules(?string $localeArg): array
    {
        $locale = $localeArg ?? '';

        if (! isset(self::$rulesPerLocale[$locale])) {
            self::$rulesPerLocale[$locale] = self::_getRules($localeArg);
        }

        return self::$rulesPerLocale[$locale];
    }

    /**
     * @return array<int, array{0: string, 1: string|\Closure}>
     */
    private static function _getRules(?string $locale): array
    {
        $rules = [];
        $rewrites = IntlPhonologicalRewrites::get($locale);

        // Process the patterns and replacements by applying metaclasses.
        foreach ($rewrites['patterns'] as $pattern => $replacement) {
            // "Metaclasses" are shorthand for larger character classes. For example,
            // _C may refer to consonants and _V to vowels for a locale.
            foreach ($rewrites['meta'] as $metaclass => $characterClass) {
                // js~php diff: metaclass names are plain literals, so a string
                // replacement is equivalent to the upstream global RegExp replacement
                $metaclassName = substr($metaclass, 1, -1);
                $pattern = str_replace($metaclassName, $characterClass, $pattern);
                $replacement = str_replace($metaclassName, $characterClass, $replacement);
            }

            $pattern = substr($pattern, 1, -1);

            if ($replacement === 'javascript') {
                $replacement = function (array $match): string {
                    return mb_strtolower(mb_substr($match[0], 1));
                };
            } else {
                // js~php diff: JS keeps references to non-existent groups as literals
                $groupCount = self::_countCapturingGroups($pattern);
                $replacement = preg_replace_callback('/\$(\d)/', function (array $match) use ($groupCount): string {
                    return (int)$match[1] > $groupCount ? '\\' . $match[0] : $match[0];
                }, $replacement);
            }

            // js~php diff: JS RegExp `$` only matches at the very end (D modifier)
            $rules[] = ['/' . $pattern . '/uD', $replacement];
        }

        return $rules;
    }

    private static function _countCapturingGroups(string $pattern): int
    {
        $count = 0;
        $inClass = false;
        $length = strlen($pattern);

        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];

            if ($char === '\\') {
                $i++;
            } elseif ($inClass) {
                $inClass = $char !== ']';
            } elseif ($char === '[') {
                $inClass = true;
            } elseif ($char === '(' && ($pattern[$i + 1] ?? '') !== '?') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Applies phonological rules (appropriate to the locale)
     * at the morpheme boundary when tokens are replaced with values.
     * For languages like Turkish, we allow translators to use shorthand
     * for a pattern of inflection (a suffix like '(y)i becomes 'i or 'yi or 'a or
     * 'ye, etc. depending on context).
     *
     * Input: Translated string with each {token} substituted with
     *        "\x01value\x01" (e.g., "\x01Ozgur\x01(y)i..." which was
     *        "{name}(y)i...")
     * Returns: String with phonological rules applied (e.g., "Ozguri...")
     */
    public static function applyPhonologicalRules(string $text): string
    {
        $rules = self::_getMemoizedRules(FbtHooks::locale());
        $result = $text;

        foreach ($rules as [$regexp, $replacement]) {
            $result = $replacement instanceof \Closure
                ? preg_replace_callback($regexp, $replacement, $result)
                : preg_replace($regexp, $replacement, $result);
        }

        // If we have no rules (or if we already applied them), remove the delimiters.
        return str_replace("\x01", '', $result);
    }

    private static function _initStops(): void
    {
        if (self::$normalizedStops !== null) {
            return;
        }

        /**
         * Map all equivalencies to the normalized key for the stop category.  These
         * are the entries in the redundancy mapping
         */
        self::$normalizedStops = [];
        foreach (IntlRedundantStops::EQUIVALENCIES as $norm => $equivalencies) {
            foreach (array_merge([$norm], $equivalencies) as $eq) {
                self::$normalizedStops[$eq] = $norm;
            }
        }

        self::$redundancies = [];
        foreach (IntlRedundantStops::REDUNDANCIES as $prefix => $suffixes) {
            self::$redundancies[$prefix] = array_fill_keys($suffixes, true);
        }
    }

    private static function isRedundant(string $rawPrefix, string $rawSuffix): bool
    {
        self::_initStops();

        $prefix = self::$normalizedStops[$rawPrefix] ?? null;
        $suffix = self::$normalizedStops[$rawSuffix] ?? null;

        return $prefix !== null
            && $suffix !== null
            && isset(self::$redundancies[$prefix][$suffix]);
    }

    /**
     * If suffix is redundant with prefix (as determined by the redundancy map),
     * return the empty string, otherwise return suffix.
     */
    public static function dedupeStops(string $prefix, string $suffix): string
    {
        // We can naively grab the last "character" (a general Unicode "no-no") from
        // our string because we know our set of stops we test against have no
        // diacritics nor lie outside the BMP
        return self::isRedundant(mb_substr($prefix, -1), $suffix) ? '' : $suffix;
    }
}
