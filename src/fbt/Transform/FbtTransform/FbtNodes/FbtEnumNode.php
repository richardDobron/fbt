<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

use function fbt\invariant;

use fbt\Transform\FbtTransform\FbtCallExpression;
use fbt\Transform\FbtTransform\FbtConstants;
use fbt\Transform\FbtTransform\FbtUtils;

/**
 * Represents an <fbt:enum> or fbt::enum() construct.
 * @see docs/enums.md
 */
class FbtEnumNode extends FbtNode
{
    public const TYPE = FbtNodeType::ENUM;

    /**
     * @param mixed $node
     */
    public static function fromNode(string $moduleName, $node): ?self
    {
        return FbtNodeUtil::createInstanceFromFbtConstructCallsite($moduleName, $node, self::class);
    }

    public function getOptions(array $validExtraOptions = []): ?array
    {
        $args = $this->getCallNodeArguments() ?? [];
        $value = $args[0] ?? null;
        $rangeArg = $args[1] ?? null;

        try {
            invariant(is_array($rangeArg), '`range` - Expected array value instead of %s', gettype($rangeArg));

            // js~php diff: lists of enum values were already converted to a map
            // of `value => value` by FbtUtils::extractEnumRange()
            $range = [];
            foreach ($rangeArg as $key => $item) {
                invariant(is_string($item), 'Enum values must be string literals');
                $range[$key] = $item;
            }
            invariant(count($range), 'Map of enum entries must not be empty');

            invariant($value !== null, '`value` - Expected value');

            // js~php diff: options for the explicit variation `key`
            $rawOptions = FbtUtils::collectOptionsFromFbtConstruct(
                $this->moduleName,
                $args[2] ?? null,
                FbtConstants::VALID_ENUM_OPTIONS
            );

            return [
                'range' => $range,
                'value' => $value,
                'key' => $rawOptions['key'] ?? null,
            ];
        } catch (\Throwable $error) {
            throw FbtUtils::errorAt($this->node, $error);
        }
    }

    public function getText(StringVariationArgsMap $argsMap): string
    {
        try {
            $svArgValue = $argsMap->get($this)->value;
            invariant($svArgValue !== null, 'Expected string variation value');
            $text = $this->options['range'][$svArgValue] ?? null;
            invariant($text !== null, 'Unable to find enum text for key=%s', $svArgValue);

            return $text;
        } catch (\Throwable $error) {
            throw FbtUtils::errorAt($this->node, $error);
        }
    }

    public function getArgsForStringVariationCalc(): array
    {
        return [
            new EnumStringVariationArg(
                $this,
                $this->options['value'],
                FbtUtils::jsObjectKeys($this->options['range'])
            ),
        ];
    }

    public function getFbtRuntimeArg(): ?FbtCallExpression
    {
        return FbtUtils::createFbtRuntimeArgCallExpression($this, [
            $this->options['value'],
            $this->options['range'],
        ]);
    }
}
