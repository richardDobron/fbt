<?php

namespace fbt\Services;

use fbt\FbtConfig;

use function fbt\invariant;

use fbt\Runtime\Shared\FbtHooks;
use fbt\Transform\FbtTransform\fbtHash;
use fbt\Transform\FbtTransform\FbtUtils;
use fbt\Transform\FbtTransform\Translate\FbtSite;
use fbt\Transform\FbtTransform\Translate\FbtSiteMetaEntry;
use fbt\Transform\FbtTransform\Translate\TranslationBuilder;
use fbt\Transform\FbtTransform\Translate\TranslationConfig;
use fbt\Transform\FbtTransform\Translate\TranslationData;
use fbt\Util\JsJson;

/**
 * Reads the JSON payload of the source strings of the following form:
 *
 * {
 *  "phrases": [
 *    {
 *      "hashToLeaf": {
 *        "QKdbwQvVn+Ag+Qn/Wp+A/g==": {"text": "Your FBT Demo", "desc": "title"}
 *      },
 *      ...,
 *      "jsfbt": {"t": {"desc": "title", "text": "Your FBT Demo"}, "m": []}
 *    },
 *    ...
 *  ],
 * }
 *
 * and JSON payloads (either in an arbitrary number of files when
 * using --translations) or grouped in a monolithic JSON file when
 * using --stdin array under `translationGroups`
 *
 *  {
 *    "fb-locale": "fb_HX",
 *    "translations": {
 *      "40bd5bc10bd59fe020569068cfd7d814": {
 *        "tokens": {},
 *        "types": {},
 *        "translations": [{
 *          "translation": "Y0ur FBT D3m0",
 *          "variations": []
 *        }]
 *      }
 *    }
 *  }
 *
 * and by default, returns the translated phrases in the following format:
 *
 * [
 *   {
 *     "fb-locale":"fb_HX",
 *     "translatedPhrases":[
 *       "Y0ur FBT D3m0",
 *        ...,
 *     ]
 *   }
 *   ...,
 * ]
 *
 * If intended for use as a runtime dictionary (accessed within the
 * runtime `fbt::_` via `FbtTranslations` when using the
 * FbtRuntime plugin), You can rely on the jenkins hash default
 *
 * When using the runtime dictionary options, output will be of the form:
 *
 *  {
 *    <locale>: {
 *      <hash>: <payload>,
 *      ...
 *    },
 *    ...
 *   }
 *
 */
class TranslationsGeneratorService
{
    /**
     * Options of the translation (translateUtils.js), js~php diff: `inclHash` (the
     * hash of the source string is included in the translated leaves, for impressions)
     * and `fallback` (translation groups of the fallback locales)
     */
    private const DEFAULT_OPTIONS = [
        'jenkins' => false,
        'hashModule' => false,
        'strict' => false,
        'inclHash' => false,
        'fallback' => [],
    ];

    /**
     * @param string[] $translationFiles
     *
     * @return array - translated groups, or translations by locale and hash
     * @throws \fbt\Exceptions\FbtException
     */
    public static function processFiles(string $stringFile, array $translationFiles, array $options = []): array
    {
        $phrases = self::parseJSONFile($stringFile)['phrases'];
        $groups = array_map([self::class, 'parseJSONFile'], $translationFiles);

        return self::processJSON([
            'phrases' => $phrases,
            'translationGroups' => $groups,
        ], $options);
    }

    /**
     * @param array{phrases: array, translationGroups: array} $json
     *
     * @return array - translated groups, or translations by locale and hash
     * @throws \fbt\Exceptions\FbtException
     */
    public static function processJSON(array $json, array $options = []): array
    {
        $options += self::DEFAULT_OPTIONS;
        $fbtSites = array_map([FbtSite::class, 'fromScan'], $json['phrases']);

        return self::processGroups(
            $json['phrases'],
            array_map(function (array $group) use ($fbtSites, $options) {
                return self::processTranslations($fbtSites, $group, $options);
            }, $json['translationGroups']),
            $options
        );
    }

    /**
     * @throws \Exception
     */
    private static function parseJSONFile(string $filepath): array
    {
        $contents = @file_get_contents($filepath);
        $json = $contents !== false ? json_decode($contents, true) : null;

        if (! is_array($json)) {
            throw new \Exception(($contents === false ? 'Unable to read the file' : json_last_error_msg()) . "\nFile path: \"$filepath\"");
        }

        return $json;
    }

    /**
     * @return callable|null
     */
    private static function getHashFunction(array $options)
    {
        if ($options['jenkins']) {
            return self::getFbtHashKey();
        } elseif ($options['hashModule'] !== false && $options['hashModule'] !== null) {
            return self::loadHashModule($options['hashModule']);
        }

        return null;
    }

    public static function getFbtHashKey(): callable
    {
        $hashModule = FbtConfig::get('fbtHashKeyModule');

        return $hashModule ? self::loadHashModule($hashModule) : [fbtHash::class, 'fbtHashKey'];
    }

    /**
     * js~php diff: a hash module is a callable, or a PHP file returning a callable
     *
     * @param callable|string $hashModule
     */
    private static function loadHashModule($hashModule): callable
    {
        if (is_string($hashModule) && is_file($hashModule)) {
            $hashModule = require $hashModule;
        }

        invariant(is_callable($hashModule), 'Expected the hash module to be a callable: %s', FbtUtils::varDump($hashModule));

        return $hashModule;
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    private static function processGroups(array $phrases, array $translatedGroups, array $options): array
    {
        $fbtHash = self::getHashFunction($options);

        if ($fbtHash === null) {
            return $translatedGroups;
        }

        $localeToHashToFbt = [];
        foreach ($translatedGroups as $group) {
            $localeToHashToFbt[$group['fb-locale']] = [];
            foreach ($phrases as $idx => $phrase) {
                $translatedFbt = $group['translatedPhrases'][$idx];
                invariant(
                    isset($phrase['jsfbt']),
                    "Expect every phrase to have 'jsfbt' field. However, 'jsfbt' is missing in the phrase at index %s.",
                    $idx
                );
                $hash = $fbtHash($phrase['jsfbt']['t']);
                $localeToHashToFbt[$group['fb-locale']][$hash] = $translatedFbt;
            }
        }

        return $localeToHashToFbt;
    }

    /**
     * @throws \Exception
     */
    private static function checkAndFilterTranslations(string $locale, array $translations, array $options): array
    {
        $filteredTranslations = [];
        foreach ($translations as $hash => $translation) {
            if ($translation === null) {
                $message = "Missing $locale translation for string ($hash)";
                if ($options['strict']) {
                    throw new \Exception($message);
                } elseif (defined('STDERR')) {
                    fwrite(STDERR, $message . PHP_EOL);
                }

                continue;
            }
            $filteredTranslations[$hash] = $translation;
        }

        return $filteredTranslations;
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    private static function processTranslations(array $fbtSites, array $group, array $options): array
    {
        $config = TranslationConfig::fromFBLocale($group['fb-locale']);
        $translations = FbtUtils::objMap(
            self::checkAndFilterTranslations($group['fb-locale'], $group['translations'], $options),
            [TranslationData::class, 'fromJSON']
        );

        // js~php diff: translations of the fallback locale are used for missing translations
        $fallback = FbtHooks::getFallback($group['fb-locale']);
        $fallbackTranslations = FbtUtils::objMap(
            array_filter($options['fallback'][$fallback]['translations'] ?? [], 'is_array'),
            [TranslationData::class, 'fromJSON']
        );

        $translatedPhrases = array_map(function (FbtSite $fbtSite) use ($translations, $fallbackTranslations, $config, $options) {
            return (new TranslationBuilder($translations, $config, $fbtSite, $options['inclHash'], $fallbackTranslations))->build();
        }, $fbtSites);

        return [
            'fb-locale' => $group['fb-locale'],
            'translatedPhrases' => $translatedPhrases,
        ];
    }

    /**
     * js~php diff: equivalent of JS `JSON.stringify()` (pretty printed with 2 spaces)
     */
    /**
     * @param array $output - translated groups, or translations by locale and hash
     * @param bool $pretty
     * @param bool|null $isList - whether the output are translated groups (inferred by default)
     */
    public static function toJSON(array $output, bool $pretty, ?bool $isList = null): string
    {
        $isList = $isList ?? ($output === [] || array_keys($output) === range(0, count($output) - 1));
        $data = $isList
            ? array_map(function (array $group) {
                return [
                    'fb-locale' => $group['fb-locale'],
                    'translatedPhrases' => array_map([JsJson::class, 'toJsObject'], $group['translatedPhrases']),
                ];
            }, $output)
            : JsJson::toJsObject($output);

        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | ($pretty ? JSON_PRETTY_PRINT : 0));

        return $pretty
            ? preg_replace_callback('/^( {4})+/m', function (array $matches) {
                return str_repeat('  ', (int)(strlen($matches[0]) / 4));
            }, $json)
            : $json;
    }

    /**
     * @param string[] $files
     * @throws \Exception
     */
    private static function readTranslationGroups(array $files): array
    {
        $groups = [];
        foreach ($files as $file) {
            $json = self::parseJSONFile($file);
            foreach (isset($json['fb-locale']) ? [$json] : array_values($json) as $group) {
                $locale = $group['fb-locale'];
                $groups[$locale] = [
                    'fb-locale' => $locale,
                    'translations' => ($groups[$locale]['translations'] ?? []) + ($group['translations'] ?? []),
                ];
            }
        }

        return $groups;
    }

    /**
     * Translate fbt phrases with provided translations (js~php diff: into the runtime
     * dictionary `<path>/translatedFbts.json`)
     *
     * @param string $path
     * @param string|null $translationsPath - glob of the translation files (the
     *   `loadTranslationGroups` hook is used by default)
     * @param string|null $stdin
     * @param bool $pretty
     *
     * @throws \fbt\Exceptions\FbtException
     * @throws \Exception
     */
    public function exportTranslations(string $path, ?string $translationsPath, ?string $stdin, bool $pretty): void
    {
        if (empty($stdin)) {
            $sourceStrings = json_decode(FbtHooks::readLocked($path . '/.source_strings.json'), true);
            if ($translationsPath) {
                $groups = self::readTranslationGroups(glob($translationsPath) ?: []);
            } else {
                $groups = FbtHooks::loadTranslationGroups();
                $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | ($pretty ? JSON_PRETTY_PRINT : 0);
                file_put_contents($path . '/.translations.json', json_encode($groups, $flags));
            }
        } else {
            $sourceStrings = json_decode($stdin, true);
            $groups = [];
            foreach ($sourceStrings['translationGroups'] as $group) {
                $groups[$group['fb-locale']] = $group;
            }
        }

        $output = self::processJSON([
            'phrases' => $sourceStrings['phrases'],
            'translationGroups' => array_values($groups),
        ], [
            'hashModule' => self::getFbtHashKey(),
            'inclHash' => true,
            'fallback' => $groups,
        ]);

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        file_put_contents($path . '/translatedFbts.json', json_encode($output, $flags), LOCK_EX);
    }

    /**
     * @param string $source
     * @param string|null $translationsPath
     * @param string $inputPath
     *
     * @throws \Exception
     */
    public function generateTranslations(string $source, ?string $translationsPath, string $inputPath): void
    {
        if (! file_exists($source)) {
            throw new \Exception('Source strings file does not exist: ' . $source);
        }

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;

        $sourceStrings = json_decode(FbtHooks::readLocked($source), true);
        $phrases = $sourceStrings['phrases'];

        $translations = [];
        foreach ($phrases as $phrase) {
            // Tokens and types are aligned (e.g. a pronoun has a type, but no token)
            $metadata = array_values(array_filter($phrase['jsfbt']['m'] ?? []));
            $tokens = array_map(function (array $entry) {
                return $entry['token'] ?? null;
            }, $metadata);
            $types = array_map(function (array $entry) {
                return $entry['type'] ?? null;
            }, $metadata);
            foreach (array_keys($phrase['hashToLeaf']) as $hash) {
                $translations[$hash] = [
                    'translations' => [
                        [
                            'translation' => '',
                            'variations' => [],
                        ],
                    ],
                    'tokens' => $tokens,
                    'types' => array_map([FbtSiteMetaEntry::class, 'getVariationMaskFromType'], $types),
                ];
            }
        }

        if (! empty($translationsPath)) {
            foreach (glob($translationsPath) as $file) {
                // e.g. sk_SK.json, fbt_AC.json
                preg_match('/^([a-zA-Z]{2,3}_[a-zA-Z]{2})\.json$/', basename($file), $match);

                if (! $match) {
                    continue;
                }

                $localeTranslations = json_decode(file_get_contents($file), true);

                $group = $localeTranslations[$match[1]] ?? $localeTranslations ?: [
                    "fb-locale" => $match[1],
                    "translations" => [],
                ];
                $group['translations'] += $translations;

                file_put_contents($file, json_encode($group, $flags));
            }
        } else {
            if (! file_exists($inputPath)) {
                $default = [
                    'phrases' => [],
                    'translationGroups' => [],
                ];

                file_put_contents($inputPath, json_encode($default));
            }

            $translationInput = json_decode(file_get_contents($inputPath), true);
            $translationInput['phrases'] = $phrases;

            foreach ($translationInput['translationGroups'] as &$group) {
                $group['translations'] += $translations;
            }

            file_put_contents($inputPath, json_encode($translationInput, $flags));

            if (! $translationInput['translationGroups']) {
                throw new \Exception(
                    'You have not yet defined any locales for which you want to translate.'
                    . PHP_EOL
                    . 'Set them in the file ' . $inputPath . ' for example like this:'
                    . PHP_EOL
                    . '
{
    "phrases": [ ... ],
    "translationGroups": [
        {
            "fb-locale": "cs_CZ",
            "translations": []
        },
        {
            "fb-locale": "sk_SK",
            "translations": []
        }
    ]
}'
                );
            }
        }
    }
}
