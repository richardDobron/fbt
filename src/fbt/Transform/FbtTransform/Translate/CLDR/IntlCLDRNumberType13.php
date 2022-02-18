<?php

namespace fbt\Transform\FbtTransform\Translate\CLDR;

use fbt\Transform\FbtTransform\Translate\IntlVariations;

class IntlCLDRNumberType13 implements IntlNumberConsistency
{
    public function getNumberVariations(): array
    {
        return [
            IntlVariations::INTL_NUMBER_VARIATIONS['ONE'],
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
            IntlVariations::INTL_NUMBER_VARIATIONS['ONE'] => "a number like 0~3, 5, 7, 8, 10~13, 15, 17, 18, 20, 21, 100, 1000, 10000, 100000, 1000000, …",
            IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'] => "a number like 4, 6, 9, 14, 16, 19, 24, 26, 104, 1004, …",
        ];

        return $examples[$variation] ?? null;
    }

    public function getVariation($n): int
    {
        if (($n === 1 || $n === 2 || $n === 3) || ($n % 10 !== 4 && $n % 10 !== 6 && $n % 10 !== 9)) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['ONE'];
        }

        return IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'];
    }
}
