<?php

namespace fbt\Runtime\Shared;

use fbt\FbtConfig;
use fbt\Lib\IntlViewerContext;
use fbt\Lib\IntlViewerContextInterface;
use fbt\Runtime\FbtTranslations;
use fbt\Transform\FbtTransform\FbtTransform;
use fbt\Util\JsJson;

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
        if (isset(self::$actions[__FUNCTION__])) {
            self::$actions[__FUNCTION__](...func_get_args());
        }

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
     * @param array{hash: string|null, translation: string} $context
     */
    public static function getErrorListener(array $context): ?IFbtErrorListener
    {
        if (isset(self::$actions['errorListener'])) {
            return self::$actions['errorListener'](...func_get_args());
        }

        return null;
    }

    /**
     * @param array{
     *   contents: array,
     *   errorListener: IFbtErrorListener|null,
     *   extraOptions: array|null,
     *   patternString: string,
     *   patternHash: string|null,
     *   reporting: bool
     * } $input
     *
     * @return mixed
     */
    public static function getFbtResult(array $input)
    {
        if (isset(self::$actions[__FUNCTION__])) {
            return self::$actions[__FUNCTION__](...func_get_args());
        }

        $inlineMode = self::inlineMode();

        if ($input['reporting'] && $inlineMode && $inlineMode !== 'NO_INLINE') {
            return new InlineFbtResult(
                $input['contents'],
                $inlineMode,
                $input['patternString'],
                $input['patternHash'],
                $input['errorListener']
            );
        }

        return FbtResult::get($input);
    }

    /**
     * @param array{
     *   contents: array,
     *   errorListener: IFbtErrorListener|null,
     *   extraOptions: array|null,
     *   patternString: string,
     *   patternHash: string|null,
     *   reporting: bool
     * } $input
     *
     * @return mixed
     */
    public static function getFbsResult(array $input)
    {
        if (isset(self::$actions[__FUNCTION__])) {
            return self::$actions[__FUNCTION__](...func_get_args());
        }

        return FbtPureStringResult::get($input);
    }

    /**
     * @param array{table: string|array, args: array|null, options: array} $input
     *
     * @return array{table: string|array, args: array|null}
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     */
    public static function getTranslatedInput(array $input): array
    {
        if (isset(self::$actions[__FUNCTION__])) {
            return self::$actions[__FUNCTION__](...func_get_args()) ?? $input;
        }

        $translatedInput = FbtTranslations::getTranslatedInput($input['table'], $input['args'] ?? [], $input['options']);

        if ($translatedInput === null) {
            return $input;
        }

        return [
            'table' => $translatedInput[0],
            'args' => $translatedInput[1],
        ];
    }

    public static function getFallback(string $locale): ?string
    {
        $fallback = FbtConfig::get('fallback');

        return $fallback[$locale] ?? null;
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
    public static function savePhrase(array $phrase, ?int $parentId = null): ?int
    {
        if (isset(self::$actions[__FUNCTION__])) {
            return self::$actions[__FUNCTION__](...func_get_args());
        }

        foreach (array_keys($phrase['hashToLeaf']) as $hash) {
            FbtHooks::$storedHashes[$hash] = true;
        }

        $hash = self::getPhraseKey($phrase);

        self::$sourceStrings['phrases'][] = $phrase;
        self::$sourceHashes[$hash] = count(self::$sourceStrings['phrases']) - 1;

        if ($parentId !== null) {
            self::$sourceStrings['childParentMappings'][self::$sourceHashes[$hash]] = $parentId;
        }

        return self::$sourceHashes[$hash];
    }

    /**
     * Identity of a collected phrase
     *
     * @throws \fbt\Exceptions\FbtException
     */
    private static function getPhraseKey(array $phrase): string
    {
        return md5(JsJson::stringify($phrase['jsfbt']['t']) . json_encode($phrase['jsfbt']['m']));
    }

    /**
     * Drops phrases collected by fbt v4 (without `hashToLeaf`), which can't be
     * used anymore, and remaps the child to parent mappings accordingly.
     */
    private static function withoutLegacyPhrases(array $sourceStrings): array
    {
        $phrases = [];
        $indexMap = [];
        foreach ($sourceStrings['phrases'] ?? [] as $index => $phrase) {
            if (isset($phrase['hashToLeaf'], $phrase['jsfbt']['t'])) {
                $indexMap[$index] = count($phrases);
                $phrases[] = $phrase;
            }
        }

        $childParentMappings = [];
        foreach ($sourceStrings['childParentMappings'] ?? [] as $child => $parent) {
            if (isset($indexMap[$child], $indexMap[$parent])) {
                $childParentMappings[$indexMap[$child]] = $indexMap[$parent];
            }
        }

        $sourceStrings['phrases'] = $phrases;
        $sourceStrings['childParentMappings'] = $childParentMappings;

        return $sourceStrings;
    }

    /**
     * JSFBT trees are serialized as JS objects (never as lists).
     */
    private static function toSerializableSourceStrings(array $sourceStrings): array
    {
        foreach ($sourceStrings['phrases'] ?? [] as $index => $phrase) {
            if (isset($phrase['jsfbt']['t'])) {
                $sourceStrings['phrases'][$index]['jsfbt']['t'] = JsJson::toJsObject($phrase['jsfbt']['t']);
            }
        }

        if (isset($sourceStrings['childParentMappings'])) {
            $sourceStrings['childParentMappings'] = JsJson::toJsObject($sourceStrings['childParentMappings']);
        }

        return $sourceStrings;
    }

    /**
     * @throws \Throwable
     */
    public static function storePhrases(): void
    {
        $fbtDir = FbtConfig::get('path') . '/';
        $file = $fbtDir . '.source_strings.json';

        self::$sourceHashes = [];
        self::$sourceStrings = ['phrases' => []];

        if (! is_dir($fbtDir)) {
            mkdir($fbtDir, 0777, true);
        }

        if (isset(self::$actions[__FUNCTION__])) {
            if (file_exists($file)) {
                self::loadSourceStrings(self::readLocked($file));
            }

            self::$actions[__FUNCTION__](...func_get_args());

            return;
        }

        // The file is read and written under an exclusive lock, so that concurrent
        // processes neither fail to read it nor overwrite each other's phrases
        $handle = fopen($file, 'c+');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open the source strings file: $file");
        }

        try {
            flock($handle, LOCK_EX);
            self::loadSourceStrings((string)stream_get_contents($handle));
            self::mergeCollectedPhrases();

            $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
            if (FbtConfig::get('prettyPrint')) {
                $flags |= JSON_PRETTY_PRINT;
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode(self::toSerializableSourceStrings(self::$sourceStrings), $flags));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        FbtTransform::$childToParent = [];
        FbtTransform::$phrases = [];
        self::$sourceHashes = [];
    }

    /**
     * Reads a file under a shared lock (see storePhrases())
     */
    public static function readLocked(string $file): string
    {
        $handle = fopen($file, 'r');
        if ($handle === false) {
            return '';
        }

        try {
            flock($handle, LOCK_SH);

            return (string)stream_get_contents($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function loadSourceStrings(string $contents): void
    {
        self::$sourceStrings = self::withoutLegacyPhrases(json_decode($contents, true) ?: []);

        foreach (self::$sourceStrings['phrases'] as $index => $phrase) {
            foreach (array_keys($phrase['hashToLeaf']) as $hash) {
                self::$storedHashes[$hash] = true;
            }

            self::$sourceHashes[self::getPhraseKey($phrase)] = $index;
        }
    }

    /**
     * Adds the phrases collected by the transform to the stored ones.
     */
    private static function mergeCollectedPhrases(): void
    {
        $sourceStrings = FbtTransform::toArray();
        $phraseIds = [];

        foreach ($sourceStrings['phrases'] as $index => $phrase) {
            $parentKey = $sourceStrings['childParentMappings'][$index] ?? null;
            $parentId = $parentKey !== null ? ($phraseIds[$parentKey] ?? null) : null;

            $phraseKey = self::getPhraseKey($phrase);
            if (isset(self::$sourceHashes[$phraseKey])) {
                // already stored
                $phraseIds[$index] = self::$sourceHashes[$phraseKey];

                continue;
            }

            $phraseIds[$index] = self::savePhrase($phrase, $parentId);
        }
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
