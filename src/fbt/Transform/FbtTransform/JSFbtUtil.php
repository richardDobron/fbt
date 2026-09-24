<?php

namespace fbt\Transform\FbtTransform;

/**
 * A JSFBT tree is either a leaf:
 *   ['desc' => string, 'text' => string, 'tokenAliases' => ?array, 'outerTokenName' => ?string]
 * or an array whose values are JSFBT trees (keyed by variation values).
 */
class JSFbtUtil
{
    /**
     * @param mixed $value
     *
     * @return array|null a TableJSFBTTreeLeaf if the given value matches its shape, or null
     */
    public static function coerceToTableJSFBTTreeLeaf($value): ?array
    {
        return is_array($value) &&
            is_string($value['desc'] ?? null) &&
            is_string($value['text'] ?? null) &&
            (is_array($value['tokenAliases'] ?? null) || ! isset($value['tokenAliases']))
            ? $value
            : null;
    }

    private static function _runOnNormalizedJSFBTLeaves(array $value, callable $callback): void
    {
        $leaflet = self::coerceToTableJSFBTTreeLeaf($value);
        if ($leaflet !== null) {
            $callback($leaflet);

            return;
        }

        foreach ($value as $subTree) {
            self::_runOnNormalizedJSFBTLeaves($subTree, $callback);
        }
    }

    /**
     * @param array $phrase - phrase with a `jsfbt` property
     */
    public static function onEachLeaf(array $phrase, callable $callback): void
    {
        self::_runOnNormalizedJSFBTLeaves($phrase['jsfbt']['t'], $callback);
    }

    /**
     * Clone `tree` and replace each leaf in the cloned tree with the result of
     * calling `convertLeaf`.
     *
     * @return mixed
     */
    public static function mapLeaves(array $tree, callable $convertLeaf)
    {
        $leaflet = self::coerceToTableJSFBTTreeLeaf($tree);
        if ($leaflet !== null) {
            return $convertLeaf($leaflet);
        }

        $newFbtTree = [];
        foreach ($tree as $tableKey => $subTree) {
            $newFbtTree[$tableKey] = self::mapLeaves($subTree, $convertLeaf);
        }

        return $newFbtTree;
    }
}
