<?php

namespace fbt\Runtime;

class FbtRuntimeTypes
{
    public const PARAM_VARIATION_TYPE = [
        'number' => 0,
        'gender' => 1,
    ];

    public const VALID_PRONOUN_USAGES_TYPE = [
        'object' => 0,
        'possessive' => 1,
        'reflexive' => 2,
        'subject' => 3,
    ];
}
