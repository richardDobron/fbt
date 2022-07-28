<?php

namespace fbt\Transform\FbtTransform\Translate\CLDR;

use fbt\Transform\FbtTransform\Translate\IntlVariations;

class IntlCLDRNumberType33 implements IntlNumberConsistency
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

    public function getExample(int $variation): ?string
    {
        $examples = [
            IntlVariations::INTL_NUMBER_VARIATIONS['ONE'] => "a number like 1, 11, 21, 31, 41, 51, 61, 71, 81, 101, 1001, …",
            IntlVariations::INTL_NUMBER_VARIATIONS['TWO'] => "a number like 2, 12, 22, 32, 42, 52, 62, 72, 82, 102, 1002, …",
            IntlVariations::INTL_NUMBER_VARIATIONS['FEW'] => "a number like 0, 20, 40, 60, 80, 100, 120, 140, 160, 180, 1000, 1020, …",
            IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'] => "a number like 3~10, 103, 1003, …",
        ];

        return $examples[$variation] ?? null;
    }

    public function getVariation($n): int
    {
        if ($n % 10 === 1) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['ONE'];
        }

        if ($n % 10 === 2) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['TWO'];
        }

        if (($n % 100 === 0 || $n % 100 === 20 || $n % 100 === 40 || $n % 100 === 60 || $n % 100 === 80)) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['FEW'];
        }

        return IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'];
    }
}
