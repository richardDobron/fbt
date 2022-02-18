<?php

namespace fbt\Transform\FbtTransform\Translate\CLDR;

use fbt\Transform\FbtTransform\Translate\IntlVariations;

class IntlCLDRNumberType21 implements IntlNumberConsistency
{
    public function getNumberVariations(): array
    {
        return [
            IntlVariations::INTL_NUMBER_VARIATIONS['ONE'],
            IntlVariations::INTL_NUMBER_VARIATIONS['TWO'],
            IntlVariations::INTL_NUMBER_VARIATIONS['FEW'],
            IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'],
        ];
    }

    public function getFallback(): int
    {
        return IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'];
    }

    public function getExample(int $variation)
    {
        $examples = [
            IntlVariations::INTL_NUMBER_VARIATIONS['ONE'] => "1 or 11.",
            IntlVariations::INTL_NUMBER_VARIATIONS['TWO'] => "2 or 12.",
            IntlVariations::INTL_NUMBER_VARIATIONS['FEW'] => "between 3 and 10, or between 13 and 19.",
            IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'] => "greater than 19.",
        ];

        return $examples[$variation] ?? null;
    }

    public function getVariation($n): int
    {
        if (($n === 1 || $n === 11)) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['ONE'];
        }

        if (($n === 2 || $n === 12)) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['TWO'];
        }

        if (($n >= 3 && $n <= 10 || $n >= 13 && $n <= 19)) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['FEW'];
        }

        return IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'];
    }
}
