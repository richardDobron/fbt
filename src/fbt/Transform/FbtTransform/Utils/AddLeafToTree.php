<?php

namespace fbt\Transform\FbtTransform\Utils;

use function fbt\invariant;

class AddLeafToTree
{
    /**
     * Adds a leaf value to a given tree-like array, using the given list of keys (i.e. path).
     * If the path doesn't exist yet, we'll create the intermediate arrays as needed.
     *
     * @example
     *
     * // empty starting tree
     * AddLeafToTree::addLeafToTree($tree = [], ['a', 'b', 'c'], ['val' => 111])
     *
     * Result:
     *   ['a' => ['b' => ['c' => ['val' => 111]]]]
     *
     * @param array $tree - modified in place
     * @param array<string|int> $keys
     * @param mixed $leaf
     *
     * @throws \fbt\Exceptions\FbtException Trying to overwrite an existing tree leaf will throw an error
     */
    public static function addLeafToTree(array &$tree, array $keys, $leaf): void
    {
        $branch = &$tree;
        $lastIndex = count($keys) - 1;

        foreach (array_values($keys) as $index => $key) {
            $isLast = $index === $lastIndex;
            invariant(
                ! $isLast || ! isset($branch[$key]),
                'Overwriting an existing tree leaf is not allowed. keys=`%s`',
                json_encode(array_values($keys), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            if (! isset($branch[$key])) {
                $branch[$key] = $isLast ? $leaf : [];
            }

            $branch = &$branch[$key];
        }

        unset($branch);
    }
}
