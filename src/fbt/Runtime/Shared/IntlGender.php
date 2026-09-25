<?php

namespace fbt\Runtime\Shared;

use function fbt\invariant;

use fbt\Lib\DisplayGenderConst;
use fbt\Runtime\GenderConst;

class IntlGender
{
    /**
     * Map an array of genders to a single value.
     * Logic here mirrors that of :fbt:pronoun::render().
     *
     * @param int[] $genders
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public static function fromMultiple(array $genders): int
    {
        invariant(0 < count($genders), 'Cannot have pronoun for zero people');

        return count($genders) === 1 ? reset($genders) : GenderConst::UNKNOWN_PLURAL;
    }

    /**
     * Maps a DisplayGenderConst value to a GenderConst value usable by Fbt.
     */
    public static function fromDisplayGender(string $gender): int
    {
        switch ($gender) {
            case DisplayGenderConst::MALE:
                return GenderConst::MALE_SINGULAR;
            case DisplayGenderConst::FEMALE:
                return GenderConst::FEMALE_SINGULAR;
            case DisplayGenderConst::NEUTER:
                return GenderConst::NEUTER_SINGULAR;
            default:
                return GenderConst::NOT_A_PERSON;
        }
    }
}
