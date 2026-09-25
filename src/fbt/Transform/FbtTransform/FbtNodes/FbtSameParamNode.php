<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

use function fbt\invariant;

use fbt\Transform\FbtTransform\FbtCallExpression;
use fbt\Transform\FbtTransform\FbtUtils;

/**
 * Represents an <fbt:same-param> or fbt::sameParam() construct.
 * @see docs/params.md
 */
class FbtSameParamNode extends FbtNode
{
    public const TYPE = FbtNodeType::SAME_PARAM;

    /**
     * @param mixed $node
     */
    public static function fromNode(string $moduleName, $node): ?self
    {
        return FbtNodeUtil::createInstanceFromFbtConstructCallsite($moduleName, $node, self::class);
    }

    public function getOptions(array $validExtraOptions = []): ?array
    {
        try {
            $name = ($this->getCallNodeArguments() ?? [])[0] ?? null;
            invariant(
                is_string($name),
                'Expected first argument of %s::sameParam to be a string literal, but got `%s`',
                $this->moduleName,
                gettype($name)
            );

            return ['name' => $name];
        } catch (\Throwable $error) {
            throw FbtUtils::errorAt($this->node, $error);
        }
    }

    public function getTokenName(StringVariationArgsMap $argsMap): ?string
    {
        return $this->options['name'];
    }

    public function getText(StringVariationArgsMap $argsMap): string
    {
        try {
            return FbtNodeUtil::tokenNameToTextPattern($this->getTokenName($argsMap));
        } catch (\Throwable $error) {
            throw FbtUtils::errorAt($this->node, $error);
        }
    }

    public function getArgsForStringVariationCalc(): array
    {
        return [];
    }

    public function getFbtRuntimeArg(): ?FbtCallExpression
    {
        return null;
    }
}
