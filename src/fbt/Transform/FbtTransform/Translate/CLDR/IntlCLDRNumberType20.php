<?php

namespace fbt\Transform\FbtTransform\Translate\CLDR;

use fbt\Transform\FbtTransform\Translate\IntlVariations;

class IntlCLDRNumberType20 implements IntlNumberConsistency
{
    public function getNumberVariations(): array
    {
        return [
            IntlVariations::INTL_NUMBER_VARIATIONS['ONE'],
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
            IntlVariations::INTL_NUMBER_VARIATIONS['ONE'] => "a number like 1, 21, 31, 41, 51, 61, 71, 81, 101, 1001, …",
            IntlVariations::INTL_NUMBER_VARIATIONS['FEW'] => "a number like 2~4, 22~24, 32~34, 42~44, 52~54, 62, 102, 1002, …",
            IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'] => "a number like 0, 5~19, 100, 1000, 10000, 100000, 1000000, …",
        ];

        return $examples[$variation] ?? null;
    }

    public function getVariation($n): int
    {
        if ($n % 10 === 1 && $n % 100 !== 11) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['ONE'];
        }

        if ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 12 || $n % 100 > 14)) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['FEW'];
        }

        return IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'];
    }
}
