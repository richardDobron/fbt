<?php

namespace fbt\Transform\FbtTransform\Translate\CLDR;

use fbt\Transform\FbtTransform\Translate\IntlVariations;

class IntlCLDRNumberType45 implements IntlNumberConsistency
{
    public function getNumberVariations(): array
    {
        return [
            IntlVariations::INTL_NUMBER_VARIATIONS['ONE'],
            IntlVariations::INTL_NUMBER_VARIATIONS['TWO'],
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
            IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'] => "0 or greater than 2.",
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

        return IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'];
    }
}
