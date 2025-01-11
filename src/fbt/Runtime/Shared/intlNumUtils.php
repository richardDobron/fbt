<?php

namespace fbt\Runtime\Shared;

use fbt\Lib\NumberFormatConsts;

class intlNumUtils
{
    protected static $config = [];
    const DEFAULT_GROUPING_SIZE = 3;
    const CURRENCIES_WITH_DOTS = [
        "\u{0433}\u{0440}\u{043d}.",
        "\u{0434}\u{0435}\u{043d}.",
        "\u{043b}\u{0432}.",
        "\u{043c}\u{0430}\u{043d}.",
        "\u{0564}\u{0580}.",
        "\u{062c}.\u{0645}.",
        "\u{062f}.\u{0625}.",
        "\u{062f}.\u{0627}.",
        "\u{062f}.\u{0628}.",
        "\u{062f}.\u{062a}.",
        "\u{062f}.\u{062c}.",
        "\u{062f}.\u{0639}.",
        "\u{062f}.\u{0643}.",
        "\u{062f}.\u{0644}.",
        "\u{062f}.\u{0645}.",
        "\u{0631}.\u{0633}.",
        "\u{0631}.\u{0639}.",
        "\u{0631}.\u{0642}.",
        "\u{0631}.\u{064a}.",
        "\u{0644}.\u{0633}.",
        "\u{0644}.\u{0644}.",
        "\u{0783}.",
        'B\/.',
        'Bs.',
        'Fr.',
        'kr.',
        'L.',
        'p.',
        'S\/.',
    ];

    public static function config($config = null)
    {
        $locale = FbtHooks::locale();

        if ($config !== null) {
            self::$config[$locale] = array_merge(NumberFormatConsts::get($locale), $config);
        }

        return self::$config[$locale] ?? NumberFormatConsts::get($locale);
    }

    /**
     * Format a number for string output.
     *
     * This will format a given number according to the user's locale.
     * Thousand delimiters will NOT be added, use
     * `formatNumberWithThousandDelimiters` if you want them to be added.
     *
     * You may optionally specify the number of decimal places that should
     * be displayed. For instance, pass `0` to round to the nearest
     * integer, `2` to round to nearest cent when displaying currency, etc.
     */
    public static function formatNumber(float $value, $decimals = null): string
    {
        $numberFormatConfig = self::config();

        return self::formatNumberRaw($value, $decimals, '', $numberFormatConfig['decimalSeparator'], $numberFormatConfig['minDigitsForThousandsSeparator'], $numberFormatConfig['standardDecimalPatternInfo'], $numberFormatConfig['numberingSystemData']);
    }

    /**
     * Format a number for string output.
     *
     * Calling this function directly is discouraged, unless you know
     * exactly what you're doing. Consider using `formatNumber` or
     * `formatNumberWithThousandDelimiters` below.
     */
    public static function formatNumberRaw(
        $value,
        $decimals = null,
        string $thousandDelimiter = '',
        string $decimalDelimiter = '.',
        int $minDigitsForThousandDelimiter = 0,
        array $standardPatternInfo = [
            'primaryGroupSize' => self::DEFAULT_GROUPING_SIZE,
            'secondaryGroupSize' => self::DEFAULT_GROUPING_SIZE,
        ],
        $numberingSystemData = null
    ): string {
        $primaryGroupingSize = $standardPatternInfo['primaryGroupSize'] ?? self::DEFAULT_GROUPING_SIZE;
        $secondaryGroupingSize = $standardPatternInfo['secondaryGroupSize'] ?? $primaryGroupingSize;

        $digits = $numberingSystemData['digits'] ?? null;

        if (is_float($value) && is_nan($value)) {
            $v = 0;
        } elseif ($decimals === null) {
            $v = (string)$value;
        } elseif (is_string($value)) {
            $v = self::truncateLongNumber($value, $decimals);
        } else {
            $v = self::_roundNumber($value, $decimals);
        }

        $valueParts = explode('.', $v);
        $wholeNumber = $valueParts[0];
        $decimal = $valueParts[1] ?? null;

        if (abs(mb_strlen(strval(intval($wholeNumber)))) >= $minDigitsForThousandDelimiter) {
            $replaceWith = '$1' . $thousandDelimiter . '$2$3';
            $primaryPattern = '(\\d)(\\d{' . ($primaryGroupingSize - 0) . '})($|\\D)';
            $replaced = preg_replace(self::_buildRegex($primaryPattern), $replaceWith, $wholeNumber);
            if ($replaced != $wholeNumber) {
                $wholeNumber = $replaced;
                $secondaryPatternString = '(\\d)(\\d{' . ($secondaryGroupingSize - 0) . '})(' . self::escapeRegex($thousandDelimiter) . ')';
                $secondaryPattern = self::_buildRegex($secondaryPatternString);
                while (($replaced = preg_replace($secondaryPattern, $replaceWith, $wholeNumber)) != $wholeNumber) {
                    $wholeNumber = $replaced;
                }
            }
        }
        if ($digits !== null) {
            $wholeNumber = self::_replaceWithNativeDigits($wholeNumber, $digits);
            if ($decimal) {
                $decimal = self::_replaceWithNativeDigits($decimal, $digits);
            }
        }

        $result = $wholeNumber;
        if ($decimal) {
            $result .= $decimalDelimiter . $decimal;
        }

        return $result;
    }

    /**
     * Format a number for string output.
     *
     * This will format a given number according to the user's locale.
     * Thousand delimiters will be added. Use `formatNumber` if you don't
     * want them to be added.
     *
     * You may optionally specify the number of decimal places that should
     * be displayed. For instance, pass `0` to round to the nearest
     * integer, `2` to round to nearest cent when displaying currency, etc.
     */
    public static function formatNumberWithThousandDelimiters(float $value, $decimals = null): string
    {
        $numberFormatConfig = self::config();

        return self::formatNumberRaw(
            $value,
            $decimals,
            $numberFormatConfig["numberDelimiter"],
            $numberFormatConfig["decimalSeparator"],
            $numberFormatConfig["minDigitsForThousandsSeparator"],
            $numberFormatConfig["standardDecimalPatternInfo"],
            $numberFormatConfig["numberingSystemData"]
        );
    }

    /**
     * Format a number for string output.
     *
     * This will format a given number according to the specified significant
     * figures.
     *
     * Also, specify the number of decimal places that should
     * be displayed. For instance, pass `0` to round to the nearest
     * integer, `2` to round to nearest cent when displaying currency, etc.
     *
     * Example:
     * > formatNumberWithLimitedSigFig(123456789, 0, 2)
     * "120,000,000"
     * > formatNumberWithLimitedSigFig(1.23456789, 2, 2)
     * "1.20"
     */
    public static function formatNumberWithLimitedSigFig(float $value, $decimals, int $numSigFigs): string
    {
        // First make the number sufficiently integer-like.
        $power = self::_getNumberOfPowersOfTen((int) $value);
        $inflatedValue = $value;
        if ($power < $numSigFigs) {
            $inflatedValue = $value * pow(10, -$power + $numSigFigs);
        }
        // Now that we have a large enough integer, round to cut off some digits.
        $roundTo = pow(10, self::_getNumberOfPowersOfTen((int) $inflatedValue) - $numSigFigs + 1);
        $truncatedValue = round($inflatedValue / $roundTo) * $roundTo;
        // Bring it back to whatever the number's magnitude was before.
        if ($power < $numSigFigs) {
            $truncatedValue /= pow(10, -$power + $numSigFigs);
            // Determine number of decimals based on sig figs
            if ($decimals === null) {
                return self::formatNumberWithThousandDelimiters($truncatedValue, $numSigFigs - $power - 1);
            }
        }

        // Decimals
        return self::formatNumberWithThousandDelimiters($truncatedValue, $decimals);
    }

    public static function parseNumber(string $text)
    {
        $numberFormatConfig = self::config();

        return self::parseNumberRaw($text, $numberFormatConfig['decimalSeparator'] ?? '.', $numberFormatConfig['numberDelimiter']);
    }

    /**
     * Parse a number.
     *
     * If the number is preceded or followed by a currency symbol or other
     * letters, they will be ignored.
     *
     * A decimal delimiter should be passed to respect the user's locale.
     *
     * Calling this function directly is discouraged, unless you know
     * exactly what you're doing. Consider using `parseNumber` below.
     */
    public static function parseNumberRaw(string $text, string $decimalDelimiter, string $numberDelimiter = '')
    {
        // Replace numerals based on current locale data
        $digitsMap = self::_getNativeDigitsMap();
        $_text = $text;
        if ($digitsMap) {
            $_text = trim(implode('', array_map(function ($character) use ($digitsMap) {
                return $digitsMap[$character] ?? $character;
            }, mb_str_split($text))));
        }

        $_text = preg_replace("/^[^\d]*\-/", "\u{0002}", $_text); // preserve negative sign
        $_text = preg_replace(self::matchCurrenciesWithDots(), '', $_text); // remove some currencies

        $decimalExp = self::escapeRegex($decimalDelimiter);
        $numberExp = self::escapeRegex($numberDelimiter);

        $isThereADecimalSeparatorInBetween = self::_buildRegex('^[^\\d]*\\d.*' . $decimalExp . '.*\\d[^\\d]*$');
        if (! preg_match($isThereADecimalSeparatorInBetween, $_text)) {
            $isValidWithDecimalBeforeHand = self::_buildRegex('(^[^\\d]*)' . $decimalExp . '(\\d*[^\\d]*$)');
            if (preg_match($isValidWithDecimalBeforeHand, $_text)) {
                $_text = preg_replace($isValidWithDecimalBeforeHand, "$1\u{0001}$2", $_text);

                return self::_parseCodifiedNumber($_text);
            }
            $isValidWithoutDecimal = self::_buildRegex('^[^\\d]*[\\d ' . self::escapeRegex($numberExp) . ']*[^\\d]*$');
            if (! preg_match($isValidWithoutDecimal, $_text)) {
                $_text = '';
            }

            return self::_parseCodifiedNumber($_text);
        }
        $isValid = self::_buildRegex('(^[^\\d]*[\\d ' . $numberExp . ']*)' . $decimalExp . '(\\d*[^\\d]*$)');
        $_text = preg_match($isValid, $_text) ? preg_replace($isValid, "$1\u{0001}$2", $_text) : '';

        return self::_parseCodifiedNumber($_text);
    }

    public static function truncateLongNumber(string $number, int $decimals = null): string
    {
        $pos = strpos($number, '.');
        $dividend = $pos === false ? $number : substr($number, 0, $pos);
        $remainder = $pos === false ? '' : substr($number, $pos + 1);

        return $decimals !== null ? $dividend . '.' . self::addZeros(substr($remainder, 0, $decimals), $decimals - strlen($remainder)) : $dividend;
    }

    /**
     * Converts a float into a prettified string. e.g. 1000.5 => "1,000.5"
     *
     * @deprecated Use `intlNumber::formatNumberWithThousandDelimiters(num)`
     * instead. It automatically handles decimal and thousand delimiters and
     * gets edge cases for Norwegian and Spanish right.
     *
     */
    public static function getFloatString(float $num, string $thousandDelimiter, string $decimalDelimiter): string
    {
        $str = (string)$num;
        $pieces = explode('.', $str);

        $intPart = self::getIntegerString($pieces[0], $thousandDelimiter);
        if (count($pieces) === 1) {
            return $intPart;
        }

        return $intPart . $decimalDelimiter . $pieces[1];
    }

    /**
     * Converts an integer into a prettified string. e.g. 1000 => "1,000"
     *
     * @deprecated Use `intlNumber::formatNumberWithThousandDelimiters(num, 0)`
     * instead. It automatically handles decimal thousand delimiters and gets
     * edge cases for Norwegian and Spanish right.
     *
     */
    public static function getIntegerString(int $num, string $thousandDelimiter): string
    {
        $delim = $thousandDelimiter;
        if ($delim === '') {
            //if (__DEV__) {
            //    throw new \Exception('thousandDelimiter cannot be empty string!');
            //}
            $delim = ',';
        }

        $str = (string)$num;
        $regex = "/(\d+)(\d{3})/";
        while (preg_match($regex, $str)) {
            $str = preg_replace($regex, '$1' . $delim . '$2', $str);
        }

        return $str;
    }

    public static function addZeros(string $x, int $count): string
    {
        $result = $x;
        if ($count > 0) {
            $result .= str_repeat('0', $count);
        }

        return $result;
    }

    public static function _roundNumber(string $valueParam, $decimalsParam = null): string
    {
        $decimals = $decimalsParam ?? 0;
        $pow = 10 ** $decimals;
        $value = $valueParam;
        $value = round($value * $pow) / $pow;
        $value = (string)$value;
        if (! $decimals) {
            return $value;
        }

        // if value is small and
        // was converted to scientific notation, don't append anything
        // as we are already done
        if (strstr($value, 'E-')) {
            return $value;
        }

        $pos = strpos($value, '.');

        if ($pos === false) {
            $value .= '.';
            $zeros = $decimals;
        } else {
            $zeros = $decimals - (strlen($value) - $pos - 1);
        }
        for ($i = 0, $l = $zeros; $i < $l; $i++) {
            $value .= '0';
        }

        return $value;
    }

    /**
     * A codified number has \u0001 in the place of a decimal separator and a
     * \u0002 in the place of a negative sign.
     */
    public static function _parseCodifiedNumber(string $text)
    {
        $_text = preg_replace("/[^0-9\u{0001}\u{0002}]/", '', $text);// decimal separator and negative sign
        $_text = preg_replace("/\u{0001}/", '.', $_text);// restore decimal separator
        $_text = preg_replace("/\u{0002}/", '-', $_text);// restore negative sign

        $value = floatval($_text);

        return $_text === '' || is_nan($value) ? null : $value;
    }

    public static function _getNativeDigitsMap()
    {
        $numberFormatConfig = intlNumUtils::config();
        $nativeDigitMap = [];
        $digits = $numberFormatConfig['numberingSystemData']['digits'] ?? $numberFormatConfig['numberingSystemData'];

        if ($digits == null) {
            return null;
        }

        foreach (mb_str_split($digits) as $i => $char) {
            $nativeDigitMap[$char] = (string)$i;
        }

        return $nativeDigitMap;
    }

    public static function matchCurrenciesWithDots()
    {
        return self::_buildRegex(array_reduce(intlNumUtils::CURRENCIES_WITH_DOTS, function ($regex, $representation) {
            return $regex . ($regex ? '|' : '') . '(' . $representation . ')';
        }, ''));
    }

    /**
     * Escapes regex special characters from a string, so it can be
     * used as a raw search term inside an actual regex.
     */
    public static function escapeRegex(string $str): string
    {
        return preg_quote($str, '/');
    }

    protected static function _buildRegex(string $pattern)
    {
        static $_regexCache;

        if (! isset($_regexCache[$pattern])) {
            $_regexCache[$pattern] = '/' . $pattern . '/iu';
        }

        return $_regexCache[$pattern];
    }

    protected static function _replaceWithNativeDigits(string $number, string $digits): string
    {
        $result = '';
        $digitsArray = mb_str_split($digits);

        for ($i = 0; $i < mb_strlen($number); $i++) {
            $char = mb_substr($number, $i, 1);
            $charCode = ord($char);
            if ($charCode >= 48 && $charCode <= 57) {
                $nativeDigit = $digitsArray[$charCode - 48] ?? null;
                $result .= $nativeDigit !== null ? $nativeDigit : $char;
            } else {
                $result .= $char;
            }
        }

        return $result;
    }

    /**
     * Calculate how many powers of 10 there are in a given number
     * I.e. 1.23 has 0, 100 and 999 have 2, and 1000 has 3.
     * Used in the inflation and rounding calculations below.
     */
    protected static function _getNumberOfPowersOfTen(int $value): float
    {
        if ($value === 0) {
            return 0;
        }

        return floor(log10(abs($value)));
    }
}
