<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

use dobron\DomForge\Node;

use function fbt\invariant;

/**
 * Represents an <fbt:same-param> or fbt::sameParam() construct.
 * @see docs/params.md
 */
class FbtSameParamNode extends FbtNode
{
    public const TYPE = FbtNodeType::SAME_PARAM;

    /**
     * Create a new class instance given the construct's DOM node and arguments.
     */
    public static function fromNode(string $moduleName, ?Node $node, array $callArgs): self
    {
        return new self([
            'moduleName' => $moduleName,
            'node' => $node,
            'callArgs' => $callArgs,
        ]);
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
            throw FbtNodeUtil::errorAt($this->node, $error);
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
            throw FbtNodeUtil::errorAt($this->node, $error);
        }
    }

    public function getArgsForStringVariationCalc(): array
    {
        return [];
    }

    public function getFbtRuntimeArg(): ?array
    {
        return null;
    }
}
