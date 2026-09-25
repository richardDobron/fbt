<?php

namespace fbt\Transform\FbtTransform;

use dobron\DomForge\Node;
use fbt\fbt;
use fbt\FbtConfig;
use fbt\Runtime\FbtTranslations;
use fbt\Runtime\Shared\FbtHooks;
use fbt\Transform\FbtTransform\FbtNodes\FbtElementNode;
use fbt\Transform\FbtTransform\FbtNodes\FbtNodeUtil;
use fbt\Transform\FbtTransform\Processors\FbtCommonFunctionCallProcessor;
use fbt\Transform\FbtTransform\Processors\FbtFunctionCallProcessor;
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
     * @var bool
     */
    public static $collectPhrases = true;
    /**
     * @var bool
     */
    public static $collectFbtElementNodes = false;
    /**
     * @var array
     */
    public static $fbtElementNodes = [];

    private const MAX_COMPILED = 1000;

    /**
     * @var array<string, array{parts: array<int, string|int>, items: array}>
     */
    private static $compiled = [];
    /**
     * @var array<int, array{processor: FbtFunctionCallProcessor, metaPhrases: array}|null>
     */
    private static $items = [];
    /** @var bool */
    private static $functionalRoot = false;
    /**
     * fbt constructs of the fbt() callsite being compiled (see FbtCallExpression::marker())
     * @var FbtCallExpression[]
     */
    private static $callExpressions = [];

    /**
     * Transforms the <fbt> callsites of an HTML document.
     *
     * @param fbt|string $html
     * @param array $trace - location of the document (file and line)
     *
     * @return string
     * @throws \Throwable
     * @throws \fbt\Exceptions\FbtException
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function transform($html, array $trace = []): string
    {
        if ($html instanceof fbt) {
            return (string)$html;
        }

        self::init($trace);

        $template = self::compile((string)$html, $trace);

        return self::render($template, []);
    }

    /**
     * Transforms an fbt() callsite, i.e. `fbt(contents, description, options)`.
     *
     * @param FbtCallExpression $callExpression - whose expressions refer to `$values`
     * @param array $values - runtime values of the callsite
     * @param array $trace - location of the fbt() call (file and line)
     *
     * @return mixed|string
     * @throws \Throwable
     */
    public static function transformCallExpression(FbtCallExpression $callExpression, array $values, array $trace = [])
    {
        self::init($trace);

        $template = self::compileCallExpression($callExpression, $trace);

        return self::render($template, $values);
    }

    /**
     * @throws \Throwable
     */
    private static function init(array $trace): void
    {
        self::initDefaultOptions($trace);
        FbtCommon::init([
            'fbtCommon' => FbtConfig::get('fbtCommon'),
            'fbtCommonPath' => FbtConfig::get('fbtCommonPath'),
        ]);

        if (! self::$init) {
            $translations = FbtConfig::get('path') . '/translatedFbts.json';
            if (file_exists($translations)) {
                FbtTranslations::mergeTranslations(json_decode(FbtHooks::readLocked($translations), true) ?: []);
            }

            FbtHooks::onTerminating();

            self::$init = true;
        }
    }

    private static function getCompiledKey(array $source): string
    {
        return md5(serialize(array_merge($source, [
            self::$defaultOptions,
            FbtConfig::get('extraOptions') ?? [],
            (bool)FbtConfig::get('generateOuterTokenName'),
        ])));
    }

    /**
     * @param callable(): array{parts: array<int, string|int>, items: array} $compile
     *
     * @return array{parts: array<int, string|int>, items: array}
     * @throws \Throwable
     */
    private static function compileOnce(string $key, array $trace, callable $compile): array
    {
        if (isset(self::$compiled[$key])) {
            return self::$compiled[$key];
        }

        $previous = [self::$trace, self::$items, self::$callExpressions];
        self::$trace = $trace;
        self::$items = [];
        self::$callExpressions = [];

        try {
            $template = $compile();

            if (FbtConfig::get('collectFbt') && self::$collectPhrases) {
                foreach ($template['items'] as $item) {
                    self::collectMetaPhrases($item['metaPhrases']);
                }
            }
        } finally {
            [self::$trace, self::$items, self::$callExpressions] = $previous;
        }

        if (count(self::$compiled) >= self::MAX_COMPILED) {
            array_shift(self::$compiled);
        }

        return self::$compiled[$key] = $template;
    }

    /**
     * Compiles a document once: fbt callsites are converted to phrases and runtime
     * calls (js~php diff: upstream compiles the source code once, at build time).
     *
     * @return array{parts: array<int, string|int>, items: array}
     * @throws \Throwable
     */
    private static function compile(string $html, array $trace): array
    {
        return self::compileOnce(self::getCompiledKey([$html]), $trace, function () use ($html) {
            $dom = NodeParser::parse($html);
            if ($dom === false) {
                return ['parts' => [$html], 'items' => []];
            }

            $dom->setCallback([self::class, '_fbtTraverse']);
            $parts = preg_split(FbtRuntimeScope::ITEM_PATTERN, $dom->save(), -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($parts as $index => $part) {
                // Odd parts are indexes of the compiled callsites
                if ($index % 2 === 1) {
                    $parts[$index] = (int)$part;
                }
            }

            return ['parts' => $parts, 'items' => self::$items];
        });
    }

    /**
     * Compiles an fbt() callsite once for all of its values
     *
     * @return array{parts: array<int, string|int>, items: array}
     * @throws \Throwable
     */
    private static function compileCallExpression(FbtCallExpression $callExpression, array $trace): array
    {
        $key = self::getCompiledKey([FbtCallExpression::shape($callExpression)]);

        return self::compileOnce($key, $trace, function () use ($callExpression) {
            $previousFunctional = self::$functionalRoot;
            self::$functionalRoot = true;

            try {
                $commonProcessor = FbtCommonFunctionCallProcessor::create($callExpression);
                if ($commonProcessor !== null) {
                    $callExpression = $commonProcessor->convertToNormalCall();
                }

                $args = $callExpression->arguments;
                $args[0] = self::parseContents($args[0]);
                if ($args[1] === null) {
                    // i.e. fbt(text) without the description
                    array_splice($args, 1);
                }

                $processor = new FbtFunctionCallProcessor(
                    new FbtCallExpression($callExpression->moduleName, null, $args),
                    self::$defaultOptions,
                    FbtConfig::get('extraOptions') ?? [],
                    [
                        'generateOuterTokenName' => (bool)FbtConfig::get('generateOuterTokenName'),
                    ]
                );
                $processor->throwIfExistsNestedFbtConstruct();

                return [
                    'parts' => [0],
                    'items' => [
                        [
                            'processor' => $processor,
                            'metaPhrases' => $processor->compile(),
                        ],
                    ],
                ];
            } finally {
                self::$functionalRoot = $previousFunctional;
            }
        });
    }

    /**
     * Converts the contents of an fbt() callsite to the list of its children, i.e. texts,
     * fbt constructs, and HTML elements (js~php diff: the equivalent of JSX elements).
     *
     * @param array<int, string|FbtCallExpression> $contents
     *
     * @return array<int, string|FbtCallExpression|Node>
     * @throws \fbt\Exceptions\FbtParserException
     */
    private static function parseContents(array $contents): array
    {
        $html = '';
        foreach ($contents as $part) {
            if ($part instanceof FbtCallExpression) {
                $html .= FbtCallExpression::marker(count(self::$callExpressions));
                self::$callExpressions[] = $part;
            } else {
                $html .= $part;
            }
        }

        $dom = preg_match('/<[a-zA-Z!\/]/', $html) ? NodeParser::parse($html) : false;
        if ($dom === false) {
            return self::splitText($html);
        }

        $children = [];
        foreach ($dom->root->nodes as $node) {
            if ($node->isText()) {
                array_push($children, ...self::splitText($node->innerHtml()));
            } elseif ($node->isElement()) {
                $children[] = $node;
            }
        }

        return $children;
    }

    /**
     * Splits a text into texts and the fbt constructs concatenated with it
     *
     * @return array<int, string|FbtCallExpression>
     * @internal
     */
    public static function splitText(string $text): array
    {
        return FbtCallExpression::split($text, self::$callExpressions);
    }

    /**
     * @throws \Throwable
     */
    private static function render(array $template, array $values): string
    {
        $scope = null;
        $renderItem = function (int $index) use ($template, &$scope): string {
            $item = $template['items'][$index];

            return (string)$item['processor']->render($item['metaPhrases'], $scope);
        };
        $scope = new FbtRuntimeScope($values, $renderItem);

        $output = '';
        foreach ($template['parts'] as $part) {
            $output .= is_int($part) ? $renderItem($part) : $part;
        }

        return $output;
    }

    /**
     * @internal
     */
    public static function clearCache(): void
    {
        self::$compiled = [];
    }

    /**
     * @internal
     */
    public static function getCallsiteLine(): ?int
    {
        return self::$functionalRoot && isset(self::$trace['line']) ? (int)self::$trace['line'] : null;
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function _fbtTraverse(Node $node): void
    {
        $root = HTMLFbtProcessor::create($node, FbtConfig::get('extraOptions') ?? []);

        if (! $root) {
            self::assertConstructInsideFbt($node);

            return;
        }

        // The index is reserved before the children are processed, so that an fbt
        // within an fbt:param gets a higher index than its enclosing fbt
        $index = count(self::$items);
        self::$items[$index] = null;

        $previousFunctional = self::$functionalRoot;
        self::$functionalRoot = false;

        try {
            $processor = $root->createFunctionCallProcessor(
                self::$defaultOptions,
                [
                    'generateOuterTokenName' => (bool)FbtConfig::get('generateOuterTokenName'),
                ]
            );
            $processor->throwIfExistsNestedFbtConstruct();

            self::$items[$index] = [
                'processor' => $processor,
                'metaPhrases' => $processor->compile(),
            ];
        } finally {
            self::$functionalRoot = $previousFunctional;
        }

        $node->outerHtml = FbtRuntimeScope::itemMarker($index);
    }

    /**
     * @throws \fbt\Exceptions\FbtParserException
     */
    private static function assertConstructInsideFbt(Node $node): void
    {
        if (! $node->isElement() || strpos((string)$node->tag, ':') === false) {
            return;
        }

        [$namespace] = explode(':', $node->tag);
        if (! FbtNodeChecker::isFbtName($namespace) && ! FbtNodeChecker::isFbsName($namespace)) {
            return;
        }

        for ($parent = $node->parent(); $parent !== null && $parent->tag !== 'root'; $parent = $parent->parent()) {
            if (FbtNodeChecker::forFbt($parent) !== null) {
                return;
            }
        }

        throw FbtUtils::errorAt(
            $node,
            'Fbt constructs can only be used within the scope of an fbt string. ' .
            'I.e. It should be used directly inside an ‹fbt› or ‹fbs› callsite'
        );
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

        if (self::$collectFbtElementNodes) {
            $phraseToIndexMap = new \SplObjectStorage();
            foreach ($indexMap as $index => $phraseIndex) {
                $phraseToIndexMap[$metaPhrases[$index]['fbtNode']] = $phraseIndex;
            }

            // Only the extracted phrases (like upstream `allMetaPhrases`)
            foreach (array_keys($indexMap) as $index) {
                if ($metaPhrases[$index]['fbtNode'] instanceof FbtElementNode) {
                    self::$fbtElementNodes[] = FbtNodeUtil::toPlainFbtNodeTree($metaPhrases[$index]['fbtNode'], $phraseToIndexMap);
                }
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
            $location['line_end'] = self::$trace['line_end'] ?? self::$trace['line'];
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
                    return $entry[0] === T_DOC_COMMENT || $entry[0] === T_COMMENT;
                }
            );

            if ($comments) {
                $comment = array_shift($comments);
                preg_match('/@fbt\s+(\{.*\})/', $comment[1], $fbtDocblockOptions);

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
