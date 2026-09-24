<?php

namespace fbt\Runtime;

use function fbt\invariant;

class FbtTable
{
    /**
     * fbt::XXX calls return arguments in the form of
     * [<INDEX>, <SUBSTITUTION>] to be processed by fbt::_
     */
    public const ARG = [
        "INDEX" => 0,
        "SUBSTITUTION" => 1,
    ];

    /**
     * Performs a depth-first search on our table, attempting to access
     * each table entry.  The first entry found is the one we want, as we
     * set defaults after preferred indices.  For example:
     *
     * @param mixed $table - {
     *   // viewer gender
     *   '*': {
     *     // {num} plural
     *     '*': {
     *       // user-defined enum
     *       LIKE: '{num} people liked your update',
     *       COMMENT: '{num} people commented on your update',
     *       POST: '{num} people posted on a wall',
     *     },
     *     SINGULAR: {
     *       LIKE: '{num} person liked your update',
     *       // ...
     *     },
     *     DUAL: { ... }
     *   },
     *   FEMALE: {
     *     // {num} plural
     *     '*': { ... },
     *     SINGULAR: { ... },
     *     DUAL: { ... }
     *   },
     *   MALE: { ... }
     * }
     *
     * Notice that LIKE and COMMENT here both have 'your' in them, whereas
     * POST doesn't.  The fallback ('*') translation for POST will be the same
     * in both the male and female version, so that entry won't exist under
     *   table[FEMALE]['*'] or table[MALE]['*'].
     *
     * Similarly, PLURAL is a number variation that never appears in the table as it
     * is the default/fallback.
     *
     * For example, if we have a female viewer, and a PLURAL number and a POST enum
     * value, in the above example, we'll first attempt to get:
     * table[FEMALE][PLURAL][POST].  undefined. Back Up, attempting to get
     * table[FEMALE]['*'][POST].  undefined also. since it's the same as the '*'
     * table['*'][PLURAL][POST].  ALSO undefined. Deduped to '*'
     * table['*']['*'][POST].  There it is.
     *
     * @param array $args - fbt runtime arguments
     * @param int $argsIndex - argument index we're currently visiting
     * @param array $tokens - inout param. Array will populate the keys used to access the table
     *
     * @return string|array|null
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public static function access($table, array $args, int $argsIndex, array &$tokens = [])
    {
        if ($argsIndex >= count($args)) {
            // We've reached the end of our arguments at a valid entry, in which case
            // table is now a string (leaf) or undefined (key doesn't exist)
            // js~php diff: a [pattern, hash] leaf is a list of two strings
            invariant(
                is_string($table) || (
                    is_array($table)
                    && count($table) === 2
                    && is_string($table[0] ?? null)
                    && is_string($table[1] ?? null)
                ),
                'Expected leaf, but got: %s',
                json_encode($table)
            );

            return $table;
        }

        $arg = $args[$argsIndex];
        $tableIndices = $arg[self::ARG['INDEX']];

        if ($tableIndices === null) {
            return self::access($table, $args, $argsIndex + 1, $tokens);
        }

        // js~php diff: a leaf pattern can also be an array ([pattern, hash]), so only
        // the string leaf can be ruled out here
        invariant(
            ! is_string($table),
            'If tableIndex is non-null, we should have a table, but we got: %s',
            gettype($table)
        );

        // js~php diff: keep supporting scalar indices
        if (! is_array($tableIndices)) {
            $tableIndices = [$tableIndices];
        }

        // Is there a variation? Attempt table access in order of variation preference
        foreach ($tableIndices as $tableIndex) {
            $subTable = $table[$tableIndex] ?? null;
            if ($subTable === null) {
                continue;
            }

            $tokens[] = $tableIndex;
            $pattern = self::access($subTable, $args, $argsIndex + 1, $tokens);
            if ($pattern !== null) {
                return $pattern;
            }
        }

        return null;
    }
}
