<?php

namespace fbt\Runtime\Shared;

use function fbs;

class formatNumber
{
    /**
     * @param int|float $value
     */
    public static function formatNumber($value, ?int $decimals = null): string
    {
        return intlNumUtils::formatNumber($value, $decimals);
    }

    /**
     * @param int|float|string $value
     */
    public static function withThousandDelimiters($value, ?int $decimals = null): string
    {
        return intlNumUtils::formatNumberWithThousandDelimiters($value, $decimals);
    }

    /**
     * @param int|float $maxNumber
     */
    private static function getAtLeastString($maxNumber, ?int $decimals = null): \fbt\fbs
    {
        // after we start using CLDR data, it will not be fbt anymore.
        return fbs(
            [
                \fbt\fbs::param(
                    'number',
                    intlNumUtils::formatNumberWithThousandDelimiters($maxNumber, $decimals),
                    ['number' => $maxNumber]
                ),
                '+',
            ],
            "Label with meaning 'at least number'",
            ['project' => 'locale_data']
        );
    }

    /**
     * @param int|float $minNumber
     */
    private static function getLessThanString($minNumber, ?int $decimals = null): \fbt\fbs
    {
        // after we start using CLDR data, it will not be fbt anymore.
        return fbs(
            [
                // js~php diff: fbt texts are HTML
                '&lt;',
                \fbt\fbs::param(
                    'number',
                    intlNumUtils::formatNumberWithThousandDelimiters($minNumber, $decimals),
                    ['number' => $minNumber]
                ),
            ],
            "Label with meaning 'less than number'",
            ['project' => 'locale_data']
        );
    }

    /**
     * @param int|float $value
     * @param int|float $maxvalue
     *
     * @return \fbt\fbs|string
     */
    public static function withMaxLimit($value, $maxvalue, ?int $decimals = null)
    {
        return $value > $maxvalue
            ? self::getAtLeastString($maxvalue, $decimals)
            : intlNumUtils::formatNumberWithThousandDelimiters($value, $decimals);
    }

    /**
     * @param int|float $value
     * @param int|float $minvalue
     *
     * @return \fbt\fbs|string
     */
    public static function withMinLimit($value, $minvalue, ?int $decimals = null)
    {
        return $value < $minvalue
            ? self::getLessThanString($minvalue, $decimals)
            : intlNumUtils::formatNumberWithThousandDelimiters($value, $decimals);
    }
}
