<?php

/**
 * Provides return values for fbt constructs calls.
 */

namespace fbt\Runtime\Shared;

class FbtTableAccessor
{
    /**
     * @param string|int $value
     */
    public static function getEnumResult($value): array
    {
        return [[$value], null];
    }

    /**
     * @param array $variation
     * @param array|null $substitution
     * @param int|null $gender
     */
    public static function getGenderResult(array $variation, ?array $substitution, $gender = null): array
    {
        // value is ignored here which will be used in alternative implementation
        // for different platform
        return [$variation, $substitution];
    }

    /**
     * @param array $variation
     * @param array|null $substitution
     * @param int|float|null $numberValue
     */
    public static function getNumberResult(array $variation, ?array $substitution, $numberValue = null): array
    {
        // value is ignored here which will be used in alternative implementation
        // for different platform
        return [$variation, $substitution];
    }

    // For an fbtParam where no gender or plural/number variation exists
    public static function getSubstitution(array $substitution): array
    {
        return [null, $substitution];
    }

    public static function getPronounResult(int $genderKey): array
    {
        return [[$genderKey, '*'], null];
    }
}
