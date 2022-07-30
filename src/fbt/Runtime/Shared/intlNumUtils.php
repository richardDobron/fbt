<?php

namespace fbt\Runtime\Shared;

use fbt\Lib\NumberFormatConsts;

define("DEFAULT_GROUPING_SIZE", 3);

define("CURRENCIES_WITH_DOTS", [
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
]);

function _buildRegex($pattern)
{
    static $_regexCache;

    if (! isset($_regexCache[$pattern])) {
        $_regexCache[$pattern] = '/' . $pattern . '/i';
    }

    return $_regexCache[$pattern];
}

/**
 * Escapes regex special characters from a string, so it can be
 * used as a raw search term inside an actual regex.
 */
function escapeRegex($str)
{
    // From http://stackoverflow.com/questions/14076210/
    return preg_replace("/([.?*+\^$\[\]\\(){}|\-])/", '\\\\\1', $str);
}

function matchCurrenciesWithDots()
{
    return _buildRegex(array_reduce(CURRENCIES_WITH_DOTS, function ($regex, $representation) {
        return $regex . ($regex ? '|' : '') . '(' . escapeRegex($representation) . ')';
    }, ''));
}

function _replaceWithNativeDigits($number, $digits)
{
    $result = '';
    $number = strVal($number);

    for ($ii = 0, $iiMax = mb_strlen($number); $ii < $iiMax; $ii++) {
        $d = $digits[ord(mb_substr($number, $ii, 1)) - 48]; /* 48 === '0' */
        $result += $d ?? $number[$ii];
    }

    return $result;
}

function truncateLongNumber($number, $decimals = null)
{
    $pos = strpos($number, '.');
    $dividend = $pos ? substr($number, 0, $pos) : $number;
    $remainder = $pos ? substr($number, $pos + 1) : '';

    return $decimals != null
        ? $dividend .
        '.' .
        addZeros(substr($remainder, 0, $decimals), $decimals - mb_strlen($remainder))
        : $dividend;
}


/**
 * Calculate how many powers of 10 there are in a given number
 * I.e. 1.23 has 0, 100 and 999 have 2, and 1000 has 3.
 * Used in the inflation and rounding calculations below.
 */
function _getNumberOfPowersOfTen($value)
{
    return floor(log10(abs($value)));
}


function _roundNumber($valueParam, $decimalsParam = null): string
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

    if (! $pos) {
        $value .= '.';
        $zeros = $decimals;
    } else {
        $zeros = $decimals - (mb_strlen($value) - $pos - 1);
    }
    for ($i = 0, $l = $zeros; $i < $l; $i++) {
        $value .= '0';
    }

    return $value;
}

function addZeros($x, $count): string
{
    $result = $x;
    $result .= str_repeat('0', $count);

    return $result;
}

/**
 * A codified number has \u0001 in the place of a decimal separator and a
 * \u0002 in the place of a negative sign.
 */
function _parseCodifiedNumber($text)
{
    $_text = preg_replace("/[^0-9\u{0001}\u{0002}]/", '', $text);// decimal separator and negative sign
    $_text = preg_replace("/\u{0001}/", '.', $_text);// restore decimal separator
    $_text = preg_replace("/\u{0002}/", '-', $_text);// restore negative sign

    $value = floatval($_text);

    return $_text === '' || is_nan($value) ? null : $value;
}

function _getNativeDigitsMap()
{
    $NumberFormatConfig = NumberFormatConsts::get(FbtHooks::locale());
    $nativeDigitMap = [];
    $digits = $NumberFormatConfig['numberingSystemData']['digits'] ?? $NumberFormatConfig['numberingSystemData'];

    if ($digits == null) {
        return null;
    }

    for ($i = 0, $iMax = mb_strlen(strval($digits)); $i < $iMax; $i++) {
        $nativeDigitMap[ord(mb_substr($digits, $i, 1))] = strval($i);
    }

    return $nativeDigitMap;
}

class intlNumUtils
{
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
    public static function formatNumber($value, $decimals = null)
    {
        $NumberFormatConfig = NumberFormatConsts::get(FbtHooks::locale());

        return self::formatNumberRaw($value, $decimals, '', $NumberFormatConfig['decimalSeparator'], $NumberFormatConfig['minDigitsForThousandsSeparator'], $NumberFormatConfig['standardDecimalPatternInfo'], $NumberFormatConfig['numberingSystemData']);
    }

    /**
     * Format a number for string output.
     *
     * Calling this function directly is discouraged, unless you know
     * exactly what you're doing. Consider using `formatNumber` or
     * `formatNumberWithThousandDelimiters` below.
     */
    public static function formatNumberRaw($value, $decimals = null, $thousandDelimiter = '', $decimalDelimiter = '.', $minDigitsForThousandDelimiter = 0, $standardPatternInfo = [
        'primaryGroupSize' => DEFAULT_GROUPING_SIZE,
        'secondaryGroupSize' => DEFAULT_GROUPING_SIZE,
    ], $numberingSystemData = null)
    {
        $primaryGroupingSize = $standardPatternInfo['primaryGroupSize'] ?? DEFAULT_GROUPING_SIZE;
        $secondaryGroupingSize = $standardPatternInfo['secondaryGroupSize'] ?? $primaryGroupingSize;

        $digits = $numberingSystemData['digits'] ?? null;

        if (is_float($value) && is_nan($value)) {
            $v = 0;
        } elseif ($decimals == null) {
            $v = (string)$value;
        } elseif (is_string($value)) {
            $v = truncateLongNumber($value, $decimals);
        } else {
            $v = _roundNumber($value, $decimals);
        }

        $valueParts = explode('.', $v);
        $wholeNumber = $valueParts[0];
        $decimal = $valueParts[1] ?? null;

        if (abs(mb_strlen(strval(intval($wholeNumber)))) >= $minDigitsForThousandDelimiter) {
            $replaceWith = '$1' . $thousandDelimiter . '$2$3';
            $primaryPattern = '(\\d)(\\d{' . ($primaryGroupingSize - 0) . '})($|\\D)';
            $replaced = preg_replace(_buildRegex($primaryPattern), $replaceWith, $wholeNumber);
            if ($replaced != $wholeNumber) {
                $wholeNumber = $replaced;
                $secondaryPatternString = '(\\d)(\\d{' . ($secondaryGroupingSize - 0) . '})(' . escapeRegex($thousandDelimiter) . ')';
                $secondaryPattern = _buildRegex($secondaryPatternString);
                while (($replaced = preg_replace($secondaryPattern, $replaceWith, $wholeNumber)) != $wholeNumber) {
                    $wholeNumber = $replaced;
                }
            }
        }
        if ($digits !== null) {
            $wholeNumber = _replaceWithNativeDigits($wholeNumber, $digits);
            $decimal = $decimal && _replaceWithNativeDigits($decimal, $digits);
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
    public static function formatNumberWithThousandDelimiters($value, $decimals = null)
    {
        $NumberFormatConfig = NumberFormatConsts::get(FbtHooks::locale());

        return self::formatNumberRaw($value, $decimals, $NumberFormatConfig["numberDelimiter"], $NumberFormatConfig["decimalSeparator"], $NumberFormatConfig["minDigitsForThousandsSeparator"], $NumberFormatConfig["standardDecimalPatternInfo"], $NumberFormatConfig["numberingSystemData"]);
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
    public static function formatNumberWithLimitedSigFig($value, $decimals, $numSigFigs)
    {
        // First make the number sufficiently integer-like.
        $power = _getNumberOfPowersOfTen($value);
        $inflatedValue = $value;
        if ($power < $numSigFigs) {
            $inflatedValue = $value * pow(10, -$power + $numSigFigs);
        }
        // Now that we have a large enough integer, round to cut off some digits.
        $roundTo = pow(10, _getNumberOfPowersOfTen($inflatedValue) - $numSigFigs + 1);
        $truncatedValue = round($inflatedValue / $roundTo) * $roundTo;
        // Bring it back to whatever the number's magnitude was before.
        if ($power < $numSigFigs) {
            $truncatedValue /= pow(10, -$power + $numSigFigs);
            // Determine number of decimals based on sig figs
            if ($decimals == null) {
                return self::formatNumberWithThousandDelimiters($truncatedValue, $numSigFigs - $power - 1);
            }
        }

        // Decimals
        return self::formatNumberWithThousandDelimiters($truncatedValue, $decimals);
    }

    public static function parseNumber($text)
    {
        $NumberFormatConfig = NumberFormatConsts::get(FbtHooks::locale());

        return self::parseNumberRaw($text, $NumberFormatConfig['decimalSeparator '] ?? '.', $NumberFormatConfig['numberDelimiter']);
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
    public static function parseNumberRaw($text, $decimalDelimiter, $numberDelimiter = '')
    {
        // Replace numerals based on current locale data
        $digitsMap = _getNativeDigitsMap();
        $_text = $text;
        if ($digitsMap) {
            $_text = trim(implode('', array_map(function ($character) use ($digitsMap) {
                return $digitsMap[$character] ?? $character;
            }, str_split($text))));
        }

        $_text = preg_replace("/^[^\d]*\-/", "\u{0002}", $_text); // preserve negative sign
        $_text = preg_replace(matchCurrenciesWithDots(), '', $_text); // remove some currencies

        $decimalExp = escapeRegex($decimalDelimiter);
        $numberExp = escapeRegex($numberDelimiter);

        $isThereADecimalSeparatorInBetween = _buildRegex('^[^\\d]*\\d.*' . $decimalExp . '.*\\d[^\\d]*$');
        if (! preg_match($isThereADecimalSeparatorInBetween, $_text)) {
            $isValidWithDecimalBeforeHand = _buildRegex('(^[^\\d]*)' . $decimalExp . '(\\d*[^\\d]*$)');
            if (preg_match($isValidWithDecimalBeforeHand, $_text)) {
                $_text = preg_replace($isValidWithDecimalBeforeHand, "$1\u{0001}$2", $_text);

                return _parseCodifiedNumber($_text);
            }
            $isValidWithoutDecimal = _buildRegex('^[^\\d]*[\\d ' . escapeRegex($numberExp) . ']*[^\\d]*$');
            if (! preg_match($isValidWithoutDecimal, $_text)) {
                $_text = '';
            }

            return _parseCodifiedNumber($_text);
        }
        $isValid = _buildRegex('(^[^\\d]*[\\d ' . $numberExp . ']*)' . $decimalExp . '(\\d*[^\\d]*$)');
        $_text = preg_match($isValid, $_text) ? preg_replace($isValid, "$1\u{0001}$2", $_text) : '';

        return _parseCodifiedNumber($_text);
    }

    public static function truncateLongNumber($number, $decimals = null)
    {
        $pos = strpos($number, '.');
        $dividend = $pos ? $number : substr($number, 0, $pos);
        $remainder = $pos ? '' : substr($number, $pos + 1);

        return $decimals !== null ? $dividend . '.' . addZeros(substr($remainder, 0, $decimals), $decimals - mb_strlen($remainder)) : $dividend;
    }

    /**
     * Converts a float into a prettified string. e.g. 1000.5 => "1,000.5"
     *
     * @deprecated Use `intlNumber::formatNumberWithThousandDelimiters(num)`
     * instead. It automatically handles decimal and thousand delimiters and
     * gets edge cases for Norwegian and Spanish right.
     *
     */
    public static function getFloatString($num, $thousandDelimiter, $decimalDelimiter)
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
    public static function getIntegerString($num, $thousandDelimiter)
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
}
