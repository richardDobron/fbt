<?php

namespace fbt\Transform\FbtTransform\Translate\CLDR;

use fbt\Transform\FbtTransform\Translate\IntlVariations;

class IntlCLDRNumberType32 implements IntlNumberConsistency
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
            IntlVariations::INTL_NUMBER_VARIATIONS['ONE'] => "1.",
            IntlVariations::INTL_NUMBER_VARIATIONS['TWO'] => "2.",
            IntlVariations::INTL_NUMBER_VARIATIONS['FEW'] => "between 3 and 6.",
            IntlVariations::INTL_NUMBER_VARIATIONS['MANY'] => "between 7 and 10.",
            IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'] => "a number like 0, 11~25, 100, 1000, 10000, 100000, 1000000, …",
        ];

        return $examples[$variation] ?? null;
    }

    public function getVariation($n): int
    {
        if ($n === 1) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['ONE'];
        }

        if ($n === 2) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['TWO'];
        }

        if ($n >= 3 && $n <= 6) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['FEW'];
        }

        if ($n >= 7 && $n <= 10) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['MANY'];
        }

        return IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'];
    }
}
