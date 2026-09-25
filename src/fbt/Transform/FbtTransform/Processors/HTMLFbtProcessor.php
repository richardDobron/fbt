<?php

namespace fbt\Transform\FbtTransform\Processors;

use dobron\DomForge\DomForge;
use dobron\DomForge\Node;
use fbt\Exceptions\FbtParserException;
use fbt\Transform\FbtTransform\FbtCallExpression;
use fbt\Transform\FbtTransform\FbtCommon;
use fbt\Transform\FbtTransform\FbtConstants;
use fbt\Transform\FbtTransform\FbtNodeChecker;
use fbt\Transform\FbtTransform\FbtUtils;

/**
 * Port of JSXFbtProcessor: converts an <fbt> DOM node to an fbt() "function call".
 */
class HTMLFbtProcessor
{
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
                        'Expected a StringLiteral but found `' . ($stringNode instanceof Node ? $stringNode->tag : $stringNode->moduleName . '::' . $stringNode->name . '()') . '` instead'
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
     * @return array<int, string|Node|FbtCallExpression>
     * @throws FbtParserException
     */
    private function _transformChildrenForFbtCallSyntax(): array
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
                    // (fbt constructs may be concatenated with the text, see FbtCallExpression)
                    array_push($children, ...FbtCallExpression::split(FbtUtils::normalizeSpaces($text)));

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

        try {
            $descAttr = FbtUtils::getAttributeByNameOrThrow($this->node, 'desc');
        } catch (FbtParserException $error) {
            throw FbtUtils::errorAt($this->node, $error->getMessage());
        }
        $node = $this->node;
        if ($node->getAttribute('desc') === true) {
            throw FbtUtils::errorAt($node, "<$moduleName> requires a \"desc\" attribute");
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
     * @param array $defaultFbtOptions
     * @param array $pluginOptions
     *
     * @return array{result: mixed, metaPhrases: array}
     * @throws FbtParserException
     * @throws \fbt\Exceptions\FbtException
     */
    public function convertToFbtRuntimeCall(
        array $defaultFbtOptions = [],
        array $pluginOptions = []
    ): array {
        $processor = $this->createFunctionCallProcessor($defaultFbtOptions, $pluginOptions);
        $processor->throwIfExistsNestedFbtConstruct();

        return $processor->convertToFbtRuntimeCall();
    }

    /**
     * Converts the <fbt> DOM node to an fbt() call, i.e. `fbt(children, description, options)`
     *
     * @throws FbtParserException
     * @throws \fbt\Exceptions\FbtException
     */
    public function createFunctionCallProcessor(
        array $defaultFbtOptions = [],
        array $pluginOptions = []
    ): FbtFunctionCallProcessor {
        $this->_assertNoNestedFbts();

        $children = $this->_transformChildrenForFbtCallSyntax();
        $description = $this->_getDescription($children);

        $callArgs = [$children, $description];
        $options = $this->_getOptions();
        if ($options !== null) {
            $callArgs[] = $options;
        }

        return new FbtFunctionCallProcessor(
            new FbtCallExpression($this->moduleName, null, $callArgs, $this->node),
            $defaultFbtOptions,
            $this->validFbtExtraOptions,
            $pluginOptions
        );
    }
}
