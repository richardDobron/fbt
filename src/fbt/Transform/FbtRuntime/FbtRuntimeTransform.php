<?php

namespace fbt\Transform\FbtRuntime;

use fbt\Transform\FbtTransform\fbtHash;
use fbt\Transform\FbtTransform\FbtUtils;
use fbt\Transform\FbtTransform\JSFbtUtil;

/**
 * Port of babel-plugin-fbt-runtime: converts the JSFBT payload of a phrase
 * to the runtime-friendly table and options used by `fbt::_()`.
 */
class FbtRuntimeTransform
{
    /**
     * @param array $phrase - phrase with a `jsfbt` property
     * @param array|null $extraOptions
     *
     * @return array{table: string|array, options: array}
     * @throws \fbt\Exceptions\FbtException
     */
    public static function transform(array $phrase, ?array $extraOptions = null): array
    {
        $jsfbt = $phrase['jsfbt'];

        $options = [];
        if ($extraOptions) {
            $options['eo'] = $extraOptions;
        }
        $options['hk'] = fbtHash::fbtHashKey($jsfbt['t']);

        return [
            'table' => self::getRuntimeTable($jsfbt['t']),
            'options' => $options,
        ];
    }

    /**
     * Replaces the leaves of the JSFBT tree with their texts, where clear token
     * names are replaced by their aliases. A single leaf becomes a string.
     *
     * @return string|array
     */
    public static function getRuntimeTable(array $jsfbtTree)
    {
        return JSFbtUtil::mapLeaves($jsfbtTree, function (array $leaf) {
            return FbtUtils::replaceClearTokensWithTokenAliases($leaf['text'], $leaf['tokenAliases'] ?? null);
        });
    }
}
