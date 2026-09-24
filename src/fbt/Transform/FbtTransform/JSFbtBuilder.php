<?php

namespace fbt\Transform\FbtTransform;

use function fbt\invariant;

use fbt\Transform\FbtTransform\FbtNodes\EnumStringVariationArg;
use fbt\Transform\FbtTransform\FbtNodes\FbtElementNode;
use fbt\Transform\FbtTransform\FbtNodes\FbtEnumNode;
use fbt\Transform\FbtTransform\FbtNodes\FbtImplicitParamNode;
use fbt\Transform\FbtTransform\FbtNodes\FbtNameNode;
use fbt\Transform\FbtTransform\FbtNodes\FbtParamNode;
use fbt\Transform\FbtTransform\FbtNodes\FbtPluralNode;
use fbt\Transform\FbtTransform\FbtNodes\FbtPronounNode;
use fbt\Transform\FbtTransform\FbtNodes\GenderStringVariationArg;
use fbt\Transform\FbtTransform\FbtNodes\NumberStringVariationArg;
use fbt\Transform\FbtTransform\FbtNodes\StringVariationArg;
use fbt\Transform\FbtTransform\Translate\IntlVariations;

/**
 * Helper class to assemble the JSFBT table data.
 * It's responsible for:
 * - producing all the combinations of string variations' candidate values,
 * from a given list of string variation arguments.
 * - generating metadata to describe the meaning of each level of the JSFBT table tree.
 */
class JSFbtBuilder
{
    /**
     * Map of fbt:enum at the current recursion level of `_getStringVariationCombinations()`
     * @var array<string, string>
     */
    private $usedEnums = [];
    /**
     * Map of fbt:plural at the current recursion level of `_getStringVariationCombinations()`
     * @var array<string, string>
     */
    private $usedPlurals = [];
    /**
     * Map of fbt:pronoun at the current recursion level of `_getStringVariationCombinations()`
     * @var array<string, int|string>
     */
    private $usedPronouns = [];
    /**
     * Set this to `true` if we're extracting strings for React Native
     * @var bool
     */
    private $reactNativeMode;
    /**
     * List of string variation arguments from a given fbt callsite
     * @var StringVariationArg[]
     */
    private $stringVariationArgs;

    /**
     * @param StringVariationArg[] $stringVariationArgs
     * @param bool $reactNativeMode
     */
    public function __construct(array $stringVariationArgs, bool $reactNativeMode = false)
    {
        $this->reactNativeMode = $reactNativeMode;
        $this->stringVariationArgs = array_values($stringVariationArgs);
    }

    /**
     * Generates a list of metadata entries that describe the usage of each level
     * of the JSFBT table tree
     *
     * @param StringVariationArg[] $compactStringVariationArgs Consolidated list of string variation arguments.
     * See FbtFunctionCallProcessor::_compactStringVariationArgs()
     *
     * @return array<int, array|null>
     * @throws \fbt\Exceptions\FbtException
     */
    public function buildMetadata(array $compactStringVariationArgs): array
    {
        return array_map(function (StringVariationArg $svArg) {
            $fbtNode = $svArg->fbtNode;

            if ($fbtNode instanceof FbtPluralNode) {
                if ($fbtNode->options['showCount'] !== FbtConstants::SHOW_COUNT_KEYS['no']) {
                    invariant($fbtNode->options['name'] !== null, 'Expected token name');

                    return [
                        'token' => $fbtNode->options['name'],
                        'type' => IntlVariations::INTL_FBT_VARIATION_TYPE['NUMBER'],
                        'singular' => true,
                    ];
                }

                return $this->reactNativeMode ? ['type' => IntlVariations::INTL_FBT_VARIATION_TYPE['NUMBER']] : null;
            }

            if ($fbtNode instanceof FbtElementNode || $fbtNode instanceof FbtImplicitParamNode) {
                return [
                    'token' => IntlVariations::SUBJECT,
                    'type' => IntlVariations::INTL_FBT_VARIATION_TYPE['GENDER'],
                ];
            }

            if ($fbtNode instanceof FbtPronounNode) {
                return $this->reactNativeMode ? ['type' => IntlVariations::INTL_FBT_VARIATION_TYPE['PRONOUN']] : null;
            }

            if ($svArg instanceof EnumStringVariationArg) {
                invariant(
                    $fbtNode instanceof FbtEnumNode,
                    'Expected fbtNode to be an instance of FbtEnumNode but got `%s` instead',
                    get_class($fbtNode)
                );

                // We ensure we have placeholders in our metadata because enums and
                // pronouns don't have metadata and will add "levels" to our resulting
                // table.
                //
                // Example for the code:
                //
                //   fbt::enum($value, [
                //     'groups' => 'Groups',
                //     'photos' => 'Photos',
                //     'videos' => 'Videos',
                //   ])
                //
                // Expected metadata entry:
                //   for non-RN -> `null`
                //   for RN     -> `['range' => ['groups', 'photos', 'videos']]`
                return $this->reactNativeMode
                    // Enum range will later be used to extract enums from the payload for React Native
                    ? ['range' => FbtUtils::jsObjectKeys($fbtNode->options['range'])]
                    : null;
            }

            if ($svArg instanceof GenderStringVariationArg || $svArg instanceof NumberStringVariationArg) {
                invariant(
                    $fbtNode instanceof FbtNameNode || $fbtNode instanceof FbtParamNode,
                    'Expected fbtNode to be an instance of FbtNameNode or FbtParamNode but got `%s` instead',
                    get_class($fbtNode)
                );

                return $svArg instanceof NumberStringVariationArg
                    ? [
                        'token' => $fbtNode->options['name'],
                        'type' => IntlVariations::INTL_FBT_VARIATION_TYPE['NUMBER'],
                    ]
                    : [
                        'token' => $fbtNode->options['name'],
                        'type' => IntlVariations::INTL_FBT_VARIATION_TYPE['GENDER'],
                    ];
            }

            invariant(false, 'Unsupported string variation argument: %s', get_class($svArg));
        }, array_values($compactStringVariationArgs));
    }

    /**
     * Get all the string variation combinations derived from a list of string variation arguments.
     *
     * E.g. If we have a list of string variation arguments as:
     *
     * [genderSV, numberSV]
     *
     * Assuming genderSV produces candidate variation values as: male, female, unknown
     * Assuming numberSV produces candidate variation values as: singular, plural
     *
     * The output would be:
     *
     * [
     *   [  genderSV(male),     numberSV(singular)  ],
     *   [  genderSV(male),     numberSV(plural)    ],
     *   [  genderSV(female),   numberSV(singular)  ],
     *   [  genderSV(female),   numberSV(plural)    ],
     *   [  genderSV(unknown),  numberSV(singular)  ],
     *   [  genderSV(unknown),  numberSV(plural)    ],
     * ]
     *
     * Follows legacy behavior:
     *   - process each SV argument (FIFO),
     *   - for each SV argument of the same fbt construct (e.g. plural)
     *     (and not of the same variation type like Gender)
     *     - check if there's already an existing SV argument of the same code being used
     *       - if so, re-use the same variation value
     *       - else, "multiplex" new variation value
     *       Do this for plural, gender, enum
     *
     * @return array<int, StringVariationArg[]>
     * @throws \fbt\Exceptions\FbtException
     */
    public function getStringVariationCombinations(): array
    {
        $combos = [];
        $this->_getStringVariationCombinations($combos);

        return $combos;
    }

    /**
     * @param array<int, StringVariationArg[]> $combos
     * @param int $curArgIndex
     * @param StringVariationArg[] $prevArgs
     *
     * @throws \fbt\Exceptions\FbtException
     */
    private function _getStringVariationCombinations(array &$combos, int $curArgIndex = 0, array $prevArgs = []): void
    {
        invariant(
            $curArgIndex >= 0,
            'curArgIndex value must greater or equal to 0, but we got `%s` instead',
            $curArgIndex
        );

        if (count($this->stringVariationArgs) === 0) {
            return;
        }

        if ($curArgIndex >= count($this->stringVariationArgs)) {
            $combos[] = $prevArgs;

            return;
        }

        $curArg = $this->stringVariationArgs[$curArgIndex];
        $fbtNode = $curArg->fbtNode;

        $recurse = function (array $candidateValues, ?callable $beforeRecurse = null, bool $isCollapsible = false) use (&$combos, $curArgIndex, $prevArgs, $curArg): void {
            foreach ($candidateValues as $value) {
                if ($beforeRecurse) {
                    $beforeRecurse($value);
                }
                $this->_getStringVariationCombinations(
                    $combos,
                    $curArgIndex + 1,
                    array_merge($prevArgs, [$curArg->cloneWithValue($value, $isCollapsible)])
                );
            }
        };

        if ($fbtNode instanceof FbtEnumNode) {
            invariant(
                $curArg instanceof EnumStringVariationArg,
                'Expected EnumStringVariationArg but got: %s',
                get_class($curArg)
            );
            $argCode = $curArg->getArgCode();

            if (array_key_exists($argCode, $this->usedEnums)) {
                $enumKey = $this->usedEnums[$argCode];
                invariant(
                    array_key_exists($enumKey, $fbtNode->options['range']),
                    '%s not found in %s. Attempting to re-use incompatible enums',
                    $enumKey,
                    json_encode($fbtNode->options['range'])
                );

                $recurse([$enumKey], null, true);

                return;
            }

            $recurse($curArg->candidateValues, function ($value) use ($argCode) {
                $this->usedEnums[$argCode] = $value;
            });
            unset($this->usedEnums[$argCode]);
        } elseif ($fbtNode instanceof FbtPluralNode) {
            invariant(
                $curArg instanceof NumberStringVariationArg,
                'Expected NumberStringVariationArg but got: %s',
                get_class($curArg)
            );
            $argCode = $curArg->getArgCode();

            if (array_key_exists($argCode, $this->usedPlurals)) {
                // Constrain our plural value ('many'/'singular') BUT still add a
                // single level.  We don't currently prune runtime args like we do
                // with enums, but we ought to...
                $recurse([$this->usedPlurals[$argCode]]);

                return;
            }

            $recurse($curArg->candidateValues, function ($value) use ($argCode) {
                $this->usedPlurals[$argCode] = $value;
            });
            unset($this->usedPlurals[$argCode]);
        } elseif ($fbtNode instanceof FbtPronounNode) {
            invariant(
                $curArg instanceof GenderStringVariationArg,
                'Expected GenderStringVariationArg but got: %s',
                get_class($curArg)
            );
            $argCode = $curArg->getArgCode();

            if (array_key_exists($argCode, $this->usedPronouns)) {
                // Constrain our pronoun value BUT still add a
                // single level.  We don't currently prune runtime args like we do
                // with enums, but we ought to...
                $recurse([$this->usedPronouns[$argCode]]);

                return;
            }

            $recurse($curArg->candidateValues, function ($value) use ($argCode) {
                $this->usedPronouns[$argCode] = $value;
            });
            unset($this->usedPronouns[$argCode]);
        } elseif ($curArg instanceof NumberStringVariationArg || $curArg instanceof GenderStringVariationArg) {
            $recurse(
                $curArg->candidateValues,
                null,
                $curArg instanceof GenderStringVariationArg && $fbtNode instanceof FbtImplicitParamNode
            );
        } else {
            invariant(false, 'Unsupported string variation argument: %s', get_class($curArg));
        }
    }
}
