<?php

namespace fbt\Runtime;

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
     *
     * @return string|array|null
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public static function access($table, array $args, int $argsIndex)
    {
        // js~php diff:

        // Either we've reached the end of our arguments at a valid entry, in which
        // case table is now a string (leaf) or we've accessed a key that didn't exist
        // in the table, in which case we return null
        if ($argsIndex >= count($args)) {
            return $table;
        } elseif ($table === null) {
            return null;
        }

        $pattern = null;
        $arg = $args[$argsIndex];
        $tableIndex = $arg[self::ARG['INDEX']];

        // Do we have a variation? Attempt table access in variation order
        if (is_array($tableIndex)) {
            foreach ($tableIndex as $index) {
                $subTable = $table[$index] ?? null;
                $pattern = self::access($subTable, $args, $argsIndex + 1);
                if ($pattern !== null) {
                    break;
                }
            }
        } else {
            $table = $tableIndex !== null ? $table[$tableIndex] ?? null : $table;
            $pattern = self::access($table, $args, $argsIndex + 1);
        }

        return $pattern;
    }
}
