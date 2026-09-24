<?php

namespace fbt\Transform\FbtTransform\Translate;

use fbt\Exceptions\FbtException;

use function fbt\invariant;

use fbt\Transform\FbtTransform\FbtUtils;

/**
 * Given an FbtSite (source payload) and the relevant translations,
 * builds the corresponding translated payload
 */
class TranslationBuilder
{
    /** @var TranslationConfig */
    private $_config;
    /** @var FbtSite */
    private $_fbtSite;
    /**
     * Memoized constraint to translation maps per hash
     * @var array<string, array<string, string>>
     */
    private $_constraintMaps = [];
    /** @var bool */
    private $_hasTranslations;
    /** @var bool */
    private $_hasVCGenderVariation;
    /** @var bool */
    private $_inclHash;
    /** @var array<int, FbtSiteMetaEntry|null> */
    private $_metadata;
    /** @var string|array */
    private $_tableOrHash;
    /** @var array<string, int> */
    private $_tokenToMask = [];
    /**
     * Map from a string's hash to its translation payload.
     * If the translation is string type, it implies it was machine generated.
     * @var array<string, TranslationData|string|null>
     */
    private $_translations;
    /**
     * js~php diff: translations of the fallback locale
     * @var array<string, TranslationData|string|null>
     */
    private $_fallbackTranslations;

    /**
     * @param array $translations Hash of a string to its translation
     * @param TranslationConfig $config Configuration for variation defaults (number/gender)
     * @param FbtSite $fbtSite Representation of the <fbt> or fbt() to be translated
     * @param bool $inclHash Include hash/identifer in leaf of payloads
     * @param array $fallbackTranslations js~php diff: translations of the fallback locale
     *
     * @throws FbtException
     */
    public function __construct(
        array $translations,
        TranslationConfig $config,
        FbtSite $fbtSite,
        bool $inclHash,
        array $fallbackTranslations = []
    ) {
        $this->_translations = $translations;
        $this->_fallbackTranslations = $fallbackTranslations;
        $this->_config = $config;
        $this->_fbtSite = $fbtSite;
        $this->_metadata = $fbtSite->getMetadata();
        $this->_tableOrHash = $fbtSite->getTableOrHash();
        $this->_hasVCGenderVariation = $this->_findVCGenderVariation();
        $this->_hasTranslations = $this->_translationsExist();
        $this->_inclHash = $inclHash;

        // If a gender variation exists, add it to our table
        if ($this->_hasVCGenderVariation) {
            $this->_tableOrHash = ['*' => $this->_tableOrHash];
            array_unshift($this->_metadata, FbtSiteMetaEntry::wrap([
                'token' => IntlVariations::VIEWING_USER,
                'type' => IntlVariations::INTL_FBT_VARIATION_TYPE['GENDER'],
            ]));
        }

        foreach ($this->_metadata as $metadata) {
            if ($metadata !== null && $metadata->hasVariationMask()) {
                $token = $metadata->getToken();
                invariant(
                    $token !== null,
                    'Expect `token` to not be null as the metadata has variation mask.'
                );
                $this->_tokenToMask[$token] = $metadata->getVariationMask();
            }
        }
    }

    public function hasTranslations(): bool
    {
        return $this->_hasTranslations;
    }

    /**
     * @return string|array|null
     * @throws FbtException
     */
    public function build()
    {
        $table = $this->_buildRecursive($this->_tableOrHash);
        if ($this->_hasVCGenderVariation) {
            invariant(
                is_array($table) && ! array_key_exists(0, $table),
                'Expect `table` to not be a TranslationLeaf when ' .
                'the string has a hidden viewer context token.'
            );

            // This hidden key is checked during fbt runtime to signal that we
            // should access the first entry of our table with the viewer's gender
            return $table + ['__vcg' => 1];
        }

        return $table;
    }

    /**
     * @return TranslationData|string|null
     */
    private function _getTranslationData(string $hash)
    {
        // js~php diff: falls back to the translations of the fallback locale
        return $this->_translations[$hash] ?? $this->_fallbackTranslations[$hash] ?? null;
    }

    private function _translationsExist(): bool
    {
        foreach (array_keys($this->_fbtSite->getHashToLeaf()) as $hash) {
            $transData = $this->_getTranslationData((string)$hash);
            if (
                is_string($transData) ||
                ($transData instanceof TranslationData && $transData->hasTranslation())
            ) {
                // There is a translation or simple string for generated translation
                return true;
            }
        }

        return false;
    }

    /**
     * Inspect all translation variations for a hidden viewer context token
     */
    private function _findVCGenderVariation(): bool
    {
        foreach (array_keys($this->_fbtSite->getHashToLeaf()) as $hash) {
            $transData = $this->_getTranslationData((string)$hash);
            if (! ($transData instanceof TranslationData)) {
                continue;
            }

            foreach ($transData->tokens as $token) {
                if ($token === IntlVariations::VIEWING_USER) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Given a hash (or hash-table), return the translated text (or table of
     * texts).  If the hash (or hashes) do not have a translation, then the
     * original text will be used as the translation.
     *
     * If we should include the string hash then the method returns a vector with
     * [string, hash] so that the hash is available to the run-time logging code.
     *
     * @param string|array $hashOrTable
     * @param array $tokenConstraints
     * @param int $levelIdx
     *
     * @return string|array|null
     * @throws FbtException
     */
    private function _buildRecursive($hashOrTable, array $tokenConstraints = [], int $levelIdx = 0)
    {
        if (is_string($hashOrTable)) {
            return $this->_getLeafTranslation($hashOrTable, $tokenConstraints);
        }

        $table = [];
        foreach ($hashOrTable as $key => $branchOrLeaf) {
            $trans = $this->_buildRecursive($branchOrLeaf, $tokenConstraints, $levelIdx + 1);
            if (self::_shouldStore($trans)) {
                $table[$key] = $trans;
            }

            // This level will have metadata if it could potentially have variations.
            // Below, we fill the table with those variation entries.
            //
            // NOTE: A key of '_1' (EXACTLY_ONE) will be processed by the
            // buildRecursive call above, as its corresponding token constraint is
            // defaulted to '*'.  See _getConstraintMap for more details
            $metadata = $this->_metadata[$levelIdx] ?? null;
            if (
                $metadata !== null &&
                $metadata->hasVariationMask() &&
                (string)$key !== IntlVariations::EXACTLY_ONE
            ) {
                $mask = $metadata->getVariationMask();
                invariant(
                    $mask === IntlVariations::INTL_VARIATION_MASK['NUMBER'] ||
                    $mask === IntlVariations::INTL_VARIATION_MASK['GENDER'],
                    'Unknown variation mask: %s (%s)',
                    (string)$mask,
                    gettype($mask)
                );
                invariant(
                    IntlVariations::isValidValue((string)$key),
                    'Expect variation keys to be coercible to IntlVariationsEnum: current key=%s (%s)',
                    (string)$key,
                    gettype($key)
                );
                $token = $metadata->getToken();
                invariant(
                    $token !== null,
                    'Expect `token` to not be falsy when the metadata has a variation mask.'
                );
                foreach (self::_getTypesFromMask($mask) as $variationKey) {
                    $tokenConstraints[$token] = $variationKey;
                    $trans = $this->_buildRecursive($branchOrLeaf, $tokenConstraints, $levelIdx + 1);
                    if (self::_shouldStore($trans)) {
                        $table[$variationKey] = $trans;
                    }
                }
                unset($tokenConstraints[$token]);
            }
        }

        return $table;
    }

    /**
     * @return string|array|null
     * @throws FbtException
     */
    private function _getLeafTranslation(string $hash, array $tokenConstraints = [])
    {
        $transData = $this->_getTranslationData($hash);
        if (is_string($transData)) {
            // Fake translations are just simple strings.  There's no such thing as
            // variation support for these locales.  So if token constraints were
            // specified, return null and rely on runtime fallback to wildcard.
            // js~php diff: upstream checks the truthiness of an object, which is always truthy
            $translation = $tokenConstraints ? null : $transData;
        } elseif (FbtUtils::hasKeys($tokenConstraints)) {
            $translation = $this->getConstrainedTranslation($hash, $tokenConstraints);
        } else {
            // Real translations are TranslationData objects, so we call the
            // getDefaultTranslation() method to get the translation (we hope)
            $defaultTranslation = $transData ? $transData->getDefaultTranslation($this->_config) : null;

            // If no translation available, use the English source text
            $translation = $defaultTranslation ?? $this->_fbtSite->getHashToLeaf()[$hash]['text'];
        }

        // js~php diff: a missing translation isn't stored in the table
        if ($translation === null) {
            return null;
        }

        // Replace clear tokens with their token aliases
        $translation = FbtUtils::replaceClearTokensWithTokenAliases(
            $translation,
            $this->_fbtSite->getHashToTokenAliases()[$hash] ?? null
        );

        // Couple the string with a hash if it was marked as such.  We do this
        // when logging impressions or when using QuickTranslations.  The logging
        // is performed by `fbt::_(...)`
        return $this->_inclHash ? [$translation, $hash] : $translation;
    }

    /**
     * Given a hash and restraints on the token variations, retrieve the
     * appropriate translation for our map.  A null entry is a signal
     * not to add the translation to the map, because it's already in
     * the map via its fallback ('*') keys.
     *
     * @throws FbtException
     */
    public function getConstrainedTranslation(string $hash, array $tokenConstraints): ?string
    {
        $constraintKeys = [];
        foreach (array_keys($this->_tokenToMask) as $token) {
            $constraintKeys[] = [(string)$token, $tokenConstraints[$token] ?? '*'];
        }
        $constraintMap = $this->_getConstraintMap($hash);
        $aggregateKey = VariationConstraintUtils::buildConstraintKey($constraintKeys);
        $translation = $constraintMap[$aggregateKey] ?? null;
        if (! $translation) {
            return null;
        }

        foreach ($constraintKeys as $ii => [$token, $constraint]) {
            if ($constraint === '*') {
                continue;
            }

            // If any of the constraints share the same translation as the wildcard
            // (default) entry at this level, don't add an entry to the table.  They
            // will be in the table under the '*' key.
            $constraintKeys[$ii] = [$token, '*'];
            $wildKey = VariationConstraintUtils::buildConstraintKey($constraintKeys);
            $wildTranslation = $constraintMap[$wildKey] ?? null;
            if ($wildTranslation === $translation) {
                return null;
            }
            // Set the constraint back
            $constraintKeys[$ii] = [$token, $constraint];
        }

        return $translation;
    }

    /**
     * @throws FbtException
     */
    private function _insertConstraint(
        array $constraintKeys,
        array &$constraintMap,
        string $translation,
        int $defaultingLevel
    ): void {
        $aggregateKey = VariationConstraintUtils::buildConstraintKey($constraintKeys);
        if (! empty($constraintMap[$aggregateKey])) {
            throw new FbtException(
                'Unexpected duplicate key: ' .
                $aggregateKey .
                "\nOriginal: " .
                $constraintMap[$aggregateKey] .
                "\nNew " .
                $translation
            );
        }
        $constraintMap[$aggregateKey] = $translation;

        // Also include duplicate '*' entries if it is a default value
        for ($ii = $defaultingLevel; $ii < count($constraintKeys); $ii++) {
            [$token, $val] = $constraintKeys[$ii];
            if ($val !== '*' && $this->_config->isDefaultVariation($val)) {
                $constraintKeys[$ii] = [$token, '*'];
                $this->_insertConstraint($constraintKeys, $constraintMap, $translation, $ii + 1);
                $constraintKeys[$ii] = [$token, $val]; // return the value back
            }
        }
    }

    /**
     * Populates our variation constraint map.  The map is of all possible
     * variation combinations (serialized as a string) to the appropriate
     * translation.  For example, a PHP code like:
     *
     *   fbt('Hi ' . fbt::param('user', $viewer->name, ['gender' => $viewer->gender]) .
     *       ', would you like to play ' .
     *        fbt::param('count', $gameCount, ['number' => true]) .
     *        ' games of ' . fbt::enum($game, ['chess', 'backgammon', 'poker']) .
     *        '?', 'sample')
     *
     * will have variations for the 'user' and 'count' parameters.  Accounting for
     * all variations in a locale where we don't merge unknown gender into male
     * and we have the dual number variation, the map will have the following keys
     * mapping to the corresponding translation.
     *
     *  user%*:count%*  [default (unknown) - default (other) ]
     *  user%*:count%4  [default           - one             ]
     *  user%*:count%20 [default           - few             ]
     *  user%*:count%24 [default           - other           ]
     *  user%1:count%*  [male              - default (other) ]
     *  user%1:count%4  [male              - one             ]
     *  user%1:count%20 [male              - few             ]
     *  user%1:count%24 [male              - other           ]
     *  user%2:count%*  [female            - default (other) ]
     *  user%2:count%4  [female            - singular        ]
     *  user%2:count%20 [female            - few             ]
     *  user%2:count%24 [female            - other           ]
     *  user%3:count%*  [unknown gender    - default (other) ]
     *  user%3:count%4  [unknown gender    - singular        ]
     *  user%3:count%20 [unknown gender    - few             ]
     *  user%3:count%24 [unknown gender    - other           ]
     *
     *  Note we have duplicate translations in this map.  As an example, the
     *  following keys map to the same translation
     *    'user%*:count%*'  (default - default)
     *    'user%3:count%*'  (unknown - default)
     *    'user%3:count%24' (unknown - other)
     *
     *  These translations are deduped later in getConstrainedTranslation such
     *  that only the 'user%*:count%*' in our tree is in the JSON map.
     *
     * @return array<string, string>
     * @throws FbtException
     */
    private function _getConstraintMap(string $hash): array
    {
        if (isset($this->_constraintMaps[$hash])) {
            return $this->_constraintMaps[$hash];
        }

        $constraintMap = [];
        $transData = $this->_getTranslationData($hash);
        if ($transData === null || is_string($transData)) {
            // No translation? No constraints.
            return $this->_constraintMaps[$hash] = $constraintMap;
        }

        // For every possible variation combination, create a mapping to its
        // corresponding translation
        foreach ($transData->translations as $translation) {
            $constraints = [];
            foreach ($translation['variations'] ?? [] as $idx => $variation) {
                // We prune entries that contain non-default variations
                // for tokens we haven't specified.
                $token = $transData->tokens[(int)$idx];
                if (
                    // Token variation type not specified
                    empty($this->_tokenToMask[$token]) ||
                    // Translated variation type is different than token variation type
                    $this->_tokenToMask[$token] !== $transData->types[(int)$idx]
                ) {
                    // Only add default tokens we haven't specified.
                    if (! $this->_config->isDefaultVariation($variation)) {
                        continue 2;
                    }
                }
                $constraints[$token] = $variation;
            }

            // A note about fbt:plurals.  They can introduce global token
            // discrepancies between leaf nodes.  Singular translations don't have
            // number tokens, but their plural counterparts can (when showCount =
            // "ifMany" or "yes").  If we are dealing with the singular leaf of an
            // fbt:plural, since it has a unique hash, we can let it masquerade as
            // default: '*', since no such variation actually exists for a
            // non-existent token
            $constraintKeys = [];
            foreach (array_keys($this->_tokenToMask) as $k) {
                $constraintKeys[] = [(string)$k, ($constraints[$k] ?? null) ?: '*'];
            }
            $this->_insertConstraint($constraintKeys, $constraintMap, $translation['translation'], 0);
        }

        return $this->_constraintMaps[$hash] = $constraintMap;
    }

    /**
     * @param mixed $branch
     */
    private static function _shouldStore($branch): bool
    {
        return $branch !== null && (is_string($branch) || (is_array($branch) && FbtUtils::hasKeys($branch)));
    }

    /**
     * @return array<int, int>
     * @throws FbtException
     */
    private static function _getTypesFromMask(int $mask): array
    {
        $type = IntlVariations::getType($mask);
        if ($type === IntlVariations::INTL_VARIATION_MASK['NUMBER']) {
            return array_values(IntlVariations::INTL_NUMBER_VARIATIONS);
        }

        return [
            IntlVariations::INTL_GENDER_VARIATIONS['MALE'],
            IntlVariations::INTL_GENDER_VARIATIONS['FEMALE'],
            IntlVariations::INTL_GENDER_VARIATIONS['UNKNOWN'],
        ];
    }
}
