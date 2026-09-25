<?php

namespace fbt\Runtime\Shared;

use fbt\FbtConfig;
use fbt\Lib\NumberFormatConsts;

class intlNumUtils
{
    protected static $config = [];
    public const DEFAULT_GROUPING_SIZE = 3;
    public const CURRENCIES_WITH_DOTS = [
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
        'B/.',
        'Bs.',
        'Fr.',
        'kr.',
        'L.',
        'p.',
        'S/.',
    ];

    /**
     * js~php diff: allows overriding the number format config of the current locale
     */
    public static function config(?array $config = null): ?array
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
     * Calling this function directly is discouraged, unless you know
     * exactly what you're doing. Consider using `formatNumber` or
     * `formatNumberWithThousandDelimiters` below.
     *
     * @param int|float|string $value
     */
    public static function formatNumberRaw(
        $value,
        ?int $decimals = null,
        string $thousandDelimiter = '',
        string $decimalDelimiter = '.',
        int $minDigitsForThousandDelimiter = 0,
        array $standardPatternInfo = [
            'primaryGroupSize' => self::DEFAULT_GROUPING_SIZE,
            'secondaryGroupSize' => self::DEFAULT_GROUPING_SIZE,
        ],
        ?array $numberingSystemData = null
    ): string {
        $primaryGroupingSize = ($standardPatternInfo['primaryGroupSize'] ?? null) ?: self::DEFAULT_GROUPING_SIZE;
        $secondaryGroupingSize = ($standardPatternInfo['secondaryGroupSize'] ?? null) ?: $primaryGroupingSize;

        $digits = $numberingSystemData['digits'] ?? null;

        if ($decimals === null) {
            $v = self::_toString($value);
        } elseif (is_string($value)) {
            $v = self::truncateLongNumber($value, $decimals);
        } else {
            $v = self::_roundNumber($value, $decimals);
        }

        $valueParts = explode('.', $v);
        $wholeNumber = $valueParts[0];
        $decimal = $valueParts[1] ?? null;

        if (mb_strlen(self::_toString(abs(self::_parseInt($wholeNumber)))) >= $minDigitsForThousandDelimiter) {
            $replaceWith = '$1' . $thousandDelimiter . '$2$3';
            $primaryPattern = '(\\d)(\\d{' . ($primaryGroupingSize - 0) . '})($|\\D)';
            $replaced = preg_replace(self::_buildRegex($primaryPattern), $replaceWith, $wholeNumber, 1);
            if ($replaced !== $wholeNumber) {
                $wholeNumber = $replaced;
                $secondaryPatternString = '(\\d)(\\d{' . ($secondaryGroupingSize - 0) . '})(' . escapeRegex::escapeRegex($thousandDelimiter) . ')';
                $secondaryPattern = self::_buildRegex($secondaryPatternString);
                while (($replaced = preg_replace($secondaryPattern, $replaceWith, $wholeNumber, 1)) !== $wholeNumber) {
                    $wholeNumber = $replaced;
                }
            }
        }
        if ($digits !== null) {
            $wholeNumber = self::_replaceWithNativeDigits($wholeNumber, $digits);
            if ($decimal !== null && $decimal !== '') {
                $decimal = self::_replaceWithNativeDigits($decimal, $digits);
            }
        }

        $result = $wholeNumber;
        if ($decimal !== null && $decimal !== '') {
            $result .= $decimalDelimiter . $decimal;
        }

        return $result;
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
     *
     * @param int|float|string $value
     */
    public static function formatNumber($value, ?int $decimals = null): string
    {
        $value = self::_toNumberIfNumericString($value);
        $numberFormatConfig = self::config();

        return self::formatNumberRaw(
            $value,
            $decimals,
            '',
            $numberFormatConfig['decimalSeparator'],
            $numberFormatConfig['minDigitsForThousandsSeparator'],
            $numberFormatConfig['standardDecimalPatternInfo'],
            $numberFormatConfig['numberingSystemData']
        );
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
     *
     * @param int|float|string $value
     */
    public static function formatNumberWithThousandDelimiters($value, ?int $decimals = null): string
    {
        $value = self::_toNumberIfNumericString($value);
        $numberFormatConfig = self::config();

        return self::formatNumberRaw(
            $value,
            $decimals,
            $numberFormatConfig['numberDelimiter'],
            $numberFormatConfig['decimalSeparator'],
            $numberFormatConfig['minDigitsForThousandsSeparator'],
            $numberFormatConfig['standardDecimalPatternInfo'],
            $numberFormatConfig['numberingSystemData']
        );
    }

    /**
     * Calculate how many powers of 10 there are in a given number
     * I.e. 1.23 has 0, 100 and 999 have 2, and 1000 has 3.
     * Used in the inflation and rounding calculations below.
     *
     * @param int|float $value
     *
     * @return int|float
     */
    protected static function _getNumberOfPowersOfTen($value)
    {
        // js~php diff: NaN is falsy in JS
        if (! $value || is_nan((float)$value)) {
            return $value;
        }

        return floor(log10(abs($value)));
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
     *
     * @param int|float $value
     */
    public static function formatNumberWithLimitedSigFig($value, ?int $decimals, int $numSigFigs): string
    {
        $value = self::_toNumberIfNumericString($value);

        // First make the number sufficiently integer-like.
        $power = self::_getNumberOfPowersOfTen($value);
        $inflatedValue = $value;
        if ($power < $numSigFigs) {
            $inflatedValue = $value * pow(10, -$power + $numSigFigs);
        }
        // Now that we have a large enough integer, round to cut off some digits.
        $roundTo = pow(10, self::_getNumberOfPowersOfTen($inflatedValue) - $numSigFigs + 1);
        $truncatedValue = self::_mathRound($inflatedValue / $roundTo) * $roundTo;
        // Bring it back to whatever the number's magnitude was before.
        if ($power < $numSigFigs) {
            $truncatedValue /= pow(10, -$power + $numSigFigs);
            // Determine number of decimals based on sig figs
            if ($decimals === null) {
                return self::formatNumberWithThousandDelimiters(
                    $truncatedValue,
                    (int)($numSigFigs - $power - 1)
                );
            }
        }

        // Decimals
        return self::formatNumberWithThousandDelimiters($truncatedValue, $decimals);
    }

    /**
     * @param int|float|string $valueParam
     */
    public static function _roundNumber($valueParam, ?int $decimalsParam = null): string
    {
        $decimals = $decimalsParam ?? 0;
        $pow = pow(10, $decimals);
        $value = self::_mathRound($valueParam * $pow) / $pow;
        $value = self::_toString($value);
        if (! $decimals) {
            return $value;
        }

        // if value is small and
        // was converted to scientific notation, don't append anything
        // as we are already done
        if (strpos($value, 'e-') !== false) {
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

    public static function addZeros(string $x, int $count): string
    {
        $result = $x;
        if ($count > 0) {
            $result .= str_repeat('0', $count);
        }

        return $result;
    }

    public static function truncateLongNumber(?string $number, ?int $decimals = null): string
    {
        $number = (string)$number;
        $pos = strpos($number, '.');
        $dividend = $pos === false ? $number : substr($number, 0, $pos);
        $remainder = $pos === false ? '' : substr($number, $pos + 1);

        return $decimals !== null
            ? $dividend . '.' . self::addZeros(substr($remainder, 0, $decimals), $decimals - strlen($remainder))
            : $dividend;
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
    public static function parseNumberRaw(string $text, string $decimalDelimiter, string $numberDelimiter = ''): ?float
    {
        // Replace numerals based on current locale data
        $digitsMap = self::_getNativeDigitsMap();
        $_text = $text;
        if ($digitsMap) {
            $_text = trim(implode('', array_map(function (string $character) use ($digitsMap) {
                return $digitsMap[$character] ?? $character;
            }, mb_str_split($text))));
        }

        $_text = preg_replace('/^[^\d]*\-/u', "\u{0002}", $_text); // preserve negative sign
        $_text = preg_replace(self::matchCurrenciesWithDots(), '', $_text, 1); // remove some currencies

        $decimalExp = escapeRegex::escapeRegex($decimalDelimiter);
        $numberExp = escapeRegex::escapeRegex($numberDelimiter);

        $isThereADecimalSeparatorInBetween = self::_buildRegex('^[^\\d]*\\d.*' . $decimalExp . '.*\\d[^\\d]*$');
        if (! preg_match($isThereADecimalSeparatorInBetween, $_text)) {
            $isValidWithDecimalBeforeHand = self::_buildRegex('(^[^\\d]*)' . $decimalExp . '(\\d*[^\\d]*$)');
            if (preg_match($isValidWithDecimalBeforeHand, $_text)) {
                $_text = preg_replace($isValidWithDecimalBeforeHand, "\$1\u{0001}\$2", $_text, 1);

                return self::_parseCodifiedNumber($_text);
            }
            $isValidWithoutDecimal = self::_buildRegex('^[^\\d]*[\\d ' . escapeRegex::escapeRegex($numberExp) . ']*[^\\d]*$');
            if (! preg_match($isValidWithoutDecimal, $_text)) {
                $_text = '';
            }

            return self::_parseCodifiedNumber($_text);
        }
        $isValid = self::_buildRegex('(^[^\\d]*[\\d ' . $numberExp . ']*)' . $decimalExp . '(\\d*[^\\d]*$)');
        $_text = preg_match($isValid, $_text) ? preg_replace($isValid, "\$1\u{0001}\$2", $_text, 1) : '';

        return self::_parseCodifiedNumber($_text);
    }

    /**
     * A codified number has \u0001 in the place of a decimal separator and a
     * \u0002 in the place of a negative sign.
     */
    public static function _parseCodifiedNumber(string $text): ?float
    {
        // remove everything but numbers, decimal separator and negative sign
        $_text = preg_replace("/[^0-9\u{0001}\u{0002}]/u", '', $text);
        $_text = preg_replace("/\u{0001}/", '.', $_text, 1); // restore decimal separator
        $_text = preg_replace("/\u{0002}/", '-', $_text, 1); // restore negative sign

        // js~php diff: equivalent of `isNaN(Number(_text))`
        return $_text === '' || ! is_numeric($_text) ? null : (float)$_text;
    }

    public static function _getNativeDigitsMap(): ?array
    {
        $numberFormatConfig = self::config();
        $nativeDigitMap = [];
        $digits = $numberFormatConfig['numberingSystemData']['digits'] ?? null;

        if ($digits === null) {
            return null;
        }

        foreach (mb_str_split($digits) as $i => $char) {
            $nativeDigitMap[$char] = (string)$i;
        }

        return $nativeDigitMap;
    }

    public static function parseNumber(string $text): ?float
    {
        $numberFormatConfig = self::config();

        return self::parseNumberRaw(
            $text,
            ($numberFormatConfig['decimalSeparator'] ?? null) ?: '.',
            $numberFormatConfig['numberDelimiter']
        );
    }

    /**
     * Converts a float into a prettified string. e.g. 1000.5 => "1,000.5"
     *
     * @deprecated Use `intlNumUtils::formatNumberWithThousandDelimiters(num)`
     * instead. It automatically handles decimal and thousand delimiters and
     * gets edge cases for Norwegian and Spanish right.
     *
     * @param string|int|float $num
     */
    public static function getFloatString($num, string $thousandDelimiter, string $decimalDelimiter): string
    {
        $str = self::_toString($num);
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
     * @deprecated Use `intlNumUtils::formatNumberWithThousandDelimiters(num, 0)`
     * instead. It automatically handles decimal thousand delimiters and gets
     * edge cases for Norwegian and Spanish right.
     *
     * @param string|int|float $num
     *
     * @throws \fbt\Exceptions\FbtException
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     */
    public static function getIntegerString($num, string $thousandDelimiter): string
    {
        $delim = $thousandDelimiter;
        if ($delim === '') {
            if (FbtConfig::get('debug')) {
                throw new \fbt\Exceptions\FbtException('thousandDelimiter cannot be empty string!');
            }
            $delim = ',';
        }

        $str = self::_toString($num);
        $regex = '/(\d+)(\d{3})/';
        while (preg_match($regex, $str)) {
            $str = preg_replace($regex, '$1' . $delim . '$2', $str, 1);
        }

        return $str;
    }

    public static function matchCurrenciesWithDots(): string
    {
        return self::_buildRegex(array_reduce(self::CURRENCIES_WITH_DOTS, function (string $regex, string $representation) {
            return $regex . ($regex ? '|' : '') . '(' . escapeRegex::escapeRegex($representation) . ')';
        }, ''));
    }

    /**
     * js~php diff: equivalent of the JS `String(value)` conversion of a number
     *
     * @param mixed $value
     */
    public static function toJsString($value): string
    {
        return self::_toString($value);
    }

    protected static function _buildRegex(string $pattern): string
    {
        static $_regexCache;

        if (! isset($_regexCache[$pattern])) {
            // js~php diff: JS RegExp `$` only matches at the very end (D modifier)
            // (a control character is the delimiter, so that "/" doesn't have to be escaped)
            $_regexCache[$pattern] = "\x01" . $pattern . "\x01iuD";
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
     * js~php diff: for backward compatibility, the locale-aware formatters convert
     * numeric strings (e.g. database decimals) to numbers, so they are rounded rather
     * than truncated. Use formatNumberRaw() for the upstream string semantics.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private static function _toNumberIfNumericString($value)
    {
        return is_string($value) && is_numeric($value) ? +$value : $value;
    }

    /**
     * js~php diff: equivalent of JS `Math.round()`, which rounds half up
     * towards +Infinity (PHP's round() rounds half away from zero)
     *
     * @param int|float $value
     *
     * @return int|float
     */
    private static function _mathRound($value)
    {
        if (is_int($value)) {
            return $value;
        }

        $floor = floor($value);

        return $value - $floor >= 0.5 ? $floor + 1 : $floor;
    }

    /**
     * js~php diff: equivalent of JS `parseInt(str, 10)`
     *
     * @return int|float
     */
    private static function _parseInt(string $str)
    {
        if (! preg_match('/^\s*([+-]?\d+)/', $str, $match)) {
            return NAN;
        }

        return is_numeric($match[1]) && abs((float)$match[1]) < PHP_INT_MAX
            ? (int)$match[1]
            : (float)$match[1];
    }

    /**
     * js~php diff: equivalent of JS `String(value)`. PHP's own float to string
     * conversion is limited by the `precision` ini setting and switches to the
     * exponent notation at different thresholds.
     *
     * @param int|float|string $value
     */
    private static function _toString($value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (string)$value;
        }

        $value = (float)$value;

        if (is_nan($value)) {
            return 'NaN';
        }

        if (is_infinite($value)) {
            return $value > 0 ? 'Infinity' : '-Infinity';
        }

        if ($value == 0) {
            return '0';
        }

        if ($value < 0) {
            return '-' . self::_toString(-$value);
        }

        // Find the shortest representation that round-trips
        for ($precision = 1; $precision <= 17; $precision++) {
            $repr = sprintf('%.' . ($precision - 1) . 'e', $value);
            if ((float)$repr === $value) {
                break;
            }
        }

        [$mantissa, $exponent] = explode('e', $repr);
        $digits = rtrim(str_replace('.', '', $mantissa), '0');
        $k = strlen($digits);
        $n = (int)$exponent + 1;

        if ($k <= $n && $n <= 21) {
            return $digits . str_repeat('0', $n - $k);
        }

        if (0 < $n && $n <= 21) {
            return substr($digits, 0, $n) . '.' . substr($digits, $n);
        }

        if (-6 < $n && $n <= 0) {
            return '0.' . str_repeat('0', -$n) . $digits;
        }

        $sign = $n - 1 < 0 ? '-' : '+';

        return ($k === 1 ? $digits : $digits[0] . '.' . substr($digits, 1)) . 'e' . $sign . abs($n - 1);
    }
}
