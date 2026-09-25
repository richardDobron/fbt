<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

use function fbt\invariant;

use fbt\Runtime\FbtRuntimeTypes;
use fbt\Transform\FbtTransform\FbtCallExpression;
use fbt\Transform\FbtTransform\FbtConstants;
use fbt\Transform\FbtTransform\FbtUtils;
use fbt\Transform\FbtTransform\Translate\IntlVariations;

/**
 * Represents an <fbt:param> or fbt::param() construct.
 * @see docs/params.md
 */
class FbtParamNode extends FbtNode
{
    public const TYPE = FbtNodeType::PARAM;

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
            $args = $this->getCallNodeArguments() ?? [];
            $rawOptions = FbtUtils::collectOptionsFromFbtConstruct(
                $this->moduleName,
                $args[2] ?? null,
                FbtConstants::validParamOptions()
            );
            $gender = $rawOptions['gender'] ?? null;
            $number = $rawOptions['number'] ?? null;

            invariant(
                $number !== false,
                '`number` option must be an expression or `true`'
            );
            invariant(
                $gender === null || $number === null,
                'Gender and number options must not be set at the same time'
            );

            $name = is_string($rawOptions['name'] ?? null) ? $rawOptions['name'] : null;
            if ($name === null || $name === '') {
                invariant(
                    is_string($args[0] ?? null),
                    'First function argument must be a string literal'
                );
                $name = $args[0];
            }
            invariant(strlen($name) > 0, 'Token name string must not be empty');

            invariant(
                array_key_exists(1, $args),
                'The second function argument must not be null'
            );

            return [
                'gender' => $gender,
                'name' => $name,
                'number' => $number,
                'value' => $args[1],
            ];
        } catch (\Throwable $error) {
            throw FbtUtils::errorAt($this->node, $error);
        }
    }

    public function getArgsForStringVariationCalc(): array
    {
        $gender = $this->options['gender'];
        $number = $this->options['number'];
        $ret = [];
        invariant(
            $gender === null || $number === null,
            'Gender and number options must not be set at the same time'
        );

        if ($gender !== null) {
            $ret[] = new GenderStringVariationArg($this, $gender, [IntlVariations::GENDER_ANY]);
        } elseif ($number !== null) {
            $ret[] = new NumberStringVariationArg(
                $this,
                $number === true ? null : $number,
                [IntlVariations::NUMBER_ANY]
            );
        }

        return $ret;
    }

    public function getTokenName(StringVariationArgsMap $argsMap): ?string
    {
        return $this->options['name'];
    }

    public function getText(StringVariationArgsMap $argsMap): string
    {
        try {
            foreach ($this->getArgsForStringVariationCalc() as $expectedArg) {
                $svArg = $argsMap->get($this);
                invariant(
                    get_class($svArg) === get_class($expectedArg),
                    'Expected SVArgument instance of %s but got %s instead',
                    get_class($expectedArg),
                    get_class($svArg)
                );
            }

            return FbtNodeUtil::tokenNameToTextPattern($this->getTokenName($argsMap));
        } catch (\Throwable $error) {
            throw FbtUtils::errorAt($this->node, $error);
        }
    }

    public function getFbtRuntimeArg(): ?FbtCallExpression
    {
        $gender = $this->options['gender'];
        $number = $this->options['number'];
        $variationValues = null;

        if ($number !== null) {
            $variationValues = [FbtRuntimeTypes::PARAM_VARIATION_TYPE['number']];
            if ($number !== true) {
                $variationValues[] = $number;
            }
        } elseif ($gender !== null) {
            $variationValues = [FbtRuntimeTypes::PARAM_VARIATION_TYPE['gender'], $gender];
        }

        $args = [$this->options['name'], $this->options['value']];
        if ($variationValues !== null) {
            $args[] = $variationValues;
        }

        return FbtUtils::createFbtRuntimeArgCallExpression($this, $args);
    }
}
