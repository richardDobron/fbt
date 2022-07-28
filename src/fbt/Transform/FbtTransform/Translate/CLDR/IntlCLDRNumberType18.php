<?php

namespace fbt\Transform\FbtTransform\Translate\CLDR;

use fbt\Transform\FbtTransform\Translate\IntlVariations;

class IntlCLDRNumberType18 implements IntlNumberConsistency
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

    public function getExample(int $variation): ?string
    {
        $examples = [
            IntlVariations::INTL_NUMBER_VARIATIONS['ONE'] => "0 or 1.",
            IntlVariations::INTL_NUMBER_VARIATIONS['FEW'] => "between 2 and 10.",
            IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'] => "greater than 10.",
        ];

        return $examples[$variation] ?? null;
    }

    public function getVariation($n): int
    {
        if ($n === 0 || $n === 1) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['ONE'];
        }

        if ($n >= 2 && $n <= 10) {
            return IntlVariations::INTL_NUMBER_VARIATIONS['FEW'];
        }

        return IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'];
    }
}
