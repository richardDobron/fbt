<?php

/**
 * Same set of 'usage' values as in :fbt:pronoun::type. Must match
 * PRONOUN_USAGE in fbt.js.
 * NOTE: Using 'usage' here, whereas :fbt:pronoun uses 'type'. It's confusing,
 * but fbt() already uses 'type' as the tag within the fbt table data for the
 * to-be-localized text.
 */

namespace fbt\Transform\FbtTransform;

class FbtConstants
{
    const VALID_PRONOUN_USAGES = [
        "object" => 0,
        "possessive" => 1,
        "reflexive" => 2,
        "subject" => 3,
    ];

    const PRONOUN_USAGE = [
        "OBJECT" => 0,
        "POSSESSIVE" => 1,
        "REFLEXIVE" => 2,
        "SUBJECT" => 3,
    ];

    const PLURAL_REQUIRED_ATTRIBUTES = [
        'count' => true,
    ];

    const SHOW_COUNT = [
        'yes' => true,
        'no' => true,
        'ifMany' => true,
    ];

    const PLURAL_OPTIONS = [
        'value' => true, // optional value to replace token (rather than count)
        'showCount' => self::SHOW_COUNT,
        'name' => true, // token
        'many' => true,
    ];

    public static function validPluralOptions(): array
    {
        return array_merge(
            [],
            self::PLURAL_OPTIONS,
            self::PLURAL_REQUIRED_ATTRIBUTES
        );
    }

    const VALID_PRONOUN_OPTIONS = [ // js~php diff
        'human' => ['true' => true, 'false' => true],
        'capitalize' => ['true' => true, 'false' => true],
    ];

    /**
     * Valid options allowed in the fbt(...) calls.
     */
    const VALID_FBT_OPTIONS = [
        'project' => true,
        'author' => true,
        'preserveWhitespace' => true,
        'subject' => true,
        'common' => true,
        'doNotExtract' => true,
        'reporting' => true, // fbt diff
    ];

    const FBT_BOOLEAN_OPTIONS = [
        'preserveWhitespace' => true,
        'doNotExtract' => true,
    ];

    const FBT_CALL_MUST_HAVE_AT_LEAST_ONE_OF_THESE_ATTRIBUTES = ['desc', 'common'];

    const FBT_REQUIRED_ATTRIBUTES = [
        'desc' => true,
    ];

    const PRONOUN_REQUIRED_ATTRIBUTES = [
        'type' => true,
        'gender' => true,
    ];

    const PLURAL_PARAM_TOKEN = 'number';

    const REQUIRED_PARAM_OPTIONS = [
        'name' => true,
    ];

    public static function validParamOptions(): array
    {
        return array_merge(
            [
                'number' => true,
                'gender' => true,
            ],
            self::REQUIRED_PARAM_OPTIONS
        );
    }

    const FBT_TYPE = [
        'TABLE' => 'table',
        'TEXT' => 'text',
    ];

    const MODULE_NAME = [
        'FBT' => 'fbt',
        // 'REACT_FBT' => 'Fbt',
        'FBS' => 'fbs',
    ];
}
