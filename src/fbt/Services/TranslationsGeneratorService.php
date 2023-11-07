<?php

namespace fbt\Services;

use fbt\Runtime\Shared\FbtHooks;
use fbt\Transform\FbtTransform\fbtHash;
use fbt\Transform\FbtTransform\FbtUtils;
use fbt\Transform\FbtTransform\Translate\TranslationBuilder;
use fbt\Transform\FbtTransform\Translate\TranslationConfig;
use fbt\Transform\FbtTransform\Translate\TranslationData;

/**
 * Reads the JSON payload of the source strings of the following form:
 *
 * {
 *  "phrases": [
 *    {
 *      "hashToText": {
 *        "40bd5bc10bd59fe020569068cfd7d814": "Your FBT Demo"
 *      },
 *      ...,
 *      "jsfbt": "Your FBT Demo"
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
    /* @var array */
    private $translations = [];

    /**
     * @param string $path
     * @param string|null $translationsPath
     * @param bool $pretty
     *
     * @return void
     */
    private function prepareTranslations(string $path, $translationsPath, bool $pretty)
    {
        $fbtDir = $path . '/';

        if (! is_dir($fbtDir)) {
            mkdir($fbtDir, 0755, true);
        }

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        if ($translationsPath) {
            $files = glob($translationsPath);

            foreach ($files as $file) {
                $translations = json_decode(file_get_contents($file), true);
                $this->translations += $translations;
            }

            return;
        }

        $this->translations = FbtHooks::loadTranslationGroups();

        file_put_contents($fbtDir . '/.translations.json', json_encode($this->translations, $flags));
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    private function processTranslations(array $fbtSites, array $group): array
    {
        $config = TranslationConfig::fromFBLocale($group['fb-locale']);
        $translations = FbtUtils::objMap($group['translations'], function ($translation) {
            return TranslationData::fromJSON($translation);
        });
        $translatedPhrases = array_map(function ($fbtSite) use ($translations, $config) {
            // fbt diff: We are including a hash for reporting and logging.
            return (new TranslationBuilder($translations, $config, $fbtSite, true))->build();
        }, $fbtSites);

        return [
            'fb-locale' => $group['fb-locale'],
            'translatedPhrases' => $translatedPhrases,
        ];
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    private function processGroups(array $phrases, array $translatedGroups): array
    {
        $localeToHashToFbt = [];

        foreach ($translatedGroups as $group) {
            $localeToHashToFbt[$group['fb-locale']] = [];
            foreach ($phrases as $idx => $phrase) {
                $translatedFbt = $group['translatedPhrases'][$idx];
                $payload = $phrase['type'] === 'text' ? $phrase['jsfbt'] : $phrase['jsfbt']['t'];
                $hash = fbtHash::fbtHashKey($payload, $phrase['desc']);
                $localeToHashToFbt[$group['fb-locale']][$hash] = $translatedFbt;
            }
        }

        return $localeToHashToFbt;
    }

    /**
     * Generate missing translation hashes from collected source strings
     *
     * @param string $source
     * @param string|null $translationsPath
     * @param string $inputPath
     *
     * @throws \Exception
     */
    public function generateTranslations(string $source, ?string $translationsPath, string $inputPath)
    {
        if (! file_exists($source)) {
            throw new \Exception('Source strings file does not exist: ' . $source);
        }

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;

        $sourceStrings = json_decode(file_get_contents($source), true);
        $phrases = $sourceStrings['phrases'];

        $translations = [];
        foreach ($phrases as $phrase) {
            $metadata = $phrase['jsfbt']['m'] ?? [];

            $tokens = array_column($metadata, "token");
            $types = array_column($metadata, "type");
            foreach ($phrase['hashToText'] as $hash => $text) {
                $translations[$hash] = [
                    'translations' => [
                        [
                            'translation' => '',
                            'variations' => [],
                        ],
                    ],
                    'tokens' => $tokens,
                    'types' => array_map("fbt\Transform\FbtTransform\Translate\FbtSiteMetaEntry::getVariationMaskFromType", $types),
                ];
            }
        }

        if (! empty($translationsPath)) {
            foreach (glob($translationsPath) as $file) {
                preg_match('/^(\w{2}_\w{2}).json$/', basename($file), $match);

                if (! $match) {
                    continue;
                }

                $localeTranslations = json_decode(file_get_contents($file), true);

                if (! $localeTranslations) {
                    $localeTranslations = [
                        $match[1] => [
                            "fb-locale" => $match[1],
                            "translations" => $translations,
                        ],
                    ];
                } else {
                    $localeTranslations[$match[1]]['translations'] += $translations;
                }

                file_put_contents($file, json_encode($localeTranslations, $flags));
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

    /**
     * Translate fbt phrases with provided translations
     *
     * @param string $path
     * @param string|null $translationsPath
     * @param string|null $stdin
     * @param bool $pretty
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public function exportTranslations(string $path, $translationsPath, $stdin, bool $pretty)
    {
        if (empty($stdin)) {
            $this->prepareTranslations($path, $translationsPath, $pretty);

            $file = $path . '/.source_strings.json';
            $sourceStrings = json_decode(file_get_contents($file), true);
        } else {
            $sourceStrings = json_decode($stdin, true);
            $this->translations = $sourceStrings['translationGroups'];
        }

        $phrases = $sourceStrings['phrases'];
        $fbtSites = array_map('\fbt\Transform\FbtTransform\Translate\FbtSite::fromScan', $phrases);
        $translatedGroups = array_map(function ($group) use ($fbtSites) {
            return self::processTranslations($fbtSites, $group);
        }, $this->translations);

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        file_put_contents($path . '/translatedFbts.json', json_encode($this->processGroups($phrases, $translatedGroups), $flags));
    }
}
