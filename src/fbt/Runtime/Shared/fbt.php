<?php

namespace fbt\Runtime\Shared;

use fbt\Exceptions\FbtException;
use fbt\FbtConfig;

use function fbt\invariant;

use fbt\Lib\FbtQTOverrides;
use fbt\Runtime\FbtRuntimeTypes;
use fbt\Runtime\FbtTable;
use fbt\Runtime\GenderConst;

class fbt
{
    /** @var array<string, FbtResultBase> */
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
     * @param array $options - options for runtime translation dictionary access.
     * hk stands for hash key which is used to look up the translated payload in
     * FbtTranslations. eo stands for extra options.
     *
     * @param bool $reporting - js~php diff: whether the result can be inlined
     *
     * @return FbtResultBase|mixed - result of the getFbtResult/getFbsResult hook
     * @throws FbtException
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     */
    public function _($inputTable, ?array $inputArgs, ?array $options = [], bool $reporting = true)
    {
        $options = $options ?? [];

        // Adapt the input payload to the translated table and arguments we expect:
        // the table is the English payload, and, by default, we look up the
        // translated payload via FbtTranslations
        $translatedInput = FbtHooks::getTranslatedInput([
            'table' => $inputTable,
            'args' => $inputArgs,
            'options' => $options,
        ]);
        $pattern = $translatedInput['table'];
        $args = $translatedInput['args'];

        // [fbt_impressions]
        // If this is a string literal (no tokens to substitute) then 'args' is empty
        // and the logic will skip the table traversal.

        // [table traversal]
        // At this point we assume that table is a hash (possibly nested) that we
        // need to traverse in order to pick the correct string, based on the
        // args that follow.
        $allSubstitutions = [];
        $tokens = [];

        if (is_array($pattern) && isset($pattern['__vcg'])) {
            $args = $args ?? [];
            $gender = FbtHooks::getViewerContext()->getGender();
            $variation = IntlVariationResolverImpl::getGenderVariations($gender);
            array_unshift($args, FbtTableAccessor::getGenderResult($variation, null, $gender));
        }

        if ($args) {
            if (! is_string($pattern)) {
                $pattern = FbtTable::access($pattern, $args, 0, $tokens);
            }

            $allSubstitutions = self::getAllSubstitutions($args);
            invariant($pattern !== null, 'Table access failed');
        }

        $patternHash = null;
        // js~php diff: a [pattern, hash] leaf is a list of two strings (other arrays are tables)
        if (self::isPatternWithHash($pattern)) {
            // [fbt_impressions]
            // When logging of string impressions is enabled, the string and its hash
            // are packaged in an array. We want to log the hash
            $patternString = $pattern[0];
            $patternHash = $pattern[1];
            // Append '1_' for appid's prepended to our i18n hash
            // (see intl_get_application_id)
            $stringID = '1_' . $patternHash;
            if (isset(FbtQTOverrides::$overrides[$stringID]) && FbtQTOverrides::$overrides[$stringID] !== '') {
                $patternString = FbtQTOverrides::$overrides[$stringID];
                FbtHooks::onTranslationOverride($patternHash);
            }
            $impressionOptions = [
                'inputTable' => $inputTable,
                'tokens' => $tokens,
            ];
            FbtHooks::logImpression($patternHash, $impressionOptions);
        } elseif (is_string($pattern)) {
            $patternString = $pattern;
        } else {
            throw new FbtException(
                'Table access did not result in string: ' .
                ($pattern === null ? 'undefined' : json_encode($pattern)) .
                ', Type: ' .
                gettype($pattern)
            );
        }

        // js~php diff: cached results are separated per runtime (fbt/fbs) and locale
        $cacheKey = static::class . "\0" . FbtHooks::locale() . "\0" . $patternString;
        // js~php diff: results that can be inlined depend on the inline mode (and the
        // callsite), so they are never cached
        $inlineMode = FbtHooks::inlineMode();
        $cacheable = ! $reporting || ! $inlineMode || $inlineMode === 'NO_INLINE';
        $cachedFbt = self::$_cachedFbtResults[$cacheKey] ?? null;
        $hasSubstitutions = self::_hasKeys($allSubstitutions);

        if ($cachedFbt && ! $hasSubstitutions && $cacheable) {
            return $cachedFbt;
        } else {
            $fbtContent = substituteTokens::substitute(
                $patternString,
                $allSubstitutions,
                FbtHooks::getErrorListener([
                    'translation' => $patternString,
                    'hash' => $patternHash,
                ])
            );
            $result = $this->_wrapContent(
                $fbtContent,
                $patternString,
                $patternHash,
                $options['eo'] ?? null,
                $reporting
            );
            if (! $hasSubstitutions && $cacheable) {
                self::$_cachedFbtResults[$cacheKey] = $result;
            }

            return $result;
        }
    }

    /**
     * Cached result of a pattern (upstream `fbt._getCachedFbt()`, for tests)
     *
     * @return FbtResultBase|mixed|null
     */
    public static function _getCachedFbt(string $patternString)
    {
        return self::$_cachedFbtResults[static::class . "\0" . FbtHooks::locale() . "\0" . $patternString] ?? null;
    }

    /**
     * @param mixed $pattern
     */
    private static function isPatternWithHash($pattern): bool
    {
        return is_array($pattern)
            && count($pattern) === 2
            && is_string($pattern[0] ?? null)
            && is_string($pattern[1] ?? null);
    }

    /**
     * @throws FbtException
     */
    private static function getAllSubstitutions(array $args): array
    {
        $allSubstitutions = [];
        foreach ($args as $arg) {
            $substitution = $arg[FbtTable::ARG['SUBSTITUTION']] ?? null;
            if (! $substitution) {
                continue;
            }

            foreach ($substitution as $tokenName => $value) {
                invariant(
                    ! isset($allSubstitutions[$tokenName]),
                    'Cannot register a substitution with token=`%s` more than once',
                    $tokenName
                );
                $allSubstitutions[$tokenName] = $value;
            }
        }

        return $allSubstitutions;
    }

    /**
     * _hasKeys takes an array and returns whether it has any keys. It purposefully
     * avoids creating the temporary arrays incurred by calling array_keys($o)
     */
    private static function _hasKeys(array $o): bool
    {
        foreach ($o as $_) {
            return true;
        }

        return false;
    }

    /**
     * fbt::enum() takes an enum value and returns a tuple in the format:
     * [value, null]
     * @param string|int $value - Example: "id1"
     * @param array $range - Example: ["id1" => "groups", "id2" => "videos", ...]
     *
     * @throws \fbt\Exceptions\FbtException
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     */
    public static function _enum($value, array $range): array
    {
        if (FbtConfig::get('debug')) {
            invariant(array_key_exists($value, $range), 'invalid value: %s', $value);
        }

        return FbtTableAccessor::getEnumResult($value);
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
                // js~php diff: numeric strings are accepted as numbers
                invariant(is_numeric($number), 'fbt::param expected number');
                $number = +$number;

                $variation = IntlVariationResolverImpl::getNumberVariations($number); // this will throw if `number` is invalid
                if (is_int($value) || is_float($value)) {
                    $substitution[$label] =
                        intlNumUtils::formatNumberWithThousandDelimiters($value);
                }

                return FbtTableAccessor::getNumberResult($variation, $substitution, $number);
            } elseif ($variations[0] === FbtRuntimeTypes::PARAM_VARIATION_TYPE['gender']) {
                $gender = $variations[1] ?? null;
                invariant($gender !== null, 'expected gender value');

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
     * fbt::_implicitParam() behaves like fbt::_param()
     *
     * @param string $label
     * @param mixed $value
     * @param array $variations
     *
     * @return array
     * @throws FbtException
     */
    public static function _implicitParam(string $label, $value, array $variations = []): array
    {
        return static::_param($label, $value, $variations);
    }

    /**
     * fbt::_plural() takes a `count` and 2 optional params: `label` and `value`.
     * It returns a tuple in the format:
     * [?variation, {label: "replaces {label} in pattern string"}]
     * @param float|int|string $count - Example: 2
     * @param string|null $label
     *   - E.g. 'replaces {number} in pattern'
     * @param mixed|null $value
     *   - The value to use (instead of count) for replacing {label}
     *
     * @return array
     * @throws FbtException
     */
    public static function _plural($count, ?string $label = null, $value = null): array
    {
        // js~php diff: numeric strings are accepted as numbers
        invariant(is_numeric($count), 'fbt::plural expected number');
        $count = +$count;

        $variation = IntlVariationResolverImpl::getNumberVariations($count);
        $substitution = [];
        // js~php diff: JS truthiness ("0" is truthy)
        if ($label !== null && $label !== '') {
            if (is_int($value) || is_float($value)) {
                $substitution[$label] = intlNumUtils::formatNumberWithThousandDelimiters($value);
            } else {
                $substitution[$label] = $value !== null && $value !== '' && $value !== false
                    ? $value
                    : intlNumUtils::formatNumberWithThousandDelimiters($count);
            }
        }

        return FbtTableAccessor::getNumberResult($variation, $substitution, $count);
    }

    /**
     * fbt::pronoun() takes a 'usage' string and a GenderConst value and returns a tuple in the format:
     * [variations, null]
     * @param int|string $usage - Example: FbtRuntimeTypes::VALID_PRONOUN_USAGES_TYPE['object'].
     * @param int $gender - Example: GenderConst::MALE_SINGULAR
     * @param array|null $options - Example: [ 'human' => 1 ]
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public static function _pronoun($usage, int $gender, ?array $options = null): array
    {
        invariant(
            $gender !== GenderConst::NOT_A_PERSON || ! $options || empty($options['human']),
            'Gender cannot be GenderConst::NOT_A_PERSON if you set "human" to true'
        );

        // js~php diff: usage names are accepted too
        if (is_string($usage) && isset(FbtRuntimeTypes::VALID_PRONOUN_USAGES_TYPE[$usage])) {
            $usage = FbtRuntimeTypes::VALID_PRONOUN_USAGES_TYPE[$usage];
        }

        $genderKey = self::getPronounGenderKey((int)$usage, $gender);

        return FbtTableAccessor::getPronounResult($genderKey);
    }

    /**
     * Must match implementation from JSFbtBuilder::getPronounGenderKey()
     */
    private static function getPronounGenderKey(int $usage, int $gender): int
    {
        $validPronounUsages = FbtRuntimeTypes::VALID_PRONOUN_USAGES_TYPE;

        switch ($gender) {
            case GenderConst::NOT_A_PERSON:
                return $usage === $validPronounUsages['object'] ||
                    $usage === $validPronounUsages['reflexive']
                    ? GenderConst::NOT_A_PERSON
                    : GenderConst::UNKNOWN_PLURAL;

            case GenderConst::FEMALE_SINGULAR:
            case GenderConst::FEMALE_SINGULAR_GUESS:
                return GenderConst::FEMALE_SINGULAR;

            case GenderConst::MALE_SINGULAR:
            case GenderConst::MALE_SINGULAR_GUESS:
                return GenderConst::MALE_SINGULAR;

            case GenderConst::MIXED_UNKNOWN:
            case GenderConst::FEMALE_PLURAL:
            case GenderConst::MALE_PLURAL:
            case GenderConst::NEUTER_PLURAL:
            case GenderConst::UNKNOWN_PLURAL:
                return GenderConst::UNKNOWN_PLURAL;

            case GenderConst::NEUTER_SINGULAR:
            case GenderConst::UNKNOWN_SINGULAR:
                return $usage === $validPronounUsages['reflexive']
                    ? GenderConst::NOT_A_PERSON
                    : GenderConst::UNKNOWN_PLURAL;
        }

        // Mirrors the behavior of :fbt:pronoun when an unknown gender value is given.
        return GenderConst::NOT_A_PERSON;
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
     * @param string|array $fbtContent
     * @param string $translation
     * @param string|null $hash
     * @param array|null $extraOptions
     * @param bool $reporting
     *
     * @return FbtResultBase|mixed
     */
    protected function _wrapContent(
        $fbtContent,
        string $translation,
        ?string $hash,
        ?array $extraOptions = null,
        bool $reporting = true
    ) {
        $contents = is_string($fbtContent) ? [$fbtContent] : $fbtContent;
        $errorListener = FbtHooks::getErrorListener([
            'translation' => $translation,
            'hash' => $hash,
        ]);

        return FbtHooks::getFbtResult([
            'contents' => $contents,
            'errorListener' => $errorListener,
            'extraOptions' => $extraOptions,
            'patternHash' => $hash,
            'patternString' => $translation,
            'reporting' => $reporting,
        ]);
    }

    /**
     * @param mixed $value
     */
    public static function isFbtInstance($value): bool
    {
        return $value instanceof FbtResultBase;
    }

    /**
     * @internal
     */
    public static function _purgeCache(): void
    {
        self::$_cachedFbtResults = [];
    }
}
