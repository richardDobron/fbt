<?php

namespace fbt\Runtime\Shared;

use fbt\FbtConfig;
use fbt\Lib\IntlViewerContext;
use fbt\Lib\IntlViewerContextInterface;
use fbt\Transform\FbtTransform\FbtTransform;

class FbtHooks
{
    /* @var null|string */
    private static $locale = null;
    /* @var string */
    private static $inlineMode = 'NO_INLINE';
    /* @var array */
    private static $actions = [];
    /* @var array */
    public static $sourceStrings = [
        'phrases' => [],
    ];
    /* @var array */
    public static $sourceHashes = [];
    /* @var array */
    public static $storedHashes = [];
    /* @var array */
    public static $impression = [];

    /**
     * @param string $hash
     * @return void
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     */
    public static function logImpression(string $hash): void
    {
        if (! FbtConfig::get('logger')) {
            return;
        }

        self::$impression[$hash] = true;
    }

    /**
     * @param string|null $locale
     * @return string
     */
    public static function locale(?string $locale = null): string
    {
        if (func_num_args() === 1) {
            self::$locale = $locale;
        }

        return self::$locale
            ?: self::getIntlViewerContext()->getLocale();
    }

    /**
     * @param string|null $inlineMode
     * @return string
     */
    public static function inlineMode(?string $inlineMode = null): string
    {
        if (func_num_args() === 1) {
            self::$inlineMode = $inlineMode;
        }

        return self::$inlineMode ?? 'NO_INLINE';
    }

    public static function getIntlViewerContext(): IntlViewerContextInterface
    {
        $viewerContext = FbtConfig::get('viewerContext');

        if (is_string($viewerContext) && class_exists($viewerContext)) {
            $viewerContext = new $viewerContext();
        }

        return $viewerContext ?? new IntlViewerContext();
    }

    /**
     * @param string $patternHash
     * @return void
     */
    public static function onTranslationOverride(string $patternHash): void
    {
        if (isset(self::$actions[__FUNCTION__])) {
            self::$actions[__FUNCTION__](...func_get_args());
        }
    }

    /**
     * @return void
     * @throws \Throwable
     */
    public static function onTerminating(): void
    {
        if (isset(self::$actions[__FUNCTION__])) {
            self::$actions[__FUNCTION__]();

            return;
        }

        register_shutdown_function(function () {
            FbtHooks::storePhrases();
            FbtHooks::storeImpressions();
        });
    }

    public static function canInline(array $backtrace): bool
    {
        if (isset(self::$actions[__FUNCTION__])) {
            return self::$actions[__FUNCTION__](...func_get_args());
        }

        return true;
    }

    /**
     * @param array $phrase
     * @param int|null $parentId
     * @return null|int
     */
    public static function savePhrase(array $phrase, int $parentId = null): ?int
    {
        if (isset(self::$actions[__FUNCTION__])) {
            return self::$actions[__FUNCTION__](...func_get_args());
        }

        foreach ($phrase['hashToText'] as $hash => $text) {
            FbtHooks::$storedHashes[$hash] = true;
        }

        $phraseSource = [
            'type' => $phrase['type'],
            'jsfbt' => $phrase['jsfbt'],
        ];

        $hash = md5(json_encode($phraseSource) . $phrase['desc']);

        self::$sourceStrings['phrases'][] = $phrase;
        self::$sourceHashes[$hash] = count(self::$sourceStrings['phrases']) - 1;

        if (! empty($parentId)) {
            self::$sourceStrings['childParentMappings'][self::$sourceHashes[$hash]] = $parentId;
        }

        return self::$sourceHashes[$hash];
    }

    /**
     * @throws \Throwable
     */
    public static function storePhrases()
    {
        $fbtDir = FbtConfig::get('path') . '/';
        $file = $fbtDir . '.source_strings.json';

        if (file_exists($file)) {
            self::$sourceStrings = json_decode(file_get_contents($file), true);
            $phrases = self::$sourceStrings['phrases'] ?? [];

            if ($phrases) {
                $hashToText = array_merge(...array_column($phrases, 'hashToText'));
                foreach (array_keys($hashToText) as $hash) {
                    self::$storedHashes[$hash] = true;
                }
            }
        } else if (! is_dir($fbtDir)) {
            mkdir($fbtDir, 0777, true);
        }

        if (isset(self::$actions[__FUNCTION__])) {
            self::$actions[__FUNCTION__](...func_get_args());

            return;
        }

        $sourceStrings = FbtTransform::toArray();
        $parentIds = [];

        foreach ($sourceStrings['phrases'] as $index => $phrase) {
            if (isset(self::$storedHashes[array_keys($phrase['hashToText'])[0]])) {
                continue;
            }

            $parentKey = $sourceStrings['childParentMappings'][$index] ?? null;

            $parentIds[$index] = self::savePhrase($phrase, $parentIds[$parentKey] ?? null);
        }

        $flags = 0;

        if (FbtConfig::get('prettyPrint')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        file_put_contents($file, json_encode(self::$sourceStrings, $flags), LOCK_EX);

        FbtTransform::$childToParent = [];
        FbtTransform::$phrases = [];
        self::$sourceHashes = [];
    }

    /**
     * @return void
     */
    public static function storeImpressions(): void
    {
        if (isset(self::$actions[__FUNCTION__])) {
            self::$actions[__FUNCTION__](...func_get_args());
        }
    }

    public static function loadTranslationGroups(): array
    {
        if (isset(self::$actions[__FUNCTION__])) {
            return self::$actions[__FUNCTION__](...func_get_args());
        }

        return [];
    }

    /**
     * @param string $tag
     * @param callable $action
     * @return void
     */
    public static function register(string $tag, callable $action): void
    {
        self::$actions[$tag] = $action;
    }

    /**
     * @param string $tag
     * @return void
     */
    public static function unregister(string $tag): void
    {
        if (array_key_exists($tag, self::$actions)) {
            unset(self::$actions[$tag]);
        }
    }
}
