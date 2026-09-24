<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

use dobron\DomForge\Node;

use function fbt\invariant;

use fbt\Transform\FbtTransform\FbtConstants;
use fbt\Transform\FbtTransform\FbtUtils;
use fbt\Transform\FbtTransform\Translate\IntlVariations;

/**
 * Represents an <fbt:plural> or fbt::plural() construct.
 * @see docs/plurals.md
 */
class FbtPluralNode extends FbtNode
{
    public const TYPE = FbtNodeType::PLURAL;

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
        $args = $this->getCallNodeArguments() ?? [];
        $rawOptions = FbtUtils::collectOptionsFromFbtConstruct(
            $this->moduleName,
            $args[2] ?? null,
            FbtConstants::validPluralOptions()
        );

        try {
            invariant(
                isset($args[1]),
                '`count`, the second function argument - Expected value'
            );
            $showCount = $rawOptions['showCount'] ?? null;
            if ($showCount !== null) {
                invariant(
                    is_string($showCount) && isset(FbtConstants::SHOW_COUNT[$showCount]),
                    '`showCount` option - Expected value to be one of [%s] but we got %s instead',
                    implode(', ', array_keys(FbtConstants::SHOW_COUNT)),
                    is_scalar($showCount) ? (string)$showCount : gettype($showCount)
                );
            } else {
                $showCount = FbtConstants::SHOW_COUNT_KEYS['no'];
            }

            $name = self::enforceStringOrNull($rawOptions['name'] ?? null, '`name` option');
            if ($name === null || $name === '') {
                $name = $showCount !== FbtConstants::SHOW_COUNT_KEYS['no'] ? FbtConstants::PLURAL_PARAM_TOKEN : null;
            }

            return [
                'count' => $args[1],
                'many' => self::enforceStringOrNull($rawOptions['many'] ?? null, '`many` option'),
                'name' => $name,
                'showCount' => $showCount,
                'value' => $rawOptions['value'] ?? null,
                'key' => $rawOptions['key'] ?? null,
            ];
        } catch (\Throwable $error) {
            throw FbtNodeUtil::errorAt($this->node, $error);
        }
    }

    /**
     * @param mixed $value
     *
     * @throws \fbt\Exceptions\FbtException
     */
    private static function enforceStringOrNull($value, string $valueDesc): ?string
    {
        invariant(
            $value === null || is_string($value),
            '%s - Expected string value instead of %s',
            $valueDesc,
            gettype($value)
        );

        return $value;
    }

    /**
     * @template T
     * @param StringVariationArgsMap $argsMap
     * @param callable(): T $exactlyOne
     * @param callable(): T $anyNumber
     *
     * @return T
     * @throws \fbt\Exceptions\FbtException
     */
    private function _branchByNumberVariation(StringVariationArgsMap $argsMap, callable $exactlyOne, callable $anyNumber)
    {
        $svArgValue = $argsMap->get($this)->value;
        invariant($svArgValue !== null, 'Expected string variation value');

        switch ($svArgValue) {
            case IntlVariations::EXACTLY_ONE:
                return $exactlyOne();
            case IntlVariations::NUMBER_ANY:
                return $anyNumber();
            default:
                invariant(false, 'Unsupported string variation value: %s', $svArgValue);
        }
    }

    private function _getStaticTokenName(): string
    {
        invariant($this->options['name'] !== null, 'Expected token name');

        return $this->options['name'];
    }

    public function getTokenName(StringVariationArgsMap $argsMap): ?string
    {
        return $this->_branchByNumberVariation(
            $argsMap,
            function () {
                return null;
            },
            function () {
                return $this->options['showCount'] !== FbtConstants::SHOW_COUNT_KEYS['no']
                    ? $this->_getStaticTokenName()
                    : null;
            }
        );
    }

    public function getText(StringVariationArgsMap $argsMap): string
    {
        try {
            $showCount = $this->options['showCount'];

            return $this->_branchByNumberVariation(
                $argsMap,
                function () use ($showCount) {
                    return ($showCount === FbtConstants::SHOW_COUNT_KEYS['yes'] ? '1 ' : '') .
                        $this->_getSingularText();
                },
                function () use ($showCount) {
                    $many = $this->options['many'] ?? $this->_getSingularText() . 's';

                    return $showCount !== FbtConstants::SHOW_COUNT_KEYS['no']
                        ? FbtNodeUtil::tokenNameToTextPattern($this->_getStaticTokenName()) . ' ' . $many
                        : $many;
                }
            );
        } catch (\Throwable $error) {
            throw FbtNodeUtil::errorAt($this->node, $error);
        }
    }

    private function _getSingularText(): string
    {
        $callArg0 = ($this->getCallNodeArguments() ?? [])[0] ?? null;
        invariant(
            is_string($callArg0),
            'Expected a StringLiteral but got "%s" instead',
            gettype($callArg0)
        );

        return $callArg0;
    }

    public function getArgsForStringVariationCalc(): array
    {
        return [
            new NumberStringVariationArg($this, $this->options['count'], [
                IntlVariations::NUMBER_ANY,
                IntlVariations::EXACTLY_ONE,
            ]),
        ];
    }

    public function getFbtRuntimeArg(): ?array
    {
        $showCount = $this->options['showCount'];
        $name = $this->options['name'];

        $pluralArgs = [$this->options['count']];
        if ($showCount !== FbtConstants::SHOW_COUNT_KEYS['no']) {
            invariant(
                $name !== null,
                'name must be defined when showCount=%s',
                $showCount
            );
            $pluralArgs[] = $name;
            if ($this->options['value'] !== null && $this->options['value'] !== '') {
                $pluralArgs[] = $this->options['value'];
            }
        }

        return $this->createFbtRuntimeArgCallExpression($pluralArgs);
    }
}
