<?php

namespace fbt\Transform\FbtTransform\Translate;

use fbt\Exceptions\FbtException;

class IntlVariations
{
    const GENDER_MALE = 1;
    const GENDER_FEMALE = 2;
    const GENDER_UNKNOWN = 3;

    const INTL_NUMBER_VARIATIONS = [
        'ZERO' => 0x10, //  0b10000
        'ONE' => 0x4, //    0b00100
        'TWO' => 0x8, //    0b01000
        'FEW' => 0x14, //   0b10100
        'MANY' => 0xc, //   0b01100
        'OTHER' => 0x18, // 0b11000
    ];

    const INTL_GENDER_VARIATIONS = [
        'MALE' => 1,
        'FEMALE' => 2,
        'UNKNOWN' => 3,
    ];

    // Two bitmasks for representing gender/number variations.  Give a bit
    // between number/gender in case CLDR ever exceeds 7 options
    const INTL_VARIATION_MASK = [
        'NUMBER' => 0x1c, // 0b11100
        'GENDER' => 0x03, // 0b00011
    ];

    const INTL_FBT_VARIATION_TYPE = [
        'GENDER' => 1,
        'NUMBER' => 2,
        'PRONOUN' => 3,
    ];

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    public static function getType($n): int
    {
        if (! self::isValidValue($n)) {
            throw new FbtException('Invalid NumberType: ' . $n);
        }

        return $n & self::INTL_VARIATION_MASK['NUMBER']
            ? self::INTL_VARIATION_MASK['NUMBER']
            : self::INTL_VARIATION_MASK['GENDER'];
    }

    // This is not CLDR, but an fbt-specific marker that exists so that
    // singular phrases are not overwritten by multiplexed plural phrases
    // with a singular entry
    const EXACTLY_ONE = '_1';

    const SUBJECT = '__subject__';
    const VIEWING_USER = '__viewing_user__';

    public static function isValidValue($v): bool
    {
        $specials = [
            // The default entry.  When no entry exists, we fallback to this in the fbt
            // table access logic.
            '*' => true,
            self::EXACTLY_ONE => true,
        ];

        return (
            $specials[$v] ??
            ($v & self::INTL_VARIATION_MASK['NUMBER'] && ! ($v & ~self::INTL_VARIATION_MASK['NUMBER'])) ||
            ($v & self::INTL_VARIATION_MASK['GENDER'] && ! ($v & ~self::INTL_VARIATION_MASK['GENDER']))
        );
    }
}
