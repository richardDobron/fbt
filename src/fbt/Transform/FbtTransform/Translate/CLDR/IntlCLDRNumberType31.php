<?php

namespace fbt\Transform\FbtTransform\Translate\CLDR;

use fbt\Transform\FbtTransform\Translate\IntlVariations;

class IntlCLDRNumberType31 implements IntlNumberConsistency
{
    public function getNumberVariations(): array
    {
        return [
            IntlVariations::INTL_NUMBER_VARIATIONS['ONE'],
            IntlVariations::INTL_NUMBER_VARIATIONS['TWO'],
            IntlVariations::INTL_NUMBER_VARIATIONS['FEW'],
            IntlVariations::INTL_NUMBER_VARIATIONS['MANY'],
            IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'],
        ];
    }

    public function getFallback(): int
    {
        return IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'];
    }

    public function getExample(int $variation): ?string
    {
        $examples = [
            IntlVariations::INTL_NUMBER_VARIATIONS['ONE'] => "a number like 1, 21, 31, 41, 51, 61, 81, 101, 1001, …",
            IntlVariations::INTL_NUMBER_VARIATIONS['TWO'] => "a number like 2, 22, 32, 42, 52, 62, 82, 102, 1002, …",
            IntlVariations::INTL_NUMBER_VARIATIONS['FEW'] => "a number like 3, 4, 9, 23, 24, 29, 33, 34, 39, 43, 44, 49, 103, 1003, …",
            IntlVariations::INTL_NUMBER_VARIATIONS['MANY'] => "a number like 1000000, …",
            IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'] => "a number like 0, 5~8, 10~20, 100, 1000, 10000, 100000, …",
        ];

        return $examples[$variation] ?? null;
    }

    public function getVariation($n): int
    {
        if ($n % 10 === 1 && ($n % 100 !== 11 && $n % 100 !== 71 && $n % 100 !== 91)) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['ONE'];
        }

        if ($n % 10 === 2 && ($n % 100 !== 12 && $n % 100 !== 72 && $n % 100 !== 92)) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['TWO'];
        }

        if (($n % 10 >= 3 && $n % 10 <= 4 || $n % 10 === 9) && (($n % 100 < 10 || $n % 100 > 19) && ($n % 100 < 70 || $n % 100 > 79) && ($n % 100 < 90 || $n % 100 > 99))) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['FEW'];
        }

        if ($n !== 0 && $n % 1000000 === 0) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['MANY'];
        }

        return IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'];
    }
}
