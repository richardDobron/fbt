<?php

namespace fbt\Transform\FbtTransform\Processors;

use fbt\Exceptions\FbtParserException;
use fbt\Runtime\fbtNode;
use fbt\Runtime\Shared\fbt;
use fbt\Transform\FbtTransform\FbtConstants;
use fbt\Transform\FbtTransform\FbtUtils;
use fbt\Transform\FbtTransform\JSFbtBuilder;

class FbtFunctionCallProcessor
{
    /* @var string */
    protected $moduleName;
    /* @var string | array */
    protected $text;
    /* @var string */
    protected $desc;

    /* @var array */
    protected $defaultFbtOptions = [];
    /* @var array */
    protected $options = [];
    /* @var array */
    private $paramSet = [];
    /* @var array */
    protected $variations = [];
    /* @var bool */
    private $hasTable = false;
    /* @var array */
    private $usedEnums = [];
    /* @var array */
    protected $runtimeArgs = [];

    public const VARIATION = [
        'number' => 0,
        'gender' => 1,
    ];

    /**
     * @return void
     * @throws \fbt\Exceptions\FbtParserException
     */
    protected function _getOptions(array $options): void
    {
        $this->options = $options;

        foreach (array_keys(FbtConstants::FBT_BOOLEAN_OPTIONS) as $key) {
            if (isset($this->options[$key])) {
                $this->options[$key] = FbtUtils::getOptionBooleanValue($this->options, $key);
            }
        }
    }

    public static function callFbt($name, $args)
    {
        return call_user_func_array([fbt::class, '_' . $name], $args);
    }

    /**
     * @param fbtNode[] $texts
     *
     * @return void
     * @throws \fbt\Exceptions\FbtParserException
     */
    protected function traverse(array $texts): void
    {
        $moduleName = $this->moduleName;

        foreach ($texts as $construct) {
            $node = $construct->node;
            $constructName = $construct->name;
            $args = $construct->args;
            @list($arg0, $arg1, $arg2) = $args;

            if ($constructName === 'param' || $constructName === 'sameParam') {
                // Collect params only if it's original one (not "sameParam").
                // Variation case. Replace:
                // ['number' => true]     -> ['type' => "number", 'token' => <param-name>]
                // ['gender' => <gender>] -> ['type' => "gender", 'token' => <param-name>]
                if (count($construct->args) === 3) {
                    $paramName = $arg0;
                    // TODO(T69419475): detect variation type by property name instead
                    // of expecting it to be the first object property
                    $key = array_keys($arg2)[0];
                    $variationInfo = $arg2[$key];
                    $variationName = $key ?? $arg2[$key];
                    $this->variations[$paramName] = [
                        'type' => $variationName,
                        'token' => $paramName,
                    ];
                    $variationValues = [self::VARIATION[$variationName]];
                    $variationValue = FbtUtils::getVariationValue(
                        $this->moduleName,
                        $variationName,
                        $variationInfo,
                        $node
                    );
                    if ($variationValue) {
                        $variationValues[] = $variationValue;
                    }

                    $args[2] = $variationValues;
                }

                if ($constructName === 'param') {
                    $this->runtimeArgs[] = self::callFbt('param', $args);

                    FbtUtils::setUniqueToken($node, $this->moduleName, $arg0, $this->paramSet);
                }

                if (count($construct->args) === 3) {
                    continue;
                }

                $construct->value = '{' . $arg0 . '}';
            } elseif ($constructName === 'enum') {
                $this->hasTable = true; // `enum` is a reserved word, so it should be quoted.

                $rawValue = $arg0;
                $usedVal = $this->usedEnums[$rawValue] ?? null;

                if (! $usedVal) {
                    $this->usedEnums[$rawValue] = true;
                    $this->runtimeArgs[] = self::callFbt('enum', $construct->args);
                }
            } elseif ($constructName === 'plural') {
                $this->hasTable = true;
                $count = $arg1;
                $options = FbtUtils::collectOptions($this->moduleName, $arg2, FbtConstants::validPluralOptions());
                $pluralArgs = [$count];

                if (! empty($options['showCount']) && $options['showCount'] !== 'no') {
                    $name = $options['name'] ?? FbtConstants::PLURAL_PARAM_TOKEN;
                    FbtUtils::setUniqueToken($node, $this->moduleName, $name, $this->paramSet);
                    $pluralArgs[] = $name;

                    if (! empty($options['value'])) {
                        $pluralArgs[] = $options['value'];
                    }
                }

                $this->runtimeArgs[] = self::callFbt('plural', $pluralArgs);
            } elseif ($constructName === 'pronoun') {
                // Usage: fbt::pronoun(usage, gender [, options])
                // - enum string usage
                //    e.g. 'object', 'possessive', 'reflexive', 'subject'
                // - enum int gender
                //    e.g. Gender::GENDER_CONST['MALE_SINGULAR'], FEMALE_SINGULAR, etc.

                $this->hasTable = true;

                if (count($args) < 2 || 3 < count($args)) {
                    throw FbtUtils::errorAt($node, "Expected '(usage, gender [, options])' arguments to $moduleName.pronoun");
                }

                $usageExpr = $arg0;

                $validPronounUsages = FbtConstants::VALID_PRONOUN_USAGES;
                if (! isset($validPronounUsages[$usageExpr])) {
                    throw FbtUtils::errorAt($node, "First argument to " . $this->moduleName . ":pronoun must be one of [" . implode(', ', array_keys($validPronounUsages)) . '], got ' . $usageExpr);
                }

                $numericUsageExpr = FbtConstants::VALID_PRONOUN_USAGES[$usageExpr];
                $genderExpr = $arg1;
                $pronounArgs = [$numericUsageExpr, $genderExpr];
                $optionsExpr = $arg2;
                $options = FbtUtils::collectOptions($this->moduleName, $optionsExpr, FbtConstants::VALID_PRONOUN_OPTIONS);

                if (FbtUtils::getOptionBooleanValue($options, 'human', $node)) {
                    $pronounArgs[] = [
                        'human' => 1,
                    ];
                }

                $this->runtimeArgs[] = self::callFbt('pronoun', $pronounArgs);
            } elseif ($constructName === 'name') {
                if (count($args) < 3) {
                    throw FbtUtils::errorAt($node, "Missing arguments. Must have three arguments: label, value, gender");
                }

                $paramName = $arg0;
                $this->variations[$paramName] = [
                    'type' => 'gender',
                    'token' => $paramName,
                ];
                $this->runtimeArgs[] = self::callFbt('name', $args);
            } else {
                throw FbtUtils::errorAt($node, "Unknown $moduleName method $constructName");
            }
        }
    }

    /**
     * @return void
     * @throws \fbt\Exceptions\FbtParserException
     */
    protected function _collectFbtCalls(): void
    {
        if (! empty($this->options['subject'])) {
            $this->hasTable = true;
        }

        if (is_array($this->text)) {
            $this->traverse(array_filter($this->text, function ($text) {
                return ($text instanceof fbtNode);
            }));
        }

        if (! empty($this->options['subject'])) {
            array_unshift($this->runtimeArgs, self::callFbt('subject', [$this->options['subject']]));
        }
    }

    protected function _isTableNeeded(): bool
    {
        return count($this->variations) > 0 || $this->hasTable;
    }

    protected function _convertToStringArrayNodeIfNeeded($textNode): array
    {
        if (is_string($textNode)) {
            return [$textNode];
        }

        return $textNode;
    }

    /**
     * Extracts texts that contains variations or enums, concatenating
     * literal parts.
     * Example:
     *
     * [
     *   'Hello, ', fbt::param('user', user, ['gender' => 'male']), '! ',
     *   'Your score is ', fbt::param('score', $score), '!',
     * ]
     * =>
     *   ["Hello, ", ['type' => 'gender', 'token' => 'user'], "! Your score is {score}!"]
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    private function _extractTableTextsFromStringArray($node, array $variations): array
    {
        return array_reduce($node, function ($results, $element) use ($variations) {
            return array_merge($results, $this->_extractTableTextsFromStringArrayItem($element, $variations));
        }, []);
    }

    /**
     * Extracts texts from each fbt text array item:
     *
     *   "Hello, " . fbt::param('user', $user, ['gender' => 'male']) . "! " .
     *   "Your score is " . fbt::param('score', $score) . "!"
     * =>
     *   ["Hello, ", ['type' => 'gender', 'token' => 'user'], "! Your score is {score}!"]
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    private function _extractTableTextsFromStringArrayItem($node, array $variations, array $texts = []): array
    {
        if (is_string($node)) {
            // If we already collected a literal part previously, and
            // current part is a literal as well, just concatenate them.
            $previousText = $texts[count($texts) - 1] ?? null;

            if (is_string($previousText)) {
                $texts[count($texts) - 1] = FbtUtils::normalizeSpaces($previousText . $node);
            } else {
                $texts[] = $node;
            }

            return $texts;
        } elseif ($node instanceof fbtNode) {
            $args = $node->args;
            @list($arg0, $arg1, $arg2) = $args;

            switch ($node->name) {
                case 'param':
                case 'sameParam':
                    $texts[] = $variations[$arg0] ?? '{' . $arg0 . '}';

                    break;
                case 'enum':
                    $texts[] = [
                        'type' => 'enum',
                        'range' => $args[1],
                        'value' => $args[0],
                    ];

                    break;
                case 'plural':
                    $singular = $arg0;
                    $opts = FbtUtils::collectOptions($this->moduleName, $arg2, FbtConstants::validPluralOptions());
                    $defaultToken = isset($opts['showCount']) && $opts['showCount'] !== 'no' ? FbtConstants::PLURAL_PARAM_TOKEN : null;

                    if (! empty($opts['showCount']) && $opts['showCount'] === 'ifMany' && empty($opts['many'])) {
                        throw new FbtParserException(
                            "The 'many' attribute must be set explicitly if showing count only "
                            . "on 'ifMany', since the singular form presumably starts with an article"
                        );
                    }

                    $data = array_merge($opts, [
                        'type' => 'plural',
                        // Set default value if `opts[optionName]` isn't defined
                        'showCount' => $opts['showCount'] ?? 'no',
                        'name' => $opts['name'] ?? $defaultToken,
                        'singular' => $singular,
                        'count' => $arg1,
                        'many' => $opts['many'] ?? $singular . 's',
                    ]);

                    if (! empty($opts['showCount']) && $opts['showCount'] !== 'no') {
                        if ($opts['showCount'] === 'yes') {
                            $data['singular'] = '1 ' . $data['singular'];
                        }

                        $data['many'] = '{' . $data['name'] . '} ' . $data['many'];
                    }

                    $texts[] = $data;

                    break;
                case 'pronoun':
                    // Usage: fbt::pronoun(usage, gender [, options])
                    $options = FbtUtils::collectOptions($this->moduleName, $arg2, FbtConstants::VALID_PRONOUN_OPTIONS);

                    foreach (array_keys($options) as $key) {
                        $options[$key] = FbtUtils::getOptionBooleanValue($options, $key, $node->node);
                    }

                    $pronounData = array_merge($options, [
                        'type' => 'pronoun',
                        'usage' => $arg0,
                        'gender' => $arg1,
                    ]);

                    $texts[] = $pronounData;

                    break;
                case 'name':
                    $texts[] = $variations[$arg0];

                    break;
            }
        }

        return $texts;
    }

    /**
     * @throws \fbt\Exceptions\FbtParserException
     */
    protected function _getTexts(array $variations, bool $isTable): array
    {
        $options = $this->options;

        $arrayTextNode = $this->_convertToStringArrayNodeIfNeeded($this->text);

        if ($isTable) {
            $texts = $this->_normalizeTableTexts($this->_extractTableTextsFromStringArray($arrayTextNode, $variations));
        } else {
            $unnormalizedText = implode('', $arrayTextNode);
            $texts = [trim(FbtUtils::normalizeSpaces($unnormalizedText, $options))];
        }

        if (isset($options['subject'])) {
            array_unshift($texts, [
                'type' => 'subject',
            ]);
        }

        return $texts;
    }

    /**
     * Normalizes first and last elements in the
     * table texts by triming them left and right accordingly.
     * [" Hello, ", {enum}, " world! "] -> ["Hello, ", {enum}, " world!"]
     */
    protected function _normalizeTableTexts(array $texts): array
    {
        $firstText = $texts[0];

        if (is_string($firstText)) {
            $texts[0] = ltrim($firstText);
        }

        $lastText = $texts[count($texts) - 1] ?? null;

        if (is_string($lastText)) {
            $texts[count($texts) - 1] = rtrim($lastText);
        }

        return $texts;
    }

    protected function _getDescription(): string
    {
        return trim(FbtUtils::normalizeSpaces($this->desc, $this->options));
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    protected function _getPhrase(array $texts, string $desc, bool $isTable): array
    {
        $phraseType = $isTable ? FbtConstants::FBT_TYPE['TABLE'] : FbtConstants::FBT_TYPE['TEXT'];
        $jsfbt = JSFbtBuilder::build($phraseType, $texts);

        return array_merge(
            [
                'desc' => $desc,
            ],
            // Merge with fbt callsite options
            $this->defaultFbtOptions,
            $this->options,
            [
                'type' => $phraseType,
                'jsfbt' => $jsfbt,
            ]
        );
    }
}
