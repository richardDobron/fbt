<?php

namespace fbt\Transform\FbtTransform\Translate;

use fbt\Exceptions\FbtException;

class IntlVariations
{
    public const INTL_NUMBER_VARIATIONS = [
        'ZERO' => 0x10, //  0b10000
        'ONE' => 0x4, //    0b00100
        'TWO' => 0x8, //    0b01000
        'FEW' => 0x14, //   0b10100
        'MANY' => 0xc, //   0b01100
        'OTHER' => 0x18, // 0b11000
    ];

    public const INTL_GENDER_VARIATIONS = [
        'MALE' => 1,
        'FEMALE' => 2,
        'UNKNOWN' => 3,
    ];

    // Two bitmasks for representing gender/number variations.  Give a bit
    // between number/gender in case CLDR ever exceeds 7 options
    public const INTL_VARIATION_MASK = [
        'NUMBER' => 0x1c, // 0b11100
        'GENDER' => 0x03, // 0b00011
    ];

    public const INTL_FBT_VARIATION_TYPE = [
        'GENDER' => 1,
        'NUMBER' => 2,
        'PRONOUN' => 3,
    ];

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    public static function getType(int $n): int
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
    public const EXACTLY_ONE = '_1';

    // Gender variation key used in JSFBT to represent any gender
    public const GENDER_ANY = '*';
    // Number variation key used in JSFBT to represent "many" (i.e. non-exactly one)
    public const NUMBER_ANY = '*';

    public const SUBJECT = '__subject__';
    public const VIEWING_USER = '__viewing_user__';

    /**
     * @param string|int $value
     */
    public static function isValidValue($value): bool
    {
        // Like upstream, the key of the special entry is the literal 'EXACTLY_ONE'
        // (not the value of the constant)
        $specials = [
            // The default entry.  When no entry exists, we fallback to this in the fbt
            // table access logic.
            '*' => true,
            'EXACTLY_ONE' => true,
        ];

        // like JS `Number(value)` in a bitwise operation (NaN is 0)
        $num = is_numeric($value) ? (int)$value : 0;

        return (
            ($specials[$value] ?? false) ||
            ($num & self::INTL_VARIATION_MASK['NUMBER'] && ! ($num & ~self::INTL_VARIATION_MASK['NUMBER'])) ||
            ($num & self::INTL_VARIATION_MASK['GENDER'] && ! ($num & ~self::INTL_VARIATION_MASK['GENDER']))
        );
    }
}
