<?php

namespace fbt\Transform\FbtTransform\Utils;

use fbt\Transform\FbtTransform\FbtAutoWrap;
use fbt\Transform\FbtTransform\FbtConstants;
use fbt\Transform\FbtTransform\FbtUtils;
use fbt\Util\NodeParser;
use fbt\Util\SimpleHtmlDom\Node;

class GetNamespacedArgs
{
    private $moduleName;

    public function __construct(string $moduleName)
    {
        $this->moduleName = $moduleName;
    }

    /**
     * Node that is a child of a <fbt> node that should be handled as
     * <fbt:param>
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public function implicitParamMarker(Node $node): array
    {
        $newNode = FbtAutoWrap::wrapImplicitFBTParam($this->moduleName, $node);

        return ['=' . $newNode->getAttribute('paramName'), $newNode->outertext()];
    }

    /**
     * <fbt:param> or <FbtParam>
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public function param(Node $node): array
    {
        $nameAttr = FbtUtils::normalizeSpaces(FbtUtils::getAttributeByNameOrThrow($node, 'name'));
        $options = FbtUtils::getOptionsFromAttributes($node, FbtConstants::validParamOptions(), FbtConstants::REQUIRED_PARAM_OPTIONS + FbtUtils::FBT_CORE_ATTRIBUTES);

        // js~php diff:

        $paramChildren = array_values(array_filter(FbtUtils::filterEmptyNodes($node->nodes), function (Node $node) {
            return $node->isElement();
        }));

        if (count($paramChildren) === 0 && count($node->nodes) === 1 && $node->nodes[0]->isText()) {
            $paramChildren = [$node->nodes[0]->innertext()];
        }

        if (count($paramChildren) !== 1) {
            throw FbtUtils::errorAt($node, "$this->moduleName:param expects an string or HTML element, and only one");
        }

        // restore nodes noise (Simple HTML DOM issue)
        $node = NodeParser::parse('<html>' . $node->innertext() . '</html>', false, true, DEFAULT_TARGET_CHARSET, false)
            ->find('html', 0);

        $value = implode('', FbtUtils::makeFbtElementArrayFromNode($node->nodes));

        $paramArgs = [$nameAttr, $value];

        if (count($options) > 0) {
            $paramArgs[] = $options;
        }

        return $paramArgs;
    }

    /**
     * <fbt:plural> or <FbtPlural>
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public function plural(Node $node): array
    {
        $options = FbtUtils::getOptionsFromAttributes($node, FbtConstants::validPluralOptions(), FbtConstants::PLURAL_REQUIRED_ATTRIBUTES);
        $countAttr = FbtUtils::getAttributeByNameOrThrow($node, 'count');
        $pluralChildren = FbtUtils::filterEmptyNodes($node->nodes);

        if (count($pluralChildren) !== 1) {
            throw FbtUtils::errorAt($node, "$this->moduleName:plural expects text or an expression, and only one");
        }

        $singularNode = $pluralChildren[0];
        $singularText = $singularNode->innertext();
        $singularArg = trim(FbtUtils::normalizeSpaces($singularText)); // fbt diff rtrim()

        return [$singularArg, $countAttr, $options];
    }

    /**
     * <fbt:pronoun> or <FbtPronoun>
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public function pronoun(Node $node): array
    {
        if (! $node->isSelfClosing()) {
            throw FbtUtils::errorAt($node, "$this->moduleName:pronoun must be a self-closing element");
        }

        $typeAttr = FbtUtils::getAttributeByNameOrThrow($node, 'type');

        $validPronounUsages = FbtConstants::VALID_PRONOUN_USAGES;
        if (! isset($validPronounUsages[$typeAttr])) {
            throw FbtUtils::errorAt($node, "$this->moduleName:pronoun attribute \"type\" must be one of [" . implode(', ', array_keys($validPronounUsages)) . ']');
        }

        $result = [$typeAttr];
        $result[] = FbtUtils::getAttributeByNameOrThrow($node, 'gender');
        $options = FbtUtils::getOptionsFromAttributes($node, FbtConstants::VALID_PRONOUN_OPTIONS, FbtConstants::PRONOUN_REQUIRED_ATTRIBUTES);

        if (0 < count($options)) {
            $result[] = $options;
        }

        return $result;
    }

    /**
     * <fbt:name> or <FbtName>
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public function name(Node $node): array
    {
        $nameAttribute = FbtUtils::getAttributeByNameOrThrow($node, 'name');
        $genderAttribute = FbtUtils::getAttributeByNameOrThrow($node, 'gender');
        $nameChildren = FbtUtils::filterEmptyNodes($node->nodes);

        if (count($nameChildren) !== 1) {
            throw FbtUtils::errorAt($node, "$this->moduleName:name expects text or an expression, and only one");
        }

        $singularArg = $nameChildren[0];

        if ($singularArg->isText()) {
            $singularArg = FbtUtils::normalizeSpaces($singularArg->innertext());
        }

        return [$nameAttribute, $singularArg, $genderAttribute];
    }

    /**
     * <fbt:same-param> or <FbtSameParam>
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public function sameParam(Node $node): array
    {
        if (! $node->isSelfClosing()) {
            throw FbtUtils::errorAt($node, "$this->moduleName:same-param must be a self-closing element");
        }

        $nameAttr = FbtUtils::getAttributeByNameOrThrow($node, 'name');

        return [$nameAttr];
    }

    /**
     * <fbt:enum> or <FbtEnum>
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public function enum(Node $node): array
    {
        if (! $node->isSelfClosing()) {
            throw FbtUtils::errorAt($node, "$this->moduleName:enum must be a self-closing element");
        }

        $rangeAttr = null;

        try {
            $rangeAttr = FbtUtils::getAttributeByNameOrThrow($node, 'enum-range');
            $rangeAttrValue = FbtUtils::extractEnumRange($rangeAttr);
        } catch (\Exception $ex) {
            throw FbtUtils::errorAt($node, 'Expected JSON for enum-range attribute but got ' . $rangeAttr); // js~php diff
        }

        $valueAttr = FbtUtils::getAttributeByNameOrThrow($node, 'value');

        return [$valueAttr, $rangeAttrValue];
    }
}
