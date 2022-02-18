<?php

namespace fbt\Transform\FbtTransform\Translate;

use fbt\Lib\IntlNumberType;
use fbt\Transform\FbtTransform\Translate\Gender\IntlGenderType;

/**
 * Represents a given locale's variation (number/gender) configuration.
 * i.e. which variations we should default to when unknown
 */
class TranslationConfig
{
    public function __construct($numberType, $genderType)
    {
        $this->numberType = $numberType;
        $this->genderType = $genderType;
    }

    public function getTypesFromMask(
        $mask // IntlVariationType
    ) {
        if ($mask === IntlVariations::INTL_FBT_VARIATION_TYPE['NUMBER']) {
            $types = $this->numberType->getNumberVariations();

            return array_merge([IntlVariations::EXACTLY_ONE], $types);
        }

        return $this->genderType->getGenderVariations();
    }

    public function isDefaultVariation(
        $variation // mixed
    ) {
        $value = intval($variation);
        if (is_nan($value)) {
            return false;
        }

        return (
            $value === $this->numberType->getFallback() ||
            $value === $this->genderType->getFallback()
        );
    }

    public static function fromFBLocale($locale): TranslationConfig
    {
        return new TranslationConfig(
            IntlNumberType::getLocale($locale),
            IntlGenderType::forLocale($locale)
        );
    }
}
