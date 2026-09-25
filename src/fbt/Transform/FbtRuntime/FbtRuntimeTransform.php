<?php

namespace fbt\Transform\FbtRuntime;

use fbt\Services\TranslationsGeneratorService;
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
        $options['hk'] = call_user_func(TranslationsGeneratorService::getFbtHashKey(), $jsfbt['t']);

        return [
            'table' => self::getRuntimeTable($jsfbt['t']),
            'options' => $options,
        ];
    }

    /**
     * @return string|array
     */
    public static function getRuntimeTable(array $jsfbtTree)
    {
        return JSFbtUtil::mapLeaves($jsfbtTree, function (array $leaf) {
            return FbtUtils::replaceClearTokensWithTokenAliases($leaf['text'], $leaf['tokenAliases'] ?? null);
        });
    }
}
