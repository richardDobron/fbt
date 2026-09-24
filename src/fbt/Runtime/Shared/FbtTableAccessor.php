<?php

/**
 * Provides return values for fbt constructs calls. Here lives the platform
 * specific implementation.
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

    public static function getGenderResult(array $variation, ?array $substitution, int $_gender): array
    {
        // value is ignored here which will be used in alternative implementation
        // for different platform
        return [$variation, $substitution];
    }

    /**
     * @param array $variation
     * @param array|null $substitution
     * @param int|float $_numberValue
     */
    public static function getNumberResult(array $variation, ?array $substitution, $_numberValue): array
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
