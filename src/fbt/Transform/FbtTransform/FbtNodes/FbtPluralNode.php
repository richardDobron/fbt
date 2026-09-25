<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

use function fbt\invariant;

use fbt\Transform\FbtTransform\FbtCallExpression;
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
     * @param mixed $node
     */
    public static function fromNode(string $moduleName, $node): ?self
    {
        return FbtNodeUtil::createInstanceFromFbtConstructCallsite($moduleName, $node, self::class);
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
            $showCount = FbtUtils::enforceStringEnumOrNull(
                $rawOptions['showCount'] ?? null,
                FbtConstants::SHOW_COUNT,
                '`showCount` option'
            ) ?: FbtConstants::SHOW_COUNT_KEYS['no'];
            $name = FbtUtils::enforceStringOrNull($rawOptions['name'] ?? null, '`name` option') ?:
                ($showCount !== FbtConstants::SHOW_COUNT_KEYS['no'] ? FbtConstants::PLURAL_PARAM_TOKEN : null);

            return [
                'count' => $args[1],
                'many' => FbtUtils::enforceStringOrNull($rawOptions['many'] ?? null, '`many` option'),
                'name' => $name,
                'showCount' => $showCount,
                'value' => $rawOptions['value'] ?? null,
                'key' => $rawOptions['key'] ?? null,
            ];
        } catch (\Throwable $error) {
            throw FbtUtils::errorAt($this->node, $error);
        }
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
            throw FbtUtils::errorAt($this->node, $error);
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

    public function getFbtRuntimeArg(): ?FbtCallExpression
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

        return FbtUtils::createFbtRuntimeArgCallExpression($this, $pluralArgs);
    }
}
