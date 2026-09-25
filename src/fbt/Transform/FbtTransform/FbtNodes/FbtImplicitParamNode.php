<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

use dobron\DomForge\DomForge;
use dobron\DomForge\Node;

use function fbt\invariant;

use fbt\Transform\FbtTransform\FbtCallExpression;
use fbt\Transform\FbtTransform\FbtTransform;
use fbt\Transform\FbtTransform\FbtUtils;

/**
 * Represents non-fbt HTML element nested inside an fbt callsite.
 */
class FbtImplicitParamNode extends FbtNode implements IFbtElementNode
{
    public const TYPE = FbtNodeType::IMPLICIT_PARAM;

    /** @var array<string, FbtCallExpression|Node> */
    public $_tokenSet = [];

    private function _getElementNode(): FbtElementNode
    {
        $element = $this->getFirstAncestorOfType(FbtElementNode::class);
        invariant($element !== null, 'Expected an FbtElementNode ancestor');

        return $element;
    }

    /**
     * @return mixed
     */
    private function _getSubjectNode()
    {
        return $this->_getElementNode()->options['subject'];
    }

    public function getOptions(array $validExtraOptions = []): ?array
    {
        return null;
    }

    /**
     * We define an FbtImplicitParamNode's outer token alias to be
     * string concatenation of '=m' + the FbtImplicitParamNode's index in its siblings array.
     *
     * @example For string <fbt> hello <a>world</a></fbt>,
     *          the outer token alias of <a>world</a> will be '=m1'.
     */
    public function getOuterTokenAlias(): string
    {
        invariant($this->parent !== null, 'Parent node must be defined');
        $index = array_search($this, $this->parent->children, true);
        invariant(
            $index !== false,
            "Could not find current fbt node among the parent node's children"
        );

        return FbtNodeUtil::convertIndexInSiblingsArrayToOuterTokenAlias($index);
    }

    public function getArgsForStringVariationCalc(): array
    {
        return FbtElementNode::getArgsForStringVariationCalcForFbtElement(
            $this,
            // The implicit fbt string may depend on a subject, inferred from the top-level FbtElementNode
            $this->_getSubjectNode()
        );
    }

    public function getText(StringVariationArgsMap $argsMap): string
    {
        try {
            FbtElementNode::beforeGetTextSanityCheck($this, $argsMap);

            return FbtNodeUtil::getTextFromFbtNodeTree(
                $this,
                $argsMap,
                $this->_getSubjectNode(),
                $this->_getElementNode()->options['preserveWhitespace'],
                [FbtNodeUtil::class, 'getChildNodeText']
            );
        } catch (\Throwable $error) {
            throw FbtUtils::errorAt($this->node, $error);
        }
    }

    public function getTextForDescription(StringVariationArgsMap $argsMap, FbtImplicitParamNode $targetFbtNode): string
    {
        return FbtNodeUtil::getTextFromFbtNodeTree(
            $this,
            $argsMap,
            $this->_getSubjectNode(),
            $this->_getElementNode()->options['preserveWhitespace'],
            function (StringVariationArgsMap $argsMap, FbtNode $child) use ($targetFbtNode) {
                return FbtNodeUtil::getChildNodeTextForDescription($targetFbtNode, $argsMap, $child);
            }
        );
    }

    /**
     * Returns the text of this FbtNode in a "token name" format.
     * Note: it's prefixed by `=` to differentiate normal token names from implicit param nodes.
     *
     * E.g. `=Hello [name]`
     */
    public function getTokenName(StringVariationArgsMap $argsMap): ?string
    {
        return FbtNodeUtil::convertToTokenName(
            FbtNodeUtil::getTextFromFbtNodeTree(
                $this,
                $argsMap,
                $this->_getSubjectNode(),
                $this->_getElementNode()->options['preserveWhitespace'],
                function (StringVariationArgsMap $argsMap, FbtNode $child) {
                    return $child->getText($argsMap);
                }
            )
        );
    }

    /**
     * Returns the string description which depends on the string variation factor values
     * from the whole fbt callsite.
     */
    public function getDescription(StringVariationArgsMap $argsMap): string
    {
        return 'In the phrase: "' . $this->_getElementNode()->getTextForDescription($argsMap, $this) . '"';
    }

    public function getTokenAliases(StringVariationArgsMap $argsMap): ?array
    {
        return FbtNodeUtil::getTokenAliasesFromFbtNodeTree($this, $argsMap);
    }

    /**
     * Returns whether this implicit param node is an ancestor of a given `node`
     */
    public function isAncestorOf(FbtNode $node): bool
    {
        for ($parent = $node->parent; $parent !== null; $parent = $parent->parent) {
            if ($parent === $this) {
                return true;
            }
        }

        return false;
    }

    /**
     * The fbt::_() call generated from an inner string surrounded by HTML tags would
     * inherit extra options specified on its ancestor fbt callsite.
     */
    public function getExtraOptions(): ?array
    {
        return $this->_getElementNode()->getExtraOptions();
    }

    /**
     * js~php diff: renders the HTML element of this node with the given contents
     * (the equivalent of cloning the JSX element with new children)
     */
    public function wrapContents(string $contents): string
    {
        $node = $this->node;
        invariant($node !== null, 'Expected a DOM node');

        return $node->makeup() . $contents . '</' . $node->tag . '>';
    }

    /**
     * Create a new class instance given a DOM element (the equivalent of a JSX element).
     * If that node is incompatible, we'll just return `null`.
     *
     * @param mixed $node
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function fromNode(string $moduleName, $node): ?self
    {
        if (! $node instanceof Node || ! $node->isElement()) {
            return null;
        }
        $implicitParam = new self([
            'moduleName' => $moduleName,
            'node' => $node,
        ]);

        $fbtChildren = [];

        foreach ($node->nodes as $child) {
            switch ($child->nodetype) {
                case DomForge::TYPE_TEXT:
                    // js~php diff: whitespace-only texts are kept, because whitespace
                    // between HTML tags is significant (JSX drops the whitespace
                    // that doesn't neighbor raw text)
                    // (fbt constructs may be concatenated with the text, see FbtCallExpression)
                    foreach (FbtTransform::splitText($child->innerHtml()) as $part) {
                        $fbtChildren[] = is_string($part)
                            ? FbtTextNode::fromText($moduleName, $part, $child)
                            : FbtElementNode::createChildNode($moduleName, $part);
                    }

                    break;

                case DomForge::TYPE_ELEMENT:
                    $fbtChildren[] = FbtElementNode::createChildNode($moduleName, $child);

                    break;

                default:
                    // e.g. HTML comments (the equivalent of empty JSX expressions)
                    break;
            }
        }

        foreach ($fbtChildren as $child) {
            $implicitParam->appendChild($child);
        }

        return $implicitParam;
    }

    public function getFbtRuntimeArg(): ?FbtCallExpression
    {
        throw FbtUtils::errorAt($this->node, 'This method must be implemented in a child class');
    }

    public function registerToken(string $name, FbtNode $source): void
    {
        FbtUtils::setUniqueToken($source->node, $this->moduleName, $name, $this->_tokenSet);
    }

    public function toPlainFbtNode(): array
    {
        $props = [];
        foreach ($this->node->getAttributes() as $name => $value) {
            // Only handling literal attributes
            if (is_string($value)) {
                $props[$name] = $value;
            }
        }

        return [
            'type' => self::TYPE,
            'wrapperNode' => [
                'type' => $this->node->tag,
                'props' => $props,
            ],
        ];
    }

    public function __toJSONForTestsOnly(): array
    {
        return parent::__toJSONForTestsOnly() + [
            '_tokenSet' => array_map('get_class', $this->_tokenSet),
        ];
    }
}
