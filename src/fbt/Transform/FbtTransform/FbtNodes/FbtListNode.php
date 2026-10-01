<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

use fbt\Transform\FbtTransform\FbtCallExpression;
use fbt\Transform\FbtTransform\FbtUtils;

/**
 * Represents an <fbt:list> or fbt::list() construct (port of FbtListNode of fbtee).
 * @see docs/lists.md
 */
class FbtListNode extends FbtNode
{
    public const TYPE = FbtNodeType::LIST;

    /**
     * @param mixed $node
     */
    public static function fromNode(string $moduleName, $node): ?self
    {
        return FbtNodeUtil::createInstanceFromFbtConstructCallsite($moduleName, $node, self::class);
    }

    public function getArgsForStringVariationCalc(): array
    {
        return [];
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

    public function getOptions(array $validExtraOptions = []): ?array
    {
        $name = ($this->getCallNodeArguments() ?? [])[0] ?? null;

        return [
            'name' => is_string($name) ? $name : null,
        ];
    }

    public function getFbtRuntimeArg(): ?FbtCallExpression
    {
        $args = $this->getCallNodeArguments() ?? [];
        $name = $args[0] ?? null;
        $items = $args[1] ?? null;
        $conjunction = $args[2] ?? null;
        $delimiter = $args[3] ?? null;
        if ($items === null) {
            throw FbtUtils::errorAt($this->node, "Missing required attribute 'items' on <fbt:list>.");
        }

        if ($name === null) {
            throw FbtUtils::errorAt($this->node, "Missing required attribute 'name' on <fbt:list>.");
        }

        $args = [$name, $items];
        $hasDelimiter = $delimiter !== null;
        if ($conjunction !== null || $hasDelimiter) {
            $args[] = $conjunction;
        }
        if ($hasDelimiter) {
            $args[] = $delimiter;
        }

        return FbtUtils::createFbtRuntimeArgCallExpression($this, $args);
    }
}
