<?php

namespace fbt\Transform\FbtTransform\Processors;

use dobron\DomForge\Node;

use function fbt\invariant;

use fbt\Runtime\Shared\fbs;
use fbt\Runtime\Shared\fbt;
use fbt\Transform\FbtRuntime\FbtRuntimeTransform;
use fbt\Transform\FbtTransform\FbtConstants;
use fbt\Transform\FbtTransform\FbtNodes\FbtElementNode;
use fbt\Transform\FbtTransform\FbtNodes\FbtImplicitParamNode;
use fbt\Transform\FbtTransform\FbtNodes\FbtNode;
use fbt\Transform\FbtTransform\FbtNodes\FbtNodeUtil;
use fbt\Transform\FbtTransform\FbtNodes\FbtParamNode;
use fbt\Transform\FbtTransform\FbtNodes\StringVariationArg;
use fbt\Transform\FbtTransform\FbtNodes\StringVariationArgsMap;
use fbt\Transform\FbtTransform\JSFbtBuilder;
use fbt\Transform\FbtTransform\Utils\AddLeafToTree;

/**
 * This class provides utility methods to process the standard fbt function call
 * (i.e. `fbt(...)`)
 *
 * js~php diff: instead of generating the code of the `fbt::_()` runtime call,
 * the runtime call is executed right away.
 *
 * A meta-phrase is an array of the form:
 *   [
 *     'compactStringVariations' => ['array' => StringVariationArg[], 'indexMap' => int[]],
 *     'fbtNode' => FbtElementNode|FbtImplicitParamNode,
 *     'phrase' => array,
 *     'parentIndex' => int|null,
 *   ]
 */
class FbtFunctionCallProcessor
{
    /** @var array */
    private $defaultFbtOptions;
    /** @var array */
    private $validFbtExtraOptions;
    /** @var string */
    private $moduleName;
    /** @var Node|null */
    private $node;
    /**
     * [contents (list of strings or DOM nodes), description, options]
     * @var array
     */
    private $callArgs;
    /** @var array */
    private $pluginOptions;

    /**
     * @param string $moduleName
     * @param Node|null $node - the <fbt> DOM node
     * @param array $callArgs - [contents (list of strings or DOM nodes), description, options]
     * @param array $defaultFbtOptions - e.g. the file-level docblock options
     * @param array $validFbtExtraOptions
     * @param array $pluginOptions
     */
    public function __construct(
        string $moduleName,
        ?Node $node,
        array $callArgs,
        array $defaultFbtOptions = [],
        array $validFbtExtraOptions = [],
        array $pluginOptions = []
    ) {
        $this->moduleName = $moduleName;
        $this->node = $node;
        $this->callArgs = $callArgs;
        $this->defaultFbtOptions = $defaultFbtOptions;
        $this->validFbtExtraOptions = $validFbtExtraOptions;
        $this->pluginOptions = $pluginOptions;
    }

    /**
     * @throws \fbt\Exceptions\FbtParserException
     */
    private function _assertHasEnoughArguments(): self
    {
        if (count($this->callArgs) < 2) {
            throw FbtNodeUtil::errorAt(
                $this->node,
                "Expected {$this->moduleName} calls to have at least two arguments. " .
                'Only ' . count($this->callArgs) . ' was given.'
            );
        }

        return $this;
    }

    /**
     * @return mixed - result of the fbt runtime call
     * @throws \fbt\Exceptions\FbtException
     */
    private function _createFbtRuntimeCallForMetaPhrase(
        array $metaPhrases,
        int $metaPhraseIndex,
        array $stringVariationRuntimeArgs
    ) {
        $metaPhrase = $metaPhrases[$metaPhraseIndex];
        /** @var FbtElementNode|FbtImplicitParamNode $fbtNode */
        $fbtNode = $metaPhrase['fbtNode'];

        $runtimeInput = FbtRuntimeTransform::transform($metaPhrase['phrase'], $fbtNode->getExtraOptions());
        $fbtRuntimeArgs = $this->_createFbtRuntimeArgumentsForMetaPhrase(
            $metaPhrases,
            $metaPhraseIndex,
            $stringVariationRuntimeArgs
        );

        $runtime = $this->moduleName === FbtConstants::MODULE_NAME['FBS'] ? new fbs() : new fbt();

        return $runtime->_(
            $runtimeInput['table'],
            count($fbtRuntimeArgs) > 0 ? $fbtRuntimeArgs : null,
            $runtimeInput['options'],
            // js~php diff: whether the result can be inlined
            (bool)$this->_getFbtElement($metaPhrases)->options['reporting']
        );
    }

    /**
     * @return mixed - result of the fbt runtime call
     * @throws \fbt\Exceptions\FbtException
     */
    private function _createRootFbtRuntimeCall(array $metaPhrases)
    {
        $stringVariationRuntimeArgs = $this->_createRuntimeArgsFromStringVariantNodes($metaPhrases[0]);

        return $this->_createFbtRuntimeCallForMetaPhrase(
            $metaPhrases,
            0,
            $stringVariationRuntimeArgs
        );
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    private function _getFbtElement(array $metaPhrases): FbtElementNode
    {
        $fbtElement = $metaPhrases[0]['fbtNode'];
        invariant(
            $fbtElement instanceof FbtElementNode,
            'Expected a FbtElementNode for top level string but received: %s',
            get_class($fbtElement)
        );

        return $fbtElement;
    }

    /**
     * Consolidate a list of string variation arguments under the following conditions:
     *
     * Enum variation arguments are consolidated to avoid creating duplicates of string variations
     * (from a candidate values POV)
     *
     * Other types of variation arguments are accepted as-is.
     *
     * @param StringVariationArg[] $args
     *
     * @return array{array: StringVariationArg[], indexMap: int[]}
     */
    private function _compactStringVariationArgs(array $args): array
    {
        $indexMap = [];
        $array = [];
        foreach (array_values($args) as $i => $arg) {
            if ($arg->isCollapsible) {
                continue;
            }
            $indexMap[] = $i;
            $array[] = $arg;
        }

        return [
            'array' => $array,
            'indexMap' => $indexMap,
        ];
    }

    /**
     * @param FbtNode $fbtNode
     * @param FbtNode[] $list
     *
     * @throws \fbt\Exceptions\FbtException
     */
    private function _getPhraseParentIndex(FbtNode $fbtNode, array $list): ?int
    {
        if ($fbtNode->parent === null) {
            return null;
        }

        $parentIndex = array_search($fbtNode->parent, $list, true);
        invariant(
            $parentIndex !== false,
            'Unable to find parent fbt node: node=%s',
            get_class($fbtNode)
        );

        return $parentIndex;
    }

    /**
     * Generates a list of meta-phrases from a given FbtElement node
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    private function _metaPhrases(FbtElementNode $fbtElement): array
    {
        $stringVariationArgs = $fbtElement->getArgsForStringVariationCalc();
        $jsfbtBuilder = new JSFbtBuilder(
            $stringVariationArgs,
            ! empty($this->pluginOptions['reactNativeMode'])
        );
        $argsCombinations = $jsfbtBuilder->getStringVariationCombinations();
        $compactStringVariations = $this->_compactStringVariationArgs($argsCombinations[0] ?? []);
        $jsfbtMetadata = $jsfbtBuilder->buildMetadata($compactStringVariations['array']);
        $sharedPhraseOptions = $this->_getSharedPhraseOptions($fbtElement);

        $list = array_merge([$fbtElement], $fbtElement->getImplicitParamNodes());

        return array_map(function (FbtNode $fbtNode) use (
            $list,
            $argsCombinations,
            $compactStringVariations,
            $jsfbtMetadata,
            $sharedPhraseOptions
        ) {
            try {
                $phrase = $sharedPhraseOptions + [
                    'jsfbt' => [
                        // the order of JSFBT props matter for unit tests
                        't' => [],
                        'm' => $jsfbtMetadata,
                    ],
                ];
                $svArgsMapList = [];

                foreach (count($argsCombinations) ? $argsCombinations : [[]] as $argsCombination) {
                    // collect text/description pairs
                    $svArgsMap = new StringVariationArgsMap($argsCombination);
                    $argValues = array_map(function (int $originIndex) use ($argsCombination) {
                        $value = $argsCombination[$originIndex]->value ?? null;
                        invariant($value !== null, 'Expected string variation value');

                        return $value;
                    }, $compactStringVariations['indexMap']);

                    $leaf = [
                        'desc' => $fbtNode->getDescription($svArgsMap),
                        'text' => $fbtNode->getText($svArgsMap),
                    ];

                    $tokenAliases = $fbtNode->getTokenAliases($svArgsMap);
                    if ($tokenAliases !== null) {
                        $leaf['tokenAliases'] = $tokenAliases;
                    }

                    if ($fbtNode instanceof FbtElementNode) {
                        // gather list of svArgsMap for all args combination for later sanity checks
                        $svArgsMapList[] = $svArgsMap;
                    } elseif (($this->pluginOptions['generateOuterTokenName'] ?? false) === true) {
                        $leaf['outerTokenName'] = $fbtNode->getTokenName($svArgsMap);
                    }

                    if (count($argValues)) {
                        AddLeafToTree::addLeafToTree($phrase['jsfbt']['t'], $argValues, $leaf);
                    } else {
                        // jsfbt only contains one leaf
                        $phrase['jsfbt']['t'] = $leaf;
                    }
                }

                if ($fbtNode instanceof FbtElementNode) {
                    $fbtNode->assertNoOverallTokenNameCollision($svArgsMapList);
                }

                return [
                    'compactStringVariations' => $compactStringVariations,
                    'fbtNode' => $fbtNode,
                    'parentIndex' => $this->_getPhraseParentIndex($fbtNode, $list),
                    'phrase' => $phrase,
                ];
            } catch (\Throwable $error) {
                throw FbtNodeUtil::errorAt($fbtNode->node, $error);
            }
        }, $list);
    }

    /**
     * Process current `fbt()` callsite to generate:
     * - the result of the `fbt::_()` runtime call
     * - a list of meta-phrases describing the collected text strings from this fbt() callsite
     *
     * @return array{result: mixed, metaPhrases: array}
     * @throws \fbt\Exceptions\FbtParserException
     * @throws \fbt\Exceptions\FbtException
     */
    public function convertToFbtRuntimeCall(): array
    {
        $fbtElement = $this->_convertToFbtNode();
        $metaPhrases = $this->_metaPhrases($fbtElement);
        $result = $this->_createRootFbtRuntimeCall($metaPhrases);

        return [
            'result' => $result,
            'metaPhrases' => $metaPhrases,
        ];
    }

    /**
     * Converts current fbt() call to an FbtNode equivalent
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    private function _convertToFbtNode(): FbtElementNode
    {
        $this->_assertHasEnoughArguments();

        return FbtElementNode::fromNode(
            $this->moduleName,
            $this->node,
            $this->callArgs,
            $this->validFbtExtraOptions
        );
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    private function _createFbtRuntimeArgumentsForMetaPhrase(
        array $metaPhrases,
        int $metaPhraseIndex,
        array $stringVariationRuntimeArgs
    ): array {
        $metaPhrase = $metaPhrases[$metaPhraseIndex];

        // Runtime arguments of a string fall into 3 categories:
        // 1. Each string variation argument must correspond to a runtime argument
        // 2. Non string variation arguments(i.e. those fbt::param() calls that do not
        // have gender or number option) should also be counted as runtime arguments.
        // 3. Each inner string of current string should be associated with a
        // runtime argument
        return array_merge(
            $stringVariationRuntimeArgs,
            $this->_createRuntimeArgsFromNonStringVariantNodes($metaPhrase['fbtNode']),
            $this->_createRuntimeArgsFromImplicitParamNodes(
                $metaPhrases,
                $metaPhraseIndex,
                $stringVariationRuntimeArgs
            )
        );
    }

    private function _createRuntimeArgsFromStringVariantNodes(array $metaPhrase): array
    {
        $fbtRuntimeArgs = [];
        foreach ($metaPhrase['compactStringVariations']['array'] as $stringVariation) {
            $fbtRuntimeArg = $stringVariation->fbtNode->getFbtRuntimeArg();
            if ($fbtRuntimeArg) {
                $fbtRuntimeArgs[] = $fbtRuntimeArg;
            }
        }

        return $fbtRuntimeArgs;
    }

    /**
     * @param FbtImplicitParamNode|FbtElementNode $fbtNode
     */
    private function _createRuntimeArgsFromNonStringVariantNodes(FbtNode $fbtNode): array
    {
        $fbtRuntimeArgs = [];
        foreach ($fbtNode->children as $child) {
            if (
                $child instanceof FbtParamNode &&
                $child->options['gender'] === null &&
                $child->options['number'] === null
            ) {
                $fbtRuntimeArgs[] = $child->getFbtRuntimeArg();
            }
        }

        return $fbtRuntimeArgs;
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    private function _createRuntimeArgsFromImplicitParamNodes(
        array $metaPhrases,
        int $metaPhraseIndex,
        array $runtimeArgsFromStringVariationNodes
    ): array {
        $fbtRuntimeArgs = [];
        foreach ($metaPhrases as $innerMetaPhraseIndex => $innerMetaPhrase) {
            if ($innerMetaPhrase['parentIndex'] !== $metaPhraseIndex) {
                continue;
            }
            $innerMetaPhraseFbtNode = $innerMetaPhrase['fbtNode'];
            invariant(
                $innerMetaPhraseFbtNode instanceof FbtImplicitParamNode,
                'Expected the inner meta phrase to be associated with a FbtImplicitParamNode instead of %s',
                get_class($innerMetaPhraseFbtNode)
            );

            $innerResult = $this->_createFbtRuntimeCallForMetaPhrase(
                $metaPhrases,
                $innerMetaPhraseIndex,
                $runtimeArgsFromStringVariationNodes
            );

            $fbtRuntimeArgs[] = $innerMetaPhraseFbtNode->createFbtRuntimeArgCallExpression([
                $innerMetaPhraseFbtNode->getOuterTokenAlias(),
                // js~php diff: the equivalent of cloning the JSX element with the inner result as children
                $innerMetaPhraseFbtNode->wrapContents((string)$innerResult),
            ]);
        }

        return $fbtRuntimeArgs;
    }

    /**
     * Combine options of the fbt element level with default options
     * @return array only options that are considered "defined".
     * I.e. Options whose value is `false` or nullish will be skipped.
     */
    private function _getSharedPhraseOptions(FbtElementNode $fbtElement): array
    {
        $fbtElementOptions = $fbtElement->options;
        $defaultFbtOptions = $this->defaultFbtOptions;

        $ret = [
            'author' => ($fbtElementOptions['author'] ?? $defaultFbtOptions['author'] ?? null) ?: null,
            'common' => ($fbtElementOptions['common'] ?? $defaultFbtOptions['common'] ?? null) ?: null,
            'doNotExtract' => ($fbtElementOptions['doNotExtract'] ?? $defaultFbtOptions['doNotExtract'] ?? null) ?: null,
            'preserveWhitespace' => ($fbtElementOptions['preserveWhitespace'] ?? $defaultFbtOptions['preserveWhitespace'] ?? null) ?: null,
            // js~php diff: the subject is a runtime value, so it's not a part of the phrase
            'project' => $fbtElementOptions['project'] ?: (string)($defaultFbtOptions['project'] ?? ''),
        ];

        // delete nullish options
        return array_filter($ret, function ($value) {
            return $value !== null;
        });
    }
}
