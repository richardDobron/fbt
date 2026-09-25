<?php

namespace fbt\Runtime;

/**
 * Port of upstream GenderConst (the same values as Gender::GENDER_CONST)
 */
final class GenderConst
{
    public const NOT_A_PERSON = 0;
    public const FEMALE_SINGULAR = 1;
    public const MALE_SINGULAR = 2;
    public const FEMALE_SINGULAR_GUESS = 3;
    public const MALE_SINGULAR_GUESS = 4;
    public const MIXED_UNKNOWN = 5;
    public const NEUTER_SINGULAR = 6;
    public const UNKNOWN_SINGULAR = 7;
    public const FEMALE_PLURAL = 8;
    public const MALE_PLURAL = 9;
    public const NEUTER_PLURAL = 10;
    public const UNKNOWN_PLURAL = 11;
}
