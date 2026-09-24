<?php

namespace fbt\Transform\FbtTransform;

use dobron\DomForge\Node;
use fbt\fbt;
use fbt\FbtConfig;
use fbt\Runtime\FbtTranslations;
use fbt\Runtime\Shared\FbtHooks;
use fbt\Transform\FbtTransform\Processors\HTMLFbtProcessor;
use fbt\Transform\FbtTransform\Utils\TextPackager;
use fbt\Util\NodeParser;

class FbtTransform
{
    /** @var array */
    private static $defaultOptions = [];
    /** @var array{file?: string, line?: int} */
    private static $trace = [];
    /** @var bool */
    private static $functional = false;
    /** @var bool */
    private static $init = false;

    /**
     * An array containing all collected phrases.
     * @var array
     */
    public static $phrases = [];
    /**
     * An array containing the child to parent relationships for implicit nodes.
     * @var array<int, int>
     */
    public static $childToParent = [];
    /**
     * Whether phrases are collected by the transform. Disabled while re-rendering
     * an fbt that was already collected.
     * @var bool
     */
    public static $collectPhrases = true;

    /**
     * @param fbt|string $html
     * @param array $trace - location of the fbt() call (file and line)
     * @param bool $functional - whether the HTML comes from the functional form,
     *   i.e. fbt('text', 'desc'), which keeps its whitespace-only strings
     *
     * @return string
     * @throws \Throwable
     * @throws \fbt\Exceptions\FbtException
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function transform($html, array $trace = [], bool $functional = false): string
    {
        self::initDefaultOptions($trace);
        FbtCommon::init([
            'fbtCommon' => FbtConfig::get('fbtCommon'),
            'fbtCommonPath' => FbtConfig::get('fbtCommonPath'),
        ]);

        if (! self::$init) {
            $translations = FbtConfig::get('path') . '/translatedFbts.json';
            if (file_exists($translations)) {
                FbtTranslations::registerTranslations(json_decode(FbtHooks::readLocked($translations), true));
            }

            FbtHooks::onTerminating();

            self::$init = true;
        }

        $previous = [self::$trace, self::$functional];
        self::$trace = $trace;
        self::$functional = $functional;

        try {
            $dom = NodeParser::parse((string)$html);
            $dom->setCallback([self::class, '_fbtTraverse']);

            return $dom->save();
        } finally {
            [self::$trace, self::$functional] = $previous;
        }
    }

    /**
     * Transform <fbt> to fbt() calls.
     *
     * @throws \fbt\Exceptions\FbtException
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function _fbtTraverse(Node $node): void
    {
        $root = HTMLFbtProcessor::create($node, FbtConfig::get('extraOptions') ?? []);

        if (! $root) {
            return;
        }

        // Only the top-level <fbt> comes from the functional form
        $functional = self::$functional && $node->parent()->tag === 'root';

        $output = $root->convertToFbtRuntimeCall(
            self::$defaultOptions,
            [
                'generateOuterTokenName' => (bool)FbtConfig::get('generateOuterTokenName'),
            ],
            $functional
        );

        if (FbtConfig::get('collectFbt') && self::$collectPhrases) {
            self::collectMetaPhrases($output['metaPhrases']);
        }

        $node->outerHtml = (string)$output['result'];
    }

    /**
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     */
    private static function collectMetaPhrases(array $metaPhrases): void
    {
        $initialPhraseCount = count(self::$phrases);
        $indexMap = [];

        foreach ($metaPhrases as $index => $metaPhrase) {
            if (! empty($metaPhrase['phrase']['doNotExtract'])) {
                continue;
            }

            $indexMap[$index] = $initialPhraseCount + count($indexMap);
            self::addMetaPhrase($metaPhrase);

            if ($metaPhrase['parentIndex'] !== null && isset($indexMap[$metaPhrase['parentIndex']])) {
                self::addEnclosingString($indexMap[$index], $indexMap[$metaPhrase['parentIndex']]);
            }
        }
    }

    /**
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     */
    private static function addMetaPhrase(array $metaPhrase): void
    {
        $location = [];
        if (isset(self::$trace['file'])) {
            $location['filepath'] = self::getRelativePath(self::$trace['file']);
        }
        if (isset(self::$trace['line'])) {
            // js~php diff: columns are not available
            $location['line_beg'] = self::$trace['line'];
            $location['line_end'] = self::$trace['line'];
        }

        $textPackager = new TextPackager(FbtConfig::get('hash_module'));
        self::$phrases[] = $textPackager->pack([$location + $metaPhrase['phrase']])[0];
    }

    private static function getRelativePath(string $file): string
    {
        $normalizedFile = str_replace('\\', '/', $file);
        $cwd = getcwd();
        if ($cwd !== false) {
            $cwd = rtrim(str_replace('\\', '/', $cwd), '/') . '/';
            if (strpos($normalizedFile, $cwd) === 0) {
                return substr($normalizedFile, strlen($cwd));
            }
        }

        return preg_replace('#^\./#', '', $normalizedFile);
    }

    /**
     * Loads the file-level default options, e.g. the `@fbt {"project": "..."}` docblock
     *
     * @throws \fbt\Exceptions\FbtParserException
     * @throws \Exception
     */
    private static function initDefaultOptions(array $entrypoint): void
    {
        static $cache = [];

        $file = $entrypoint['file'] ?? null;
        $defaultOptions = [];

        if ($file !== null && isset($cache[$file])) {
            $defaultOptions = $cache[$file];
        } elseif ($file !== null && file_exists($file)) {
            $comments = array_filter(
                token_get_all(file_get_contents($file)),
                function ($entry) {
                    return $entry[0] === T_DOC_COMMENT;
                }
            );

            if ($comments) {
                $comment = array_shift($comments);
                preg_match('/@fbt ({.+?})/', $comment[1], $fbtDocblockOptions);

                if (isset($fbtDocblockOptions[1])) {
                    $defaultOptions = json_decode($fbtDocblockOptions[1], true);
                    foreach ($defaultOptions as $key => $value) {
                        FbtUtils::checkOption($key, FbtConstants::VALID_FBT_OPTIONS, $value);
                    }
                }
            }

            $cache[$file] = $defaultOptions;
        }

        // js~php diff: default values from the configuration
        if (empty($defaultOptions['project'])) {
            $defaultOptions['project'] = FbtConfig::get('project') ?? '';
        }

        if (empty($defaultOptions['author'])) {
            $defaultOptions['author'] = FbtConfig::get('author');
        }

        self::$defaultOptions = $defaultOptions;
    }

    public static function addEnclosingString(int $childIdx, int $parentIdx): void
    {
        self::$childToParent[$childIdx] = $parentIdx;
    }

    public static function toArray(): array
    {
        return [
            'phrases' => self::$phrases,
            'childParentMappings' => self::$childToParent,
        ];
    }
}
