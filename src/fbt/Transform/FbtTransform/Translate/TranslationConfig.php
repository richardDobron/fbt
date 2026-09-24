<?php

namespace fbt\Transform\FbtTransform\Translate;

use function fbt\invariant;

use fbt\Lib\IntlNumberType;
use fbt\Transform\FbtTransform\Translate\CLDR\IntlNumberConsistency;
use fbt\Transform\FbtTransform\Translate\Gender\IntlGenderType;

/**
 * Represents a given locale's variation (number/gender) configuration.
 * i.e. which variations we should default to when unknown
 */
class TranslationConfig
{
    /** @var IntlNumberConsistency */
    public $numberType;
    /** @var mixed */
    public $genderType;

    /**
     * @param IntlNumberConsistency $numberType
     * @param mixed $genderType
     */
    public function __construct(IntlNumberConsistency $numberType, $genderType)
    {
        $this->numberType = $numberType;
        $this->genderType = $genderType;
    }

    public function getTypesFromMask(int $mask): array
    {
        if ($mask === IntlVariations::INTL_FBT_VARIATION_TYPE['NUMBER']) {
            return array_merge([IntlVariations::EXACTLY_ONE], $this->numberType->getNumberVariations());
        }

        return $this->genderType->getGenderVariations();
    }

    /**
     * @param mixed $variation
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public function isDefaultVariation($variation): bool
    {
        // variation could be "*", or it could be number variation or
        // gender variation value in either string or number type.
        if (is_int($variation)) {
            $value = $variation;
        } else {
            invariant(
                is_string($variation),
                'Expect keys in translated payload to be either string or number type ' .
                'but got a key of type `%s`',
                gettype($variation)
            );
            // js~php diff: equivalent of `Number.parseInt(variation, 10)`
            if (! preg_match('/^\s*([+-]?\d+)/', $variation, $match)) {
                return false;
            }
            $value = (int)$match[1];
        }

        return $value === $this->numberType->getFallback()
            || $value === $this->genderType->getFallback();
    }

    public static function fromFBLocale(string $locale): TranslationConfig
    {
        return new TranslationConfig(
            IntlNumberType::forLocale($locale),
            IntlGenderType::forLocale($locale)
        );
    }
}
