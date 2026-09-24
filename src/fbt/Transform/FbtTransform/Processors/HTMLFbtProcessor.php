<?php

namespace fbt\Transform\FbtTransform\Processors;

use dobron\DomForge\DomForge;
use dobron\DomForge\Node;
use fbt\Exceptions\FbtParserException;

use function fbt\invariant;

use fbt\Transform\FbtTransform\FbtCommon;
use fbt\Transform\FbtTransform\FbtConstants;
use fbt\Transform\FbtTransform\FbtNodeChecker;
use fbt\Transform\FbtTransform\FbtUtils;

/**
 * Port of JSXFbtProcessor: converts an <fbt> DOM node to an fbt() "function call".
 */
class HTMLFbtProcessor
{
    private const CONSTRUCTS = ['enum', 'param', 'plural', 'pronoun', 'name', 'sameParam'];

    /** @var string */
    private $moduleName;
    /** @var Node */
    private $node;
    /** @var FbtNodeChecker */
    private $nodeChecker;
    /** @var array */
    private $validFbtExtraOptions;

    public function __construct(FbtNodeChecker $nodeChecker, Node $node, array $validFbtExtraOptions = [])
    {
        $this->moduleName = $nodeChecker->moduleName;
        $this->node = $node;
        $this->nodeChecker = $nodeChecker;
        $this->validFbtExtraOptions = $validFbtExtraOptions;
    }

    public static function create(Node $node, array $validFbtExtraOptions = []): ?HTMLFbtProcessor
    {
        $nodeChecker = FbtNodeChecker::forFbt($node);

        return $nodeChecker !== null
            ? new HTMLFbtProcessor($nodeChecker, $node, $validFbtExtraOptions)
            : null;
    }

    /**
     * @return string|null the description of the <fbt>
     * @throws FbtParserException
     */
    private function _getDescription(array $texts): string
    {
        $moduleName = $this->moduleName;
        $node = $this->node;
        $commonAttributeValue = $this->_getCommonAttributeValue();

        if ($commonAttributeValue) {
            $rawTextValue = implode('', array_map(function ($stringNode) use ($node) {
                if (! is_string($stringNode)) {
                    throw FbtUtils::errorAt(
                        $node,
                        'Expected a StringLiteral but found `' . $stringNode->tag . '` instead'
                    );
                }

                return $stringNode;
            }, $texts));

            $textValue = FbtUtils::jsTrim(FbtUtils::normalizeSpaces($rawTextValue));
            $descValue = FbtCommon::getDesc($textValue);
            if ($descValue === null || $descValue === '') {
                throw FbtUtils::errorAt(
                    $node,
                    FbtCommon::getUnknownCommonStringErrorMessage($moduleName, $textValue)
                );
            }
            if (FbtUtils::getAttributeByName($node, 'desc') !== null) {
                throw FbtUtils::errorAt($node, "<$moduleName common=\"true\"> must not have \"desc\" attribute");
            }

            return $descValue;
        }

        return $this->_getDescAttributeValue();
    }

    /**
     * @throws FbtParserException
     */
    private function _getOptions(): ?array
    {
        // Optional attributes to be passed as options.
        $this->_assertHasMandatoryAttributes();
        $options = count($this->node->getAttributes()) > 0
            ? FbtUtils::getOptionsFromAttributes(
                $this->node,
                array_merge($this->validFbtExtraOptions, FbtConstants::VALID_FBT_OPTIONS),
                FbtConstants::FBT_REQUIRED_ATTRIBUTES
            )
            : [];

        return count($options) > 0 ? $options : null;
    }

    /**
     * @throws FbtParserException
     */
    private function _assertHasMandatoryAttributes(): void
    {
        foreach (FbtConstants::FBT_CALL_MUST_HAVE_AT_LEAST_ONE_OF_THESE_ATTRIBUTES as $attribute) {
            if ($this->node->hasAttribute($attribute)) {
                return;
            }
        }

        throw FbtUtils::errorAt(
            $this->node,
            "<$this->moduleName> must have at least one of these attributes: " .
            implode(', ', FbtConstants::FBT_CALL_MUST_HAVE_AT_LEAST_ONE_OF_THESE_ATTRIBUTES)
        );
    }

    /**
     * @throws FbtParserException
     */
    private function _assertNoNestedFbts(): void
    {
        $this->nodeChecker->assertNoNestedFbts($this->node);
    }

    /**
     * fbt constructs are not allowed to be direct children of fbt constructs.
     * For example it is not okay to have
     *    <fbt desc='desc'>
     *      <fbt:param name="outer">
     *        <fbt:param name="inner">
     *          variable
     *        </fbt:param>
     *      </fbt:param>
     *    </fbt>
     * However, the next example is okay because the inner `fbt:param` sits inside
     * an inner fbt.
     *    <fbt desc='outer string'>
     *      <fbt:param name="outer">
     *        <fbt desc='inner string'>
     *          <fbt:param name="inner">
     *            variable
     *          </fbt:param>
     *        </fbt>
     *      </fbt:param>
     *    </fbt>
     *
     * @throws FbtParserException
     */
    private function throwIfExistsNestedFbtConstruct(Node $node, ?string $parentConstructName = null): void
    {
        foreach ($node->children as $child) {
            if (FbtNodeChecker::forFbt($child) !== null) {
                continue;
            }

            $constructName = FbtUtils::validateNamespacedFbtElement($this->moduleName, $child);
            $isConstruct = in_array($constructName, self::CONSTRUCTS, true);

            if ($isConstruct && $parentConstructName !== null) {
                throw FbtUtils::errorAt(
                    $node,
                    'Expected fbt constructs to not nest inside fbt constructs, but found ' .
                    "{$this->moduleName}.$constructName nest inside {$this->moduleName}.$parentConstructName"
                );
            }

            $this->throwIfExistsNestedFbtConstruct($child, $isConstruct ? $constructName : $parentConstructName);
        }
    }

    /**
     * @param bool $functional - js~php diff: whether the <fbt> comes from the
     *   functional form, i.e. fbt('text', 'desc'), which keeps its whitespace-only strings
     *
     * @return array<int, string|Node>
     * @throws FbtParserException
     */
    private function _transformChildrenForFbtCallSyntax(bool $functional): array
    {
        $children = [];
        foreach ($this->node->nodes as $node) {
            switch ($node->nodetype) {
                case DomForge::TYPE_ELEMENT:
                    // Simple HTML elements and fbt constructs are converted later on
                    $children[] = $node;

                    break;
                case DomForge::TYPE_TEXT:
                    $text = $node->innerHtml();
                    // js~php diff: whitespace-only texts are kept, because whitespace
                    // between HTML tags is significant (JSX drops it, see FbtUtil.filterEmptyNodes)
                    $children[] = $functional ? $text : FbtUtils::normalizeSpaces($text);

                    break;
                case DomForge::TYPE_COMMENT:
                    break;
                default:
                    throw FbtUtils::errorAt($node, "Unsupported HTML element child type '{$node->nodetype}'");
            }
        }

        return $children;
    }

    /**
     * @throws FbtParserException
     */
    private function _getDescAttributeValue(): string
    {
        $moduleName = $this->moduleName;
        $descAttr = FbtUtils::getAttributeByName($this->node, 'desc');

        if ($descAttr === null) {
            throw FbtUtils::errorAt($this->node, "<$moduleName> requires a \"desc\" attribute");
        }

        return $descAttr;
    }

    /**
     * @throws FbtParserException
     */
    private function _getCommonAttributeValue(): ?bool
    {
        if (! $this->node->hasAttribute(FbtConstants::COMMON_OPTION)) {
            return null;
        }

        $commonAttr = $this->node->getAttribute(FbtConstants::COMMON_OPTION);

        // An HTML tag attribute without value is default to boolean value true.
        // E.g. `<fbt common>Done</fbt>`
        if ($commonAttr === null || $commonAttr === true || $commonAttr === 'true') {
            return true;
        }

        if ($commonAttr === 'false') {
            return false;
        }

        throw new FbtParserException("`common` attribute for <$this->moduleName> requires boolean literal");
    }

    /**
     * Converts the <fbt> node to an fbt() call, and processes it.
     *
     * @param array $defaultFbtOptions
     * @param array $pluginOptions
     * @param bool $functional
     *
     * @return array{result: mixed, metaPhrases: array}
     * @throws FbtParserException
     * @throws \fbt\Exceptions\FbtException
     */
    public function convertToFbtRuntimeCall(
        array $defaultFbtOptions = [],
        array $pluginOptions = [],
        bool $functional = false
    ): array {
        $this->_assertNoNestedFbts();
        $this->throwIfExistsNestedFbtConstruct($this->node);

        $children = $this->_transformChildrenForFbtCallSyntax($functional);
        $description = $this->_getDescription($children);
        invariant($children !== [], 'text cannot be null');

        $callArgs = [$children, $description];
        $options = $this->_getOptions();
        if ($options !== null) {
            $callArgs[] = $options;
        }

        return (new FbtFunctionCallProcessor(
            $this->moduleName,
            $this->node,
            $callArgs,
            $defaultFbtOptions,
            $this->validFbtExtraOptions,
            $pluginOptions
        ))->convertToFbtRuntimeCall();
    }
}
