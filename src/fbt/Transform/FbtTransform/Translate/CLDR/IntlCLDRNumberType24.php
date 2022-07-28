<?php

namespace fbt\Transform\FbtTransform\Translate\CLDR;

use fbt\Transform\FbtTransform\Translate\IntlVariations;

class IntlCLDRNumberType24 implements IntlNumberConsistency
{
    public function getNumberVariations(): array
    {
        return [
            IntlVariations::INTL_NUMBER_VARIATIONS['ONE'],
            IntlVariations::INTL_NUMBER_VARIATIONS['TWO'],
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
            IntlVariations::INTL_NUMBER_VARIATIONS['MANY'] => "a number like 20, 30, 40, 50, 60, 70, 80, 90, 100, 1000, 10000, 100000, 1000000, …",
            IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'] => "a number like 0, 3~17, 101, 1001, …",
        ];

        return $examples[$variation] ?? null;
    }

    public function getVariation($n): int
    {
        if ($n % 100 === 1) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['ONE'];
        }

        if ($n % 100 === 2) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['TWO'];
        }

        if (($n < 0 || $n > 10) && $n % 10 === 0) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['MANY'];
        }

        return IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'];
    }
}
