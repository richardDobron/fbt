<?php

namespace fbt\Transform\FbtTransform\Utils;

use dobron\DomForge\Node;
use fbt\Transform\FbtTransform\FbtAutoWrap;
use fbt\Transform\FbtTransform\FbtConstants;
use fbt\Transform\FbtTransform\FbtUtils;

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

        return ['=' . $newNode->context->paramName, $newNode->outerHtml()];
    }

    /**
     * <fbt:param>
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public function param(Node $node): array
    {
        $nameAttr = FbtUtils::normalizeSpaces(FbtUtils::getAttributeByNameOrThrow($node, 'name'));
        $options = FbtUtils::getOptionsFromAttributes($node, FbtConstants::validParamOptions(), FbtConstants::REQUIRED_PARAM_OPTIONS);

        $paramChildren = array_filter(FbtUtils::filterEmptyNodes($node->nodes), function (Node $node) {
            return $node->isElement();
        });

        if (count($paramChildren) > 1) {
            throw FbtUtils::errorAt($node, "$this->moduleName:param expects an string or HTML element, and only one");
        }

        $paramArgs = [$nameAttr, $node->innerHtml];

        if (count($options) > 0) {
            $paramArgs[] = $options;
        }

        return $paramArgs;
    }

    /**
     * <fbt:plural>
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
        $singularText = $singularNode->innerHtml;
        $singularArg = rtrim(FbtUtils::normalizeSpaces($singularText));

        return [$singularArg, $countAttr, $options];
    }

    /**
     * <fbt:pronoun>
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
     * <fbt:name>
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
            $singularArg = FbtUtils::normalizeSpaces($singularArg->innerHtml);
        }

        return [$nameAttribute, $singularArg, $genderAttribute];
    }

    /**
     * <fbt:same-param>
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
     * <fbt:enum>
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
