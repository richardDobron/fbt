<?php

namespace fbt\Runtime\Shared;

use fbt\Exceptions\FbtException;
use fbt\FbtConfig;

use function fbt\invariant;

use fbt\Lib\FbtQTOverrides;
use fbt\Runtime\FbtRuntimeTypes;
use fbt\Runtime\FbtTable;
use fbt\Runtime\FbtTranslations;
use fbt\Runtime\Gender;
use fbt\Transform\FbtTransform\FbtUtils;
use fbt\Transform\FbtTransform\JSFbtBuilder;

class fbt
{
    /** @var array */
    private static $_cachedFbtResults = [];

    /**
     * fbt::_() iterates through all indices provided in `args` and accesses
     * the relevant entry in the `table` resulting in the appropriate
     * pattern string.  It then substitutes all relevant substitutions.
     *
     * @param string|array $inputTable - Example: [
     *   "singular" => "You have a cat in a photo album named {title}",
     *   "plural" => "You have cats in a photo album named {title}"
     * ]
     * -or-
     * [
     *   "singular" => ["You have a cat in a photo album named {title}", <hash>],
     *   "plural" => ["You have cats in a photo album named {title}", <hash>]
     * ]
     *
     * or table can simply be a pattern string:
     *   "You have a cat in a photo album named {title}"
     * -or-
     *    ["You have a cat in a photo album named {title}", <hash>]
     *
     * @param array|null $inputArgs - arguments from which to pull substitutions
     *    Example: [["singular", null], [null, ['title' => "felines!"]]]
     *
     * @param array $options - options for runtime
     * translation dictionary access. hk stands for hash key which is used to look
     * up translated payload in React Native. ehk stands for enum hash key which
     * contains a structured enums to hash keys map which will later be traversed
     * to look up enum-less translated payload.
     *
     * @param bool $reporting
     *
     * @return FbtResult|InlineFbtResult
     * @throws FbtException
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     */
    public function _($inputTable, ?array $inputArgs, array $options = [], bool $reporting = true)
    {
        // Adapt the input payload to the translated table and arguments we expect
        //
        // WWW: The payload is ready, as-is, and is pre-translated UNLESS we detect
        //      the magic BINAST string which needs to be stripped if it exists.
        //
        // RN: we look up our translated table via the hash key (options.hk) and
        //     flattened enum hash key (options.ehk), which partially resolves the
        //     translation for the enums (should they exist).
        //
        // OSS: The table is the English payload, and, by default, we lookup the
        //      translated payload via FbtTranslations
        list($pattern, $args) = FbtTranslations::getTranslatedInput($inputTable, $inputArgs, $options) ?? [$inputTable, $inputArgs, FbtTranslations::DEFAULT_SRC_LOCALE];

        // [fbt_impressions]
        // If this is a string literal (no tokens to substitute) then 'args' is empty
        // and the logic will skip the table traversal.

        // [table traversal]
        // At this point we assume that table is a hash (possibly nested) that we
        // need to traverse in order to pick the correct string, based on the
        // args that follow.
        $allSubstitutions = [];

        if (! empty($pattern['__vcg'])) {
            $args = $args ?? [];
            $gender = FbtHooks::getIntlViewerContext()->getGender();
            $variation = IntlVariationResolverImpl::getGenderVariations($gender);
            array_unshift($args, FbtTableAccessor::getGenderResult($variation, null, $gender));
        }

        if ($args) {
            if (! is_string($pattern)) {
                // On mobile, table can be accessed at the native layer when fetching
                // translations. If pattern is not a string here, table has not been accessed
                $pattern = FbtTable::access($pattern, $args, 0);
            }
            $allSubstitutions = array_merge(...array_map(function ($arg) {
                return $arg[FbtTable::ARG['SUBSTITUTION']] ?? [];
            }, $args));
            invariant($pattern !== null, 'Table access failed');
        }

        $patternHash = null;
        if (is_array($pattern)) {
            // [fbt_impressions]
            // When logging of string impressions is enabled, the string and its hash
            // are packaged in an array. We want to log the hash
            $patternString = $pattern[0];
            $patternHash = $pattern[1];
            // Append '1_' for appid's prepended to our i18n hash
            // (see intl_get_application_id)
            $stringID = '1_' . $patternHash;
            if (! empty(FbtQTOverrides::$overrides[$stringID])) {
                $patternString = FbtQTOverrides::$overrides[$stringID];
                FbtHooks::onTranslationOverride($patternHash);
            }
            FbtHooks::logImpression($patternHash);
        } elseif (is_string($pattern)) {
            $patternString = $pattern;
        } else {
            throw new FbtException(
                'Table access did not result in string: ' .
                ($pattern === null ? 'null' : json_encode($pattern)) .
                ', Type: ' .
                gettype($pattern)
            );
        }

        $cachedFbt = self::$_cachedFbtResults[$patternString] ?? null;
        $hasSubstitutions = FbtUtils::hasKeys($allSubstitutions);
        if ($cachedFbt && ! $hasSubstitutions) {
            return $cachedFbt;
        } else {
            $fbtContent = FbtUtils::substituteTokens($patternString, $allSubstitutions);
            $result = $this->_wrapContent($fbtContent, $patternString, $patternHash, $reporting);
            if (! $hasSubstitutions) {
                self::$_cachedFbtResults[$patternString] = $result;
            }

            return $result;
        }
    }

    /**
     * fbt::enum() takes an enum value and returns a tuple in the format:
     * [value, null]
     * @param $value - Example: "id1"
     * @param $range - Example: ["id1" => "groups", "id2" => "videos", ...]
     *
     * @throws \fbt\Exceptions\FbtException
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     */
    public static function _enum(string $value, array $range): array
    {
        if (FbtConfig::get('debug')) {
            invariant(isset($range[$value]), 'invalid value: %s', $value);
        }

        return FbtTableAccessor::getEnumResult($value);
    }

    /**
     * fbt::name() takes a `label`, `value`, and `gender` and
     * returns a tuple in the format:
     * [gender, {label: "replaces {label} in pattern string"}]
     * @param string $label - Example: "label"
     * @param mixed $value
     *   - E.g. 'replaces {label} in pattern'
     * @param int $gender - Example: "IntlVariations::GENDER_FEMALE"
     *
     * @return array
     *
     * @throws FbtException
     */
    public static function _name(string $label, $value, int $gender): array
    {
        $variation = IntlVariationResolverImpl::getGenderVariations($gender);
        $substitution = [];
        $substitution[$label] = $value;

        return FbtTableAccessor::getGenderResult($variation, $substitution, $gender);
    }

    /**
     * fbt::_subject() takes a gender value and returns a tuple in the format:
     * [variation, null]
     * @param int $value - Example: "16777216"
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public static function _subject(int $value): array
    {
        return FbtTableAccessor::getGenderResult(
            IntlVariationResolverImpl::getGenderVariations($value),
            null,
            $value
        );
    }

    /**
     * fbt::param() takes a `label` and `value` returns a tuple in the format:
     * [?variation, {label: "replaces {label} in pattern string"}]
     * @param string $label - Example: "label"
     * @param mixed $value
     *   - E.g. 'replaces {label} in pattern'
     * @param array $variations - Variation type and variation value (if explicitly provided)
     *   E.g.
     *   number: `[0]`, `[0, $count]`, or `[0, foo::someNumber() + 1]`
     *   gender: `[1, $someGender]`
     *
     * @return array
     * @throws FbtException
     */
    public static function _param(string $label, $value, array $variations = []): array
    {
        $substitution = [$label => $value];
        if ($variations) {
            if ($variations[0] === FbtRuntimeTypes::PARAM_VARIATION_TYPE['number']) {
                $number = count($variations) > 1 ? $variations[1] : $value;
                invariant(is_numeric($number), 'fbt::param expected number');

                $variation = IntlVariationResolverImpl::getNumberVariations($number); // this will throw if `number` is invalid
                if (is_numeric($value)) {
                    $substitution[$label] =
                        intlNumUtils::formatNumberWithThousandDelimiters($value);
                }

                return FbtTableAccessor::getNumberResult($variation, $substitution, $number);
            } elseif ($variations[0] === FbtRuntimeTypes::PARAM_VARIATION_TYPE['gender']) {
                $gender = $variations[1];
                invariant($gender != null, 'expected gender value');

                return FbtTableAccessor::getGenderResult(
                    IntlVariationResolverImpl::getGenderVariations($gender),
                    $substitution,
                    $gender
                );
            } else {
                invariant(false, 'Unknown invariant mask');
            }
        }

        return FbtTableAccessor::getSubstitution($substitution);
    }

    /**
     * fbt::_plural() takes a `count` and 2 optional params: `label` and `value`.
     * It returns a tuple in the format:
     * [?variation, {label: "replaces {label} in pattern string"}]
     * @param float $count - Example: 2
     * @param string|null $label
     *   - E.g. 'replaces {number} in pattern'
     * @param mixed|null $value
     *   - The value to use (instead of count) for replacing {label}
     *
     * @return array
     * @throws FbtException
     */
    public static function _plural(float $count, string $label = null, $value = null): array
    {
        $variation = IntlVariationResolverImpl::getNumberVariations($count);
        $substitution = [];
        if ($label) {
            if (is_numeric($value)) {
                $substitution[$label] = intlNumUtils::formatNumberWithThousandDelimiters($value);
            } else {
                $substitution[$label] = $value ?? intlNumUtils::formatNumberWithThousandDelimiters($count);
            }
        }

        return FbtTableAccessor::getNumberResult($variation, $substitution, $count);
    }

    /**
     * fbt::pronoun() takes a 'usage' string and a Gender::GENDER_CONST value and returns a tuple in the format:
     * [variations, null]
     * @param $usage - Example: FbtConstants::PRONOUN_USAGE['OBJECT'].
     * @param $gender - Example: Gender::GENDER_CONST['MALE_SINGULAR']
     * @param $options - Example: [ 'human' => 1 ]
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public static function _pronoun(string $usage, int $gender, array $options = []): array
    {
        invariant(
            $gender !== Gender::GENDER_CONST['NOT_A_PERSON'] || ! $options || empty($options['human']),
            'Gender cannot be Gender::GENDER_CONST[\'NOT_A_PERSON\'] if you set "human" to true'
        );
        $genderKey = JSFbtBuilder::getPronounGenderKey($usage, $gender);

        return FbtTableAccessor::getPronounResult($genderKey);
    }

    /**
     * @param string|array $fbtContent
     * @param string $patternString
     * @param string|null $patternHash
     * @param bool $reporting
     *
     * @return FbtResult|InlineFbtResult
     */
    private function _wrapContent($fbtContent, string $patternString, $patternHash, bool $reporting = true)
    {
        $contents = is_string($fbtContent) ? [$fbtContent] : $fbtContent;

        $inlineMode = FbtHooks::inlineMode();

        if ($reporting && $inlineMode && $inlineMode !== 'NO_INLINE') {
            return new InlineFbtResult(
                $contents,
                $inlineMode,
                $patternString,
                $patternHash
            );
        }

        return new FbtResult($contents);
    }
}
