<?php

namespace fbt\Transform\FbtTransform\Translate\CLDR;

use fbt\Transform\FbtTransform\Translate\IntlVariations;

class IntlCLDRNumberType29 implements IntlNumberConsistency
{
    public function getNumberVariations(): array
    {
        return [
            IntlVariations::INTL_NUMBER_VARIATIONS['ONE'],
            IntlVariations::INTL_NUMBER_VARIATIONS['FEW'],
            IntlVariations::INTL_NUMBER_VARIATIONS['MANY'],
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
            IntlVariations::INTL_NUMBER_VARIATIONS['ONE'] => "1.",
            IntlVariations::INTL_NUMBER_VARIATIONS['FEW'] => "a number like 0, 2~10, 102~107, 1002, …",
            IntlVariations::INTL_NUMBER_VARIATIONS['MANY'] => "a number like 11~19, 111~117, 1011, …",
            IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'] => "a number like 20~35, 100, 1000, 10000, 100000, 1000000, …",
        ];

        return $examples[$variation] ?? null;
    }

    public function getVariation($n): int
    {
        if ($n === 1) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['ONE'];
        }

        if ($n === 0 || $n % 100 >= 2 && $n % 100 <= 10) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['FEW'];
        }

        if ($n % 100 >= 11 && $n % 100 <= 19) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['MANY'];
        }

        return IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'];
    }
}
