<?php

namespace fbt\Transform\FbtTransform\Translate;

class VariationConstraintUtils
{
    /**
     * Build the aggregate key with which we access the constraint map.  The
     * constraint map maps the given constraints to the appropriate translation
     *
     * e.g. 'user%2:count%24' is the key of [['user', 2], ['count', 24]]
     *
     * @param array<int, array{0: string, 1: int|string}> $constraintKeys
     */
    public static function buildConstraintKey(array $constraintKeys): string
    {
        return implode(':', array_map(function (array $kv) {
            return $kv[0] . '%' . $kv[1];
        }, $constraintKeys));
    }
}
