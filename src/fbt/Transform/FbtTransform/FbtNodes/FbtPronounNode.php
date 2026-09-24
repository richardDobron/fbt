<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

use dobron\DomForge\Node;

use function fbt\invariant;

use fbt\Runtime\Gender;
use fbt\Transform\FbtTransform\FbtConstants;
use fbt\Transform\FbtTransform\FbtUtils;
use fbt\Transform\FbtTransform\Translate\IntlVariations;

/**
 * Represents an <fbt:pronoun> or fbt::pronoun() construct.
 * @see docs/pronouns.md
 */
class FbtPronounNode extends FbtNode
{
    public const TYPE = FbtNodeType::PRONOUN;

    private const HUMAN_OPTION = 'human';

    /** @var int[]|null */
    private static $candidatePronounGenders = null;

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
        $moduleName = $this->moduleName;
        $args = $this->getCallNodeArguments() ?? [];
        $rawOptions = FbtUtils::collectOptionsFromFbtConstruct(
            $moduleName,
            $args[2] ?? null,
            FbtConstants::VALID_PRONOUN_OPTIONS,
            FbtConstants::VALID_PRONOUN_OPTIONS_BOOLEAN
        );

        try {
            $usageArg = $args[0] ?? null;
            $genderArg = $args[1] ?? null;
            invariant(
                is_string($usageArg),
                '`usage`, the first argument of %s::pronoun() must be a `StringLiteral` but we got `%s`',
                $moduleName,
                gettype($usageArg)
            );
            invariant(
                isset(FbtConstants::VALID_PRONOUN_USAGES[$usageArg]),
                '`usage`, the first argument of %s::pronoun() - Expected value to be one of [%s] but we got %s instead',
                $moduleName,
                implode(', ', array_keys(FbtConstants::VALID_PRONOUN_USAGES)),
                $usageArg
            );
            invariant(
                $genderArg !== null,
                '`gender`, the second argument - Expected value'
            );

            return [
                'capitalize' => $rawOptions['capitalize'] ?? null,
                'gender' => $genderArg,
                'human' => $rawOptions['human'] ?? null,
                'type' => $usageArg,
                'key' => $rawOptions['key'] ?? null,
            ];
        } catch (\Throwable $error) {
            throw FbtNodeUtil::errorAt($this->node, $error);
        }
    }

    public function initCheck(): void
    {
        $args = $this->getCallNodeArguments();
        invariant(
            $args === null || count($args) === 2 || count($args) === 3,
            "Expected '(usage, gender [, options])' arguments to %s::pronoun()",
            $this->moduleName
        );
    }

    public function getText(StringVariationArgsMap $argsMap): string
    {
        try {
            $svArgValue = $argsMap->get($this)->value;
            invariant($svArgValue !== null, 'Expected string variation value');

            $word = Gender::getData(
                $svArgValue === IntlVariations::GENDER_ANY
                    ? Gender::GENDER_CONST['UNKNOWN_PLURAL']
                    : (int)$svArgValue,
                $this->options['type']
            );
            invariant(
                is_string($word),
                'Expected pronoun word to be a string but we got %s',
                gettype($word)
            );

            return $this->options['capitalize']
                ? mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1)
                : $word;
        } catch (\Throwable $error) {
            throw FbtNodeUtil::errorAt($this->node, $error);
        }
    }

    public function getArgsForStringVariationCalc(): array
    {
        $candidates = [];

        foreach (self::candidatePronounGenders() as $gender) {
            if ($this->options['human'] === true && $gender === Gender::GENDER_CONST['NOT_A_PERSON']) {
                continue;
            }
            $resolvedGender = self::getPronounGenderKey($this->options['type'], $gender);
            $candidate = $resolvedGender === Gender::GENDER_CONST['UNKNOWN_PLURAL']
                ? IntlVariations::GENDER_ANY
                : $resolvedGender;

            if (! in_array($candidate, $candidates, true)) {
                $candidates[] = $candidate;
            }
        }

        return [
            new GenderStringVariationArg($this, $this->options['gender'], $candidates),
        ];
    }

    public function getFbtRuntimeArg(): ?array
    {
        $pronounArgs = [
            FbtConstants::VALID_PRONOUN_USAGES[$this->options['type']],
            $this->options['gender'],
        ];
        if ($this->options['human']) {
            $pronounArgs[] = [self::HUMAN_OPTION => 1];
        }

        return $this->createFbtRuntimeArgCallExpression($pronounArgs);
    }

    /**
     * Must match implementation from fbt::getPronounGenderKey()
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public static function getPronounGenderKey(string $usage, int $gender): int
    {
        switch ($gender) {
            case Gender::GENDER_CONST['NOT_A_PERSON']:
                return $usage === 'object' || $usage === 'reflexive'
                    ? Gender::GENDER_CONST['NOT_A_PERSON']
                    : Gender::GENDER_CONST['UNKNOWN_PLURAL'];

            case Gender::GENDER_CONST['FEMALE_SINGULAR']:
            case Gender::GENDER_CONST['FEMALE_SINGULAR_GUESS']:
                return Gender::GENDER_CONST['FEMALE_SINGULAR'];

            case Gender::GENDER_CONST['MALE_SINGULAR']:
            case Gender::GENDER_CONST['MALE_SINGULAR_GUESS']:
                return Gender::GENDER_CONST['MALE_SINGULAR'];

            case Gender::GENDER_CONST['MIXED_UNKNOWN']:
            case Gender::GENDER_CONST['FEMALE_PLURAL']:
            case Gender::GENDER_CONST['MALE_PLURAL']:
            case Gender::GENDER_CONST['NEUTER_PLURAL']:
            case Gender::GENDER_CONST['UNKNOWN_PLURAL']:
                return Gender::GENDER_CONST['UNKNOWN_PLURAL'];

            case Gender::GENDER_CONST['NEUTER_SINGULAR']:
            case Gender::GENDER_CONST['UNKNOWN_SINGULAR']:
                return $usage === 'reflexive'
                    ? Gender::GENDER_CONST['NOT_A_PERSON']
                    : Gender::GENDER_CONST['UNKNOWN_PLURAL'];
        }

        invariant(false, 'Unknown GENDER_CONST value: %s', $gender);
    }

    /**
     * @return int[]
     */
    private static function candidatePronounGenders(): array
    {
        if (self::$candidatePronounGenders === null) {
            $set = [];
            foreach (Gender::GENDER_CONST as $gender) {
                foreach (array_keys(FbtConstants::VALID_PRONOUN_USAGES) as $usage) {
                    $set[self::getPronounGenderKey($usage, $gender)] = true;
                }
            }
            $genders = array_keys($set);
            sort($genders);
            self::$candidatePronounGenders = $genders;
        }

        return self::$candidatePronounGenders;
    }
}
