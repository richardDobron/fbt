<?php

namespace fbt\Transform\FbtTransform\Translate\Gender;

use fbt\Transform\FbtTransform\Translate\IntlVariations;

class IntlDefaultGenderType
{
    public static function getFallback()
    {
        return IntlVariations::INTL_GENDER_VARIATIONS['UNKNOWN'];
    }

    public static function getGenderVariations(): array
    {
        return [
            IntlVariations::INTL_GENDER_VARIATIONS['UNKNOWN'],
            IntlVariations::INTL_GENDER_VARIATIONS['MALE'],
            IntlVariations::INTL_GENDER_VARIATIONS['FEMALE'],
        ];
    }
}
