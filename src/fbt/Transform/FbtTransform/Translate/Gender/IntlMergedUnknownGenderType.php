<?php

namespace fbt\Transform\FbtTransform\Translate\Gender;

use fbt\Transform\FbtTransform\Translate\IntlVariations;

class IntlMergedUnknownGenderType
{
    public static function getFallback(): int
    {
        return IntlVariations::INTL_GENDER_VARIATIONS['MALE'];
    }

    public static function getGenderVariations(): array
    {
        return [
            IntlVariations::INTL_GENDER_VARIATIONS['MALE'],
            IntlVariations::INTL_GENDER_VARIATIONS['FEMALE'],
        ];
    }
}
