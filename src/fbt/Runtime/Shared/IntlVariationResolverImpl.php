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
     * @param int|float $number
     * @return array
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public static function getNumberVariations($number): array
    {
        $numType = IntlNumberType::get(FbtHooks::locale())->getVariation($number);

        invariant(
            $numType & IntlVariations::INTL_VARIATION_MASK['NUMBER'],
            'Invalid number provided: %s (%s)',
            $numType,
            gettype($numType)
        );

        // js~php diff: JS has a single number type, so 1.0 is exactly one as well
        return $number == 1 ? [self::EXACTLY_ONE, $numType, '*'] : [$numType, '*'];
    }

    /**
     * Wrapper to validate gender.
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public static function getGenderVariations(int $gender): array
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
