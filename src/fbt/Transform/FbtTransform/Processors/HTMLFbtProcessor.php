<?php

namespace fbt\Transform\FbtTransform\Processors;

use fbt\Exceptions\FbtParserException;

use function fbt\fbt;
use function fbt\invariant;

use fbt\Runtime\fbtElement;
use fbt\Runtime\fbtNamespace;
use fbt\Transform\FbtTransform\FbtAutoWrap;
use fbt\Transform\FbtTransform\FbtCommon;
use fbt\Transform\FbtTransform\FbtConstants;
use fbt\Transform\FbtTransform\FbtNodeChecker;
use fbt\Transform\FbtTransform\FbtUtils;
use fbt\Transform\FbtTransform\Utils\GetNamespacedArgs;
use fbt\Util\SimpleHtmlDom\Node;

class HTMLFbtProcessor
{
    /* @var string */
    private $moduleName;
    /* @var Node */
    private $node;
    /* @var FbtNodeChecker */
    private $nodeChecker;

    public function __construct(FbtNodeChecker $nodeChecker, Node $node)
    {
        $this->moduleName = $nodeChecker->moduleName;
        $this->node = $node;
        $this->nodeChecker = $nodeChecker;
    }

    /**
     * @param Node $node
     * @return HTMLFbtProcessor|null
     */
    public static function create(Node $node): ?HTMLFbtProcessor
    {
        $nodeChecker = FbtNodeChecker::forFbt($node);

        return $nodeChecker !== null ?
            new HTMLFbtProcessor($nodeChecker, $node) : null;
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    private function _getText($childNodes)
    {
        return count($childNodes) > 1
            ? $this->_createConcatFromExpressions($childNodes)
            : $childNodes[0];
    }

    /**
     * @throws \fbt\Exceptions\FbtParserException
     */
    private function _getDescription($text): string
    {
        $moduleName = $this->moduleName;
        $node = $this->node;

        $commonAttributeValue = $this->_getCommonAttributeValue();

        if ($commonAttributeValue) {
            $textValue = FbtUtils::normalizeSpaces(trim($text));
            $descValue = FbtCommon::getDesc($textValue);

            if (empty($descValue)) {
                throw FbtUtils::errorAt($node, FbtCommon::getUnknownCommonStringErrorMessage($moduleName, $textValue));
            }

            if (FbtUtils::getAttributeByName($this->node, 'desc')) {
                throw FbtUtils::errorAt($node, '<' . $moduleName . ' common="true"> must not have "desc" attribute');
            }

            $desc = $descValue;
        } else {
            $desc = $this->_getDescAttributeValue();
        }

        return $desc;
    }

    /**
     * @return null|array
     * @throws \fbt\Exceptions\FbtParserException
     */
    private function _getOptions(): ?array
    {
        // Optional attributes to be passed as options.

        $this->_assertHasMandatoryAttributes();

        return $this->node->getAllAttributes() // js~php diff
            ? FbtUtils::getOptionsFromAttributes($this->node, FbtConstants::VALID_FBT_OPTIONS, FbtConstants::FBT_REQUIRED_ATTRIBUTES)
            : null;
    }

    /**
     * @throws \fbt\Exceptions\FbtParserException
     */
    private function _assertHasMandatoryAttributes(): void
    {
        if (! count(array_intersect(array_keys($this->node->getAllAttributes()), FbtConstants::FBT_CALL_MUST_HAVE_AT_LEAST_ONE_OF_THESE_ATTRIBUTES))) {
            throw FbtUtils::errorAt($this->node, "<$this->moduleName> must have at least one of these attributes: " . implode(', ', FbtConstants::FBT_CALL_MUST_HAVE_AT_LEAST_ONE_OF_THESE_ATTRIBUTES));
        }
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     * @throws \fbt\Exceptions\FbtParserException
     */
    private function _createFbtFunctionCallNode($text, $desc, $options): fbtNamespace
    {
        invariant($text, 'text cannot be null');
        invariant($desc, 'desc cannot be null');

        $args = [$text, $desc];

        if ($options !== null) {
            $args[] = $options;
        }

        return fbt(...$args);
    }

    /**
     * @return void
     * @throws FbtParserException
     */
    private function _assertNoNestedFbts(): void
    {
        $this->nodeChecker->assertNoNestedFbts($this->node);
    }

    private function _isImplicitFbt(): bool
    {
        return $this->node->getAttribute('implicitFbt') === 'true';
    }

    /**
     * @throws \fbt\Exceptions\FbtParserException
     */
    private function _addImplicitDescriptionsToChildrenRecursively(): void
    {
        FbtAutoWrap::createImplicitDescriptions($this->moduleName, $this->node);
    }

    /**
     * Given a node, and its index location in phrases, any children of the given
     * node that are implicit are given their parent's location. This can then
     * be used to link the inner strings with their enclosing string.
     */
    private function _setPhraseIndexOnImplicitChildren($phraseIndex): self
    {
        $children = $this->node->children();

        if (! $children) {
            return $this;
        }

        foreach ($children as $child) {
            if ($child->implicitDesc !== null && $child->implicitDesc !== '') {
                $child->parentIndex = $phraseIndex;
                $child->setAttribute('parentIndex', $phraseIndex);
            }
        }

        return $this;
    }

    /**
     * @throws \fbt\Exceptions\FbtParserException
     */
    private function _transformChildrenToFbtCalls(array $nodes): array
    {
        return array_map(function ($node) {
            return $this->_transformNamespacedFbtElement($node);
        }, FbtUtils::filterEmptyNodes($nodes));
    }

    /**
     * Transform a namespaced fbt JSXElement into a
     * method call. E.g. `<fbt:param>` or <FbtParam> to `fbt::param()`
     * @throws \fbt\Exceptions\FbtParserException
     */
    private function _transformNamespacedFbtElement(Node $node)
    {
        switch ($node->nodetype) {
            case HDOM_TYPE_ELEMENT:
                return $this->_toFbtNamespacedCall($node);
            case HDOM_TYPE_TEXT:
                return FbtUtils::normalizeSpaces($node->innertext);
            default:
                throw FbtUtils::errorAt($node, "Unknown namespace fbt type $node->nodetype ($node->tag)");
        }
    }

    /**
     * @throws \fbt\Exceptions\FbtParserException
     */
    // WARNING: this method has side-effects because it alters the given `node` object
    // You shouldn't try to run this multiple times on the same `node`.
    private function _toFbtNamespacedCall(Node $node)
    {
        $moduleName = $this->moduleName;
        $name = FbtUtils::validateNamespacedFbtElement($moduleName, $node);
        $getNamespacedArgs = new GetNamespacedArgs($moduleName);
        $args = [
            $node,
            $getNamespacedArgs->{$name}($node),
        ];

        if ($name === 'implicitParamMarker') {
            $name = 'param';

            // js~php diff:
            $content = (string)new fbtElement($node->tag, fbt($this->_transformChildrenToFbtCalls($node->nodes), $node->getAttribute('desc'), [
                'implicitFbt' => true,
                'subject' => $this->node->getAttribute('subject') ?: null,
                'project' => $this->node->getAttribute('project'),
                'author' => $this->node->getAttribute('author'),
            ]), $node->getAllAttributes());
            $args[1][1] = $content;
            $args[2] = $content;
        }

        return call_user_func_array([fbtNamespace::class, $name], $args);
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    private function _createConcatFromExpressions(array $nodes): array
    {
        invariant($nodes, 'Cannot create an expression without nodes.');

        // js~php diff
        return $nodes;
    }

    /**
     * @throws \fbt\Exceptions\FbtParserException
     */
    private function _getDescAttributeValue(): string
    {
        $node = $this->node;
        $descAttr = FbtUtils::getAttributeByNameOrThrow($node, 'desc');

        if (! $descAttr) {
            throw FbtUtils::errorAt($node, "<$this->moduleName> requires a \"desc\" attribute");
        }


        return $descAttr;
    }

    /**
     * @return null|bool
     * @throws \fbt\Exceptions\FbtParserException
     */
    private function _getCommonAttributeValue(): ?bool
    {
        $commonAttr = FbtUtils::getAttributeByName($this->node, 'common');

        if (! $commonAttr) {
            return null;
        }

        if ($commonAttr === 'true' || $commonAttr === 'false') {
            return $commonAttr === 'true';
        }

        throw new FbtParserException("`common` attribute for <$this->moduleName> requires boolean literal");
    }

    /**
     * @throws \fbt\Exceptions\FbtParserException|\fbt\Exceptions\FbtException
     */
    public function convertToFbtFunctionCallNode(): fbtNamespace
    {
        $this->_assertNoNestedFbts();

        if (! $this->_isImplicitFbt()) {
            $this->_addImplicitDescriptionsToChildrenRecursively();
        }

        // js~php diff:

        // $this->_setPhraseIndexOnImplicitChildren($phraseIndex);

        $children = $this->_transformChildrenToFbtCalls($this->node->nodes);

        $text = $this->_getText($children);

        $description = $this->_getDescription($text);

        return $this->_createFbtFunctionCallNode(
            $text,
            $description,
            $this->_getOptions()
        );
    }
}
