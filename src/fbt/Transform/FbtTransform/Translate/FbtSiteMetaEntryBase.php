<?php

namespace fbt\Transform\FbtTransform\Translate;

/**
 * Represents a metadata entry in a <fbt> source data. An entry could result
 * in string variations during the translation process depending on the
 * locale we are translating the string for.
 */
abstract class FbtSiteMetaEntryBase
{
    /** @var int|null */
    public $type;
    /** @var string|null */
    public $token;

    public function __construct(?int $type, ?string $token)
    {
        $this->type = $type;
        $this->token = $token;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    abstract public function hasVariationMask(): bool;

    abstract public function getVariationMask(): ?int;

    public static function getVariationMaskFromType(?int $type): ?int
    {
        switch ($type) {
            case IntlVariations::INTL_FBT_VARIATION_TYPE['GENDER']:
                return IntlVariations::INTL_VARIATION_MASK['GENDER'];
            case IntlVariations::INTL_FBT_VARIATION_TYPE['NUMBER']:
                return IntlVariations::INTL_VARIATION_MASK['NUMBER'];
        }

        return null;
    }
}
