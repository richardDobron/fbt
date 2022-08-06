<?php

namespace fbt\Transform\FbtTransform;

use fbt\Exceptions\FbtException;
use function fbt\invariant;
use fbt\Runtime\Gender;
use fbt\Transform\FbtTransform\Translate\IntlVariations;

class JSFbtBuilder
{
    /** @var array */
    private $usedEnums;
    /** @var array */
    private $usedPronouns;
    /** @var array */
    private $usedPlurals;
    /** @var bool */
    private $reactNativeMode;

    const PLURAL_KEY_TO_TYPE = [
        '*' => 'many',
        IntlVariations::EXACTLY_ONE => 'singular',
    ];

    public function __construct(bool $reactNativeMode)
    {
        $this->usedEnums = [];
        $this->usedPronouns = [];
        $this->usedPlurals = [];
        $this->reactNativeMode = $reactNativeMode;
    }

    /**
     * @param $type
     * @param $texts
     * @param bool $reactNativeMode
     * @return array|string
     *
     * @throws FbtException
     */
    public static function build($type, $texts, bool $reactNativeMode = false)
    {
        $builder = new JSFbtBuilder($reactNativeMode);
        if ($type === FbtConstants::FBT_TYPE['TEXT']) {
            invariant(count($texts) === 1, 'Text type is a singleton array');

            return FbtUtils::normalizeSpaces($texts[0]);
        } else {
            invariant(
                $type === FbtConstants::FBT_TYPE['TABLE'],
                'We only expect two types of fbt phrases'
            );

            return [
                't' => $builder->buildTable($texts),
                'm' => $builder->buildMetadata($texts),
            ];
        }
    }

    public function buildMetadata($texts): array
    {
        $metadata = [];
        $enums = [];
        foreach ($texts as $item) {
            if (is_string($item)) {
                continue;
            }

            switch ($item['type']) {
                case 'gender':
                case 'number':
                    $metadata[] = [
                        'token' => $item['token'],
                        'type' => $item['type'] === 'number'
                            ? IntlVariations::INTL_FBT_VARIATION_TYPE['NUMBER']
                            : IntlVariations::INTL_FBT_VARIATION_TYPE['GENDER'],
                    ];

                    break;

                case 'plural':
                    if ($item['showCount'] !== 'no') {
                        $metadata[] = [
                            'token' => $item['name'],
                            'type' => IntlVariations::INTL_FBT_VARIATION_TYPE['NUMBER'],
                            'singular' => true,
                        ];
                    } else {
                        $metadata[] = $this->reactNativeMode
                            ? [
                                'type' => IntlVariations::INTL_FBT_VARIATION_TYPE['NUMBER'],
                            ]
                            : null;
                    }

                    break;

                case 'subject':
                    $metadata[] = [
                        'token' => IntlVariations::SUBJECT,
                        'type' => IntlVariations::INTL_FBT_VARIATION_TYPE['GENDER'],
                    ];

                    break;

                    // We ensure we have placeholders in our metadata because enums and
                    // pronouns don't have metadata and will add "levels" to our resulting
                    // table. In the example in the docblock of buildTable(), we'd expect
                //     array({range: ...}, array('token' => 'count', 'type' => ...))
                case 'enum':
                    // Only add an enum if it adds a level. Duplicated enum values do not
                    // add levels.
                    if (! array_key_exists($item['value'], $enums)) {
                        $enums[$item['value']] = true;
                        $metadataEntry = null;
                        if ($this->reactNativeMode) {
                            // Enum range will later be used to extract enums from the payload
                            // for React Native
                            $metadataEntry = ['range' => array_keys($item['range'])];
                        }
                        $metadata[] = $metadataEntry;
                    }

                    break;

                case 'pronoun':
                    $metadata[] = $this->reactNativeMode
                        ? [
                            'type' => IntlVariations::INTL_FBT_VARIATION_TYPE['PRONOUN'],
                        ]
                        : null;

                    break;

                default:
                    $metadata[] = null;

                    break;
            }
        }

        return $metadata;
    }

    /**
     * Build a tree and set of all the strings - A (potentially multi-level)
     * dictionary of keys of various FBT components (enum, plural, etc) to their
     * string leaves or the next level of the tree.
     *
     * Example (probably a bad example of when to use an enum):
     *
     *   buildTable([
     *     'Click ',
     *     {
     *       'type': 'enum',
     *       'values': ['here', 'there', 'anywhere']
     *     },
     *     ' to see ',
     *     {
     *      'type': 'number',
     *      'token': 'count',
     *      'type': FbtVariationType::NUMBER,
     *     },
     *     'things'
     *   ])
     *
     * Returns:
     *
     *   {
     *     'here': {'*': 'Click here to see {count} things'}
     *     'there': {'*': 'Click there to see {count} things'}
     *     ...
     *   }
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public function buildTable($texts)
    {
        return $this->_buildTable('', $texts, 0);
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    private function _buildTable($prefix, $texts, $idx)
    {
        if ($idx === count($texts)) {
            return FbtUtils::normalizeSpaces($prefix);
        }

        $item = $texts[$idx];

        if (is_string($item)) {
            return $this->_buildTable($prefix . $item, $texts, $idx + 1);
        }

        $textSegments = [];

        switch ($item['type']) {
            case 'subject':
                $textSegments['*'] = '';

                break;
            case 'gender':
            case 'number':
                $textSegments['*'] = '{' . $item['token'] . '}';

                break;

            case 'plural':
                $pluralCount = $item['count'];

                if (array_key_exists($pluralCount, $this->usedPlurals)) {
                    // Constrain our plural value ('many'/'singular') BUT still add a
                    // single level.  We don't currently prune runtime args like we do
                    // with enums, but we ought to...
                    $key = $this->usedPlurals[$pluralCount];
                    $val = $item[self::PLURAL_KEY_TO_TYPE[$key]];

                    return [
                        $key => $this->_buildTable($prefix . $val, $texts, $idx + 1),
                    ];
                }

                $table = FbtUtils::objMap(self::PLURAL_KEY_TO_TYPE, function ($type, $key) use ($pluralCount, $prefix, $item, $texts, $idx) {
                    $this->usedPlurals[$pluralCount] = $key;

                    return $this->_buildTable($prefix . $item[$type], $texts, $idx + 1);
                });

                unset($this->usedPlurals[$pluralCount]);

                return $table;

            case 'pronoun':
                $genderSrc = $item['gender'];
                $isUsed = in_array($genderSrc, $this->usedPronouns);
                $genders = $isUsed ? $this->usedPronouns[$genderSrc] : Gender::GENDER_CONST;
                $resTable = [];
                foreach (array_keys($genders) as $key) {
                    $gender = Gender::GENDER_CONST[$key];

                    if ($gender === Gender::GENDER_CONST['NOT_A_PERSON'] && ! empty($item['human'])) {
                        continue;
                    }

                    if (! $isUsed) {
                        $this->usedPronouns[$genderSrc] = [
                            $key => $gender,
                        ];
                    }

                    $genderKey = self::getPronounGenderKey($item['usage'], $gender);
                    $pivotKey = $genderKey === Gender::GENDER_CONST['UNKNOWN_PLURAL'] ? '*' : $genderKey;
                    $word = Gender::getData($genderKey, $item['usage']);
                    $capWord = ! empty($item['capitalize']) ? mb_strtoupper($word[0]) . mb_substr($word, 1) : $word;
                    $resTable[$pivotKey] = $this->_buildTable($prefix . $capWord, $texts, $idx + 1);
                }

                if (! $isUsed) {
                    unset($this->usedPronouns['genderSrc']);
                }

                // js~php diff
                // @see https://stackoverflow.com/questions/5525795/does-javascript-guarantee-object-property-order
                uksort($resTable, function ($a, $b) {
                    return is_int($b) - is_int($a) ?: strnatcmp($a, $b);
                });

                return $resTable;

            case 'enum':
                //  If this is a duplicate enum, use the stored value.  Otherwise,
                //  create a level in our table.
                $enumArg = $item['value'];
                if (array_key_exists($enumArg, $this->usedEnums)) {
                    $enumKey = $this->usedEnums[$enumArg];

                    if (! array_key_exists($enumKey, $item['range'])) {
                        throw new FbtException($enumKey . ' not found in ' . json_encode($item['range']) . '. Attempting to re-use incompatible enums');
                    }

                    $val = $item['range'][$enumKey];

                    return $this->_buildTable($prefix . $val, $texts, $idx + 1);
                }

                $result = FbtUtils::objMap($item['range'], function ($val, $key) use ($enumArg, $prefix, $texts, $idx) {
                    $this->usedEnums[$enumArg] = $key;

                    return $this->_buildTable($prefix . $val, $texts, $idx + 1);
                });

                unset($this->usedEnums[$enumArg]);

                return $result;
            default:
                break;
        }

        return FbtUtils::objMap($textSegments, function ($v) use ($prefix, $texts, $idx) {
            return $this->_buildTable($prefix . $v, $texts, $idx + 1);
        });
    }

    /**
     * @param string $usage
     * @param int $gender
     *
     * @return int|null
     * @throws FbtException
     */
    public static function getPronounGenderKey(string $usage, int $gender)
    {
        switch ($gender) {
            case Gender::GENDER_CONST['NOT_A_PERSON']:
                return $usage === 'object' || $usage === 'reflexive' ? Gender::GENDER_CONST['NOT_A_PERSON'] : Gender::GENDER_CONST['UNKNOWN_PLURAL'];

            case Gender::GENDER_CONST['FEMALE_SINGULAR']:
            case Gender::GENDER_CONST['FEMALE_SINGULAR_GUESS']:
                return Gender::GENDER_CONST['FEMALE_SINGULAR'];

            case Gender::GENDER_CONST['MALE_SINGULAR']:
            case Gender::GENDER_CONST['MALE_SINGULAR_GUESS']:
                return Gender::GENDER_CONST['MALE_SINGULAR'];

            case Gender::GENDER_CONST['MIXED_SINGULAR']: // And MIXED_PLURAL; they have the same integer values.
            case Gender::GENDER_CONST['FEMALE_PLURAL']:
            case Gender::GENDER_CONST['MALE_PLURAL']:
            case Gender::GENDER_CONST['NEUTER_PLURAL']:
            case Gender::GENDER_CONST['UNKNOWN_PLURAL']:
                return Gender::GENDER_CONST['UNKNOWN_PLURAL'];

            case Gender::GENDER_CONST['NEUTER_SINGULAR']:
            case Gender::GENDER_CONST['UNKNOWN_SINGULAR']:
                return $usage === 'reflexive' ? Gender::GENDER_CONST['NOT_A_PERSON'] : Gender::GENDER_CONST['UNKNOWN_PLURAL'];
        }

        invariant(false, 'Unknown GENDER_CONST value.');

        return null;
    }
}
