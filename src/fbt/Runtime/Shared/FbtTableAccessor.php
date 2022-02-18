<?php

/**
 * Provides return values for fbt constructs calls. Here lives the platform
 * specific implementation.
 */

namespace fbt\Runtime\Shared;

class FbtTableAccessor
{
    public static function getEnumResult($value): array
    {
        return [$value, null];
    }

    public static function getGenderResult($variation, $substitution, int $_gender): array
    {
        // value is ignored here which will be used in alternative implementation
        // for different platform
        return [$variation, $substitution];
    }

    public static function getNumberResult($variation, $substitution, $value): array
    {
        // value is ignored here which will be used in alternative implementation
        // for different platform
        return [$variation, $substitution];
    }

    // For an fbtParam where no gender or plural/number variation exists
    public static function getSubstitution($substitution): array
    {
        return [null, $substitution];
    }

    public static function getPronounResult($genderKey): array
    {
        return [[$genderKey, '*'], null];
    }
}
