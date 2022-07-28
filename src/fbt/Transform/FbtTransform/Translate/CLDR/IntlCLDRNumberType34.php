<?php

namespace fbt\Transform\FbtTransform\Translate\CLDR;

use fbt\Transform\FbtTransform\Translate\IntlVariations;

class IntlCLDRNumberType34 implements IntlNumberConsistency
{
    public function getNumberVariations(): array
    {
        return [
            IntlVariations::INTL_NUMBER_VARIATIONS['ZERO'],
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
            IntlVariations::INTL_NUMBER_VARIATIONS['ZERO'] => "0.",
            IntlVariations::INTL_NUMBER_VARIATIONS['ONE'] => "1.",
            IntlVariations::INTL_NUMBER_VARIATIONS['TWO'] => "2.",
            IntlVariations::INTL_NUMBER_VARIATIONS['FEW'] => "a number like 3~10, 103~110, 1003, …",
            IntlVariations::INTL_NUMBER_VARIATIONS['MANY'] => "a number like 11~26, 111, 1011, …",
            IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'] => "a number like 100~102, 200~202, 300~302, 400~402, 500~502, 600, 1000, 10000, 100000, 1000000, …",
        ];

        return $examples[$variation] ?? null;
    }

    public function getVariation($n): int
    {
        if ($n === 0) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['ZERO'];
        }

        if ($n === 1) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['ONE'];
        }

        if ($n === 2) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['TWO'];
        }

        if ($n % 100 >= 3 && $n % 100 <= 10) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['FEW'];
        }

        if ($n % 100 >= 11 && $n % 100 <= 99) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['MANY'];
        }

        return IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'];
    }
}
