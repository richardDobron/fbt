<?php

namespace fbt\Transform\FbtTransform\Utils;

use dobron\DomForge\Node;
use fbt\Exceptions\FbtParserException;
use fbt\Transform\FbtTransform\FbtConstants;
use fbt\Transform\FbtTransform\FbtNodeChecker;
use fbt\Transform\FbtTransform\FbtUtils;

class GetNamespacedArgs
{
    /**
     * @throws \fbt\Exceptions\FbtParserException
     */
    private static function getAttributeOrThrow(Node $node, string $name): string
    {
        try {
            return (string)FbtUtils::getAttributeByNameOrThrow($node, $name);
        } catch (FbtParserException $error) {
            throw FbtUtils::errorAt($node, $error->getMessage());
        }
    }

    private $moduleName;

    public function __construct(string $moduleName)
    {
        $this->moduleName = $moduleName;
    }

    /**
     * <fbt:param>
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public function param(Node $node): array
    {
        $nameAttr = self::getAttributeOrThrow($node, 'name');
        $options = FbtUtils::getOptionsFromAttributes($node, FbtConstants::validParamOptions(), FbtConstants::REQUIRED_PARAM_OPTIONS);

        $children = FbtUtils::filterEmptyNodes($node->nodes);
        $paramChildren = array_filter($children, function (Node $node) {
            return $node->isElement();
        });

        // js~php diff: a text is the equivalent of an {expression} (a single space
        // is kept, like in upstream fbt)
        if (count($paramChildren) > 1 || ($children === [] && $node->innerHtml !== ' ')) {
            throw FbtUtils::errorAt($node, "$this->moduleName:param expects an {expression} or JSX element, and only one");
        }

        // js~php diff: an HTML element is a rich content, which the fbs runtime doesn't accept
        if ($this->moduleName === FbtConstants::MODULE_NAME['FBS']) {
            foreach ($paramChildren as $child) {
                if (FbtNodeChecker::forFbt($child) === null) {
                    throw FbtUtils::errorAt($node, FbtConstants::FBS_RICH_CONTENT_ERROR);
                }
            }
        }

        if (strpos($nameAttr, "\n") !== false) {
            $nameAttr = FbtUtils::normalizeSpaces($nameAttr);
        }

        $value = $node->innerHtml;
        if (array_key_exists('number', $options)) {
            $value = self::toNumberIfNumeric($value);
        }

        $paramArgs = [$nameAttr, $value];

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
        $children = FbtUtils::filterEmptyNodes($node->nodes);
        $pluralChildren = array_values(array_filter($children, function (Node $node) {
            return $node->isText();
        }));

        if (count($pluralChildren) !== 1) {
            throw FbtUtils::errorAt($node, "$this->moduleName:plural expects text or an expression, and only one");
        }

        $singularNode = $pluralChildren[0];
        $singularText = $singularNode->innerHtml;
        $singularArg = FbtUtils::jsTrimRight(FbtUtils::normalizeSpaces($singularText));

        if (isset($options['value'])) {
            $options['value'] = self::toNumberIfNumeric($options['value']);
        }

        return [$singularArg, $countAttr, $options];
    }

    /**
     * js~php diff: HTML values are strings, so a numeric value is the equivalent of
     * a JSX number expression (e.g. `{1234}`), which the runtime formats
     * (see fbt::_param() and fbt::_plural())
     *
     * @param mixed $value
     * @return mixed
     */
    private static function toNumberIfNumeric($value)
    {
        return is_string($value) && is_numeric($value) ? +$value : $value;
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
            throw FbtUtils::errorAt($node, "$this->moduleName:pronoun attribute \"type\" must be one of [" . implode(',', array_keys($validPronounUsages)) . ']');
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
        $children = FbtUtils::filterEmptyNodes($node->nodes);
        $nameChildren = array_values(array_filter($children, function (Node $node) {
            return $node->isText();
        }));

        if (count($nameChildren) !== 1) {
            throw FbtUtils::errorAt($node, "$this->moduleName:name expects text or an expression, and only one");
        }

        $singularArg = FbtUtils::normalizeSpaces($nameChildren[0]->innerHtml);

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
            throw FbtUtils::errorAt($node, "Expected $this->moduleName:same-param to be selfClosing.");
        }

        $nameAttr = self::getAttributeOrThrow($node, 'name');

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
            throw FbtUtils::errorAt($node, "Expected $this->moduleName:enum to be selfClosing.");
        }

        $rangeAttr = self::getAttributeOrThrow($node, 'enum-range');

        try {
            $rangeAttrValue = FbtUtils::extractEnumRange($rangeAttr);
        } catch (\Exception $ex) {
            // js~php diff: the range is JSON (a JSX expression in upstream fbt)
            throw FbtUtils::errorAt($node, 'Expected JSON for enum-range attribute but got ' . $rangeAttr);
        }

        $valueAttr = self::getAttributeOrThrow($node, 'value');

        $enumArgs = [$valueAttr, $rangeAttrValue];

        // js~php diff: optional `key` (identity of the enum value), other attributes
        // are ignored (like in upstream fbt)
        $key = FbtUtils::getAttributeByName($node, 'key');
        if ($key !== null) {
            $enumArgs[] = ['key' => $key];
        }

        return $enumArgs;
    }
}
