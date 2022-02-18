<?php

namespace fbt\Transform\FbtTransform\Translate\CLDR;

use fbt\Transform\FbtTransform\Translate\IntlVariations;

class IntlCLDRNumberType47 implements IntlNumberConsistency
{
    public function getNumberVariations(): array
    {
        return [
            IntlVariations::INTL_NUMBER_VARIATIONS['ZERO'],
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
            IntlVariations::INTL_NUMBER_VARIATIONS['ZERO'] => "a number like 0, 10~20, 30, 40, 50, 60, 100, 1000, 10000, 100000, 1000000, …",
            IntlVariations::INTL_NUMBER_VARIATIONS['ONE'] => "a number like 1, 21, 31, 41, 51, 61, 71, 81, 101, 1001, …",
            IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'] => "a number like 2~9, 22~29, 102, 1002, …",
        ];

        return $examples[$variation] ?? null;
    }

    public function getVariation($n): int
    {
        if ($n % 10 === 0 || $n % 100 >= 11 && $n % 100 <= 19) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['ZERO'];
        }

        if ($n % 10 === 1 && $n % 100 !== 11) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['ONE'];
        }

        return IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'];
    }
}
