<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

use function fbt\invariant;

use fbt\Transform\FbtTransform\FbtCallExpression;
use fbt\Transform\FbtTransform\FbtUtils;
use fbt\Transform\FbtTransform\Translate\IntlVariations;

/**
 * Represents an <fbt:name> or fbt::name() construct.
 * @see docs/params.md
 */
class FbtNameNode extends FbtNode
{
    public const TYPE = FbtNodeType::NAME;

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
            $moduleName = $this->moduleName;
            $args = $this->getCallNodeArguments() ?? [];
            [$name, $value, $gender] = $args + [null, null, null];

            invariant(
                is_string($name),
                'Expected first argument of %s::name to be a string literal, but got %s',
                $moduleName,
                gettype($name)
            );
            invariant(
                array_key_exists(1, $args),
                'Second argument of %s::name - Expected value',
                $moduleName
            );
            invariant(
                $gender !== null,
                'Third argument of %s::name - Expected value',
                $moduleName
            );

            return [
                'name' => $name,
                'value' => $value,
                'gender' => $gender,
            ];
        } catch (\Throwable $error) {
            throw FbtUtils::errorAt($this->node, $error);
        }
    }

    public function getArgsForStringVariationCalc(): array
    {
        return [
            new GenderStringVariationArg($this, $this->options['gender'], [IntlVariations::GENDER_ANY]),
        ];
    }

    public function getTokenName(StringVariationArgsMap $argsMap): ?string
    {
        return $this->options['name'];
    }

    public function getText(StringVariationArgsMap $argsMap): string
    {
        try {
            $argsMap->mustHave($this);

            return FbtNodeUtil::tokenNameToTextPattern($this->options['name']);
        } catch (\Throwable $error) {
            throw FbtUtils::errorAt($this->node, $error);
        }
    }

    public function getFbtRuntimeArg(): ?FbtCallExpression
    {
        return FbtUtils::createFbtRuntimeArgCallExpression($this, [
            $this->options['name'],
            $this->options['value'],
            $this->options['gender'],
        ]);
    }
}
