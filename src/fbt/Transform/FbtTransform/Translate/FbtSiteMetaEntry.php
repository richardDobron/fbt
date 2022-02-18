<?php

namespace fbt\Transform\FbtTransform\Translate;

use function fbt\invariant;

class FbtSiteMetaEntry
{
    private $_type;
    private $_token;
    private $_mask;

    public function __construct($type, $token, $mask)
    {
        $this->_type = $type;
        $this->_token = $token;
        $this->_mask = $mask;
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    public static function wrap($entry): FbtSiteMetaEntry
    {
        FbtSiteMetaEntry::_validate($entry);

        return new FbtSiteMetaEntry(
            $entry['type'] ?? null,
            $entry['token'] ?? null,
            $entry['mask'] ?? null
        );
    }

    public function getToken()
    {
        return $this->_token;
    }

    public function hasVariationMask(): bool
    {
        if ($this->_token === null) {
            return false;
        }
        if ($this->_type === null) {
            return $this->_mask !== null;
        }

        return self::getVariationMaskFromType($this->_type) !== null;
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    public function getVariationMask()
    {
        invariant(
            $this->hasVariationMask() === true,
            'check hasVariationMask to avoid this invariant'
        );

        if ($this->_type === null) {
            return $this->_mask;
        } else {
            return self::getVariationMaskFromType($this->_type);
        }
    }

    public function unwrap(): array
    {
        $entry = [];
        if ($this->_token !== null) {
            $entry['token'] = $this->_token;
        }
        if ($this->_mask !== null) {
            $entry['mask'] = $this->_mask;
        }
        if ($this->_type !== null) {
            $entry['type'] = $this->_type;
        }

        return $entry;
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    public static function _validate($entry)
    {
        $type = $entry['type'] ?? null;
        $token = $entry['token'] ?? null;
        $mask = $entry['mask'] ?? null;
        if ($type === null) {
            invariant(
                $token !== null && $mask !== null,
                'token and mask should be specified when there is not type'
            );
        } else {
            invariant(
                $mask === null,
                'mask should not be specified when there is type'
            );
            if ($type === IntlVariations::INTL_FBT_VARIATION_TYPE['GENDER']) {
                invariant(
                    $token !== null,
                    'token should be specified for gender variation'
                );
            } elseif ($type === IntlVariations::INTL_FBT_VARIATION_TYPE['PRONOUN']) {
                invariant(
                    $token === null,
                    'token should not be specified for pronoun variation'
                );
            }
        }
    }

    /**
     * @param int|null $type
     * @return int|mixed|null
     */
    public static function getVariationMaskFromType($type)
    {
        $_variationTypeToMask = [];
        $_variationTypeToMask[IntlVariations::INTL_FBT_VARIATION_TYPE['GENDER']] = IntlVariations::INTL_VARIATION_MASK['GENDER'];
        $_variationTypeToMask[IntlVariations::INTL_FBT_VARIATION_TYPE['NUMBER']] = IntlVariations::INTL_VARIATION_MASK['NUMBER'];

        return $_variationTypeToMask[$type] ?? null;
    }
}
