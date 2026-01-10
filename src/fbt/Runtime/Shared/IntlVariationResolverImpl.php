<?php

namespace fbt\Runtime\Shared;

use function fbt\invariant;

use fbt\Lib\IntlNumberType;
use fbt\Transform\FbtTransform\Translate\IntlVariations;

class IntlVariationResolverImpl
{
    public const EXACTLY_ONE = '_1';

    /**
     * Wrapper around FbtNumberType::getVariation that special cases our EXACTLY_ONE
     * value to accommodate the singular form of fbt:plural
     *
     * @param int $number
     * @return array
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public static function getNumberVariations(int $number): array
    {
        $locale = FbtHooks::locale();

        $numType = IntlNumberType::getLocale($locale)->getVariation($number);

        invariant(
            $numType & IntlVariations::INTL_VARIATION_MASK['NUMBER'],
            'Invalid number provided',
            $numType,
            gettype($numType)
        );

        return $number === 1 ? [self::EXACTLY_ONE, $numType, "*"] : [$numType, "*"];
    }

    /**
     * Wrapper to validate gender.
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public static function getGenderVariations($gender): array
    {
        invariant(
            $gender & IntlVariations::INTL_VARIATION_MASK['GENDER'],
            'Invalid gender provided: %s (%s)',
            $gender,
            gettype($gender)
        );

        return [$gender, "*"];
    }
}
