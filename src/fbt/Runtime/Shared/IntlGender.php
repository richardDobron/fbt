<?php

namespace fbt\Runtime\Shared;

use function fbt\invariant;

use fbt\Lib\DisplayGenderConst;
use fbt\Runtime\Gender;

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

        return count($genders) === 1 ? reset($genders) : Gender::GENDER_CONST['UNKNOWN_PLURAL'];
    }

    /**
     * Maps a DisplayGenderConst value to a Gender::GENDER_CONST value usable by Fbt.
     */
    public static function fromDisplayGender(string $gender): int
    {
        switch ($gender) {
            case DisplayGenderConst::MALE:
                return Gender::GENDER_CONST['MALE_SINGULAR'];
            case DisplayGenderConst::FEMALE:
                return Gender::GENDER_CONST['FEMALE_SINGULAR'];
            case DisplayGenderConst::NEUTER:
                return Gender::GENDER_CONST['NEUTER_SINGULAR'];
            default:
                return Gender::GENDER_CONST['NOT_A_PERSON'];
        }
    }
}
