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
    /** @var TranslationData[] */
    private $_translations;
    /** @var TranslationConfig */
    private $_config;
    /** @var FbtSite */
    private $_fbtSite;
    /** @var array */
    private $_metadata;
    /** @var bool */
    private $_hasVCGenderVariation;
    /** @var bool */
    private $_hasTranslations;
    /** @var bool */
    private $_inclHash;
    /** @var array|string */
    private $_tableOrHash;
    /** @var array */
    private $_tokenMasks;

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    public function __construct(
        array $translations, // hash/id => translation (TranslationData | string)
        TranslationConfig $config, // Configuration for variation defaults (number/gender)
        FbtSite $fbtSite, // fbtSite to translate
        bool $inclHash // include hash/identifer in leaf of payloads
    ) {
        $this->_translations = $translations;
        $this->_config = $config;
        $this->_fbtSite = $fbtSite;
        $this->_tokenMasks = []; // token => mask
        $this->_metadata = $fbtSite->getMetadata(); // [{token: ..., mask: ...}, ...]
        $this->_tableOrHash = $fbtSite->getTableOrHash();
        $this->_hasVCGenderVariation = $this->_findVCGenderVariation();
        $this->_hasTranslations = $this->_translationsExist();
        $this->_inclHash = $inclHash;
        self::$_mem = [];

        // If a gender variation exists, add it to our table
        if ($this->_hasVCGenderVariation) {
            $this->_tableOrHash = ['*' => $this->_tableOrHash];
            array_unshift(
                $this->_metadata,
                FbtSiteMetaEntry::wrap([
                    'token' => IntlVariations::VIEWING_USER,
                    'mask' => IntlVariations::INTL_VARIATION_MASK['GENDER'],
                ])
            );
        }

        for ($ii = 0; $ii < count($this->_metadata); ++$ii) {
            $metadata = $this->_metadata[$ii];
            if ($metadata !== null && $metadata->hasVariationMask()) {
                $this->_tokenMasks[$metadata->getToken()] = $metadata->getVariationMask();
            }
        }
    }

    public function hasTranslations(): bool
    {
        return $this->_hasTranslations;
    }

    public function build()
    {
        $table = $this->_buildRecursive($this->_tableOrHash);
        if ($this->_hasVCGenderVariation) {
            // This hidden key is checked during JS fbt runtime to signal that we
            // should access the first entry of our table with the viewer's gender
            $table['__vcg'] = 1;
        }

        return $table;
    }

    private function _translationsExist(): bool
    {
        foreach ($this->_fbtSite->getHashToText() as $hash) {
            $transData = $this->_translations[$hash] ?? null;
            if (
                ! ($transData instanceof TranslationData) ||
                $transData->hasTranslation()
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
        foreach (array_keys($this->_fbtSite->getHashToText()) as $hash) {
            $transData = $this->_translations[$hash] ?? null;
            if (! ($transData instanceof TranslationData)) {
                continue;
            }

            $tokens = $transData->tokens;
            foreach ($tokens as $token) {
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
     * @return array|TranslationData|string|null
     *
     * @throws FbtException
     */
    private function _buildRecursive(
        $hashOrTable,
        array $tokenConstraints = [], // token_name => variation constraint
        int $levelIdx = 0
    ) {
        if (is_string($hashOrTable)) {
            return $this->_getLeafTranslation($hashOrTable, $tokenConstraints);
        }

        $table = [];
        foreach ($hashOrTable as $key => $branchOrLeaf) {
            $trans = $this->_buildRecursive(
                $branchOrLeaf,
                $tokenConstraints,
                $levelIdx + 1
            );
            if (shouldStore($trans)) {
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
                $key !== IntlVariations::EXACTLY_ONE
            ) {
                $mask = $metadata->getVariationMask();
                invariant(
                    $mask === IntlVariations::INTL_VARIATION_MASK['NUMBER'] || $mask === IntlVariations::INTL_VARIATION_MASK['GENDER'],
                    'Unknown variation mask'
                );
                invariant(
                    IntlVariations::isValidValue($key),
                    'We expect variation value keys for variations'
                );
                $token = $metadata->getToken();
                $variationCandidates = getTypesFromMask($mask);
                foreach ($variationCandidates as $variationKey) {
                    $tokenConstraints[$token] = $variationKey;
                    $trans = $this->_buildRecursive(
                        $branchOrLeaf,
                        $tokenConstraints,
                        $levelIdx + 1
                    );
                    if (shouldStore($trans)) {
                        $table[$variationKey] = $trans;
                    }
                }
                unset($tokenConstraints[$token]);
            }

            // js~php diff
            // @see https://stackoverflow.com/questions/5525795/does-javascript-guarantee-object-property-order
            uksort($table, function ($a, $b) {
                return is_int($b) - is_int($a) ?: strnatcmp($a, $b);
            });
        }

        return $table;
    }

    /**
     * @param string $hash
     * @param array $tokenConstraints
     *
     * @return string|array|TranslationData|null
     */
    private function _getLeafTranslation(
        string $hash, // string
        array $tokenConstraints // {string: string}: token => constraint
    ) {
        $transData = $this->_translations[$hash] ?? null;
        if (is_string($transData)) {
            // Fake translations are just simple strings.  There's no such thing as
            // variation support for these locales.  So if token constraints were
            // specified, return null and rely on runtime fallback to wildcard.
            $translation = $tokenConstraints ? null : $transData;
        } else {
            // Real translations are TranslationData objects, so we call the
            // getDefaultTranslation() method to get the translation (we hope), or use
            // original text if no translation exist.
            $source = $this->_fbtSite->getHashToText()[$hash];
            $defTranslation = $transData ? $transData->getDefaultTranslation($this->_config) : null;
            $translation = FbtUtils::hasKeys($tokenConstraints)
                ? $this->getConstrainedTranslation($hash, $tokenConstraints)
                : // If no translation available, use the English source text
                $defTranslation ?? $source;
        }

        // fbt: disable null translation for variation
        if (! $translation) {
            return null;
        }

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
     */
    public function getConstrainedTranslation(
        string $hash, // string
        array $tokenConstraints // dict<string, string> : token => constraint
    ) {
        $constraintKeys = [];
        foreach ($this->_tokenMasks as $token => $mask) {
            $val = $tokenConstraints[$token] ?? '*';
            $constraintKeys[] = [$token, $val];
        }
        $constraintMap = $this->_getConstraintMap($hash);
        $aggregateKey = buildConstraintKey($constraintKeys);
        $translation = $constraintMap[$aggregateKey] ?? null;
        if (! $translation) {
            return null;
        }
        for ($ii = 0; $ii < count($constraintKeys); ++$ii) {
            list($token, $constraint) = $constraintKeys[$ii];
            if ($constraint === '*') {
                continue;
            }

            // If any of the constraints share the same translation as the wildcard
            // (default) entry at this level, don't add an entry to the table.  They
            // will be in the table under the '*' key.
            $constraintKeys[$ii] = [$token, '*'];
            $wildKey = buildConstraintKey($constraintKeys);
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
     * Populates our variation constraint map.  The map is of all possible
     * variation combinations (serialized as a string) to the appropriate
     * translation.  For example, JavaScript like:
     *
     *   fbt('Hi ' . fbt::param('user', $viewer->name, ['gender' => $viewer->gender]) .
     *       ', would you like to play ' .
     *        fbt::param('count', $gameCount, ['number' => true]) .
     *        ' games of ' . fbt::enum($game, ['chess','backgammon','poker']) .
     *        '?  Click ' . fbt::param('link', createElement('a', ...)), 'sample'),
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
     *  that only the 'user%*:count%*' in our tree is in the JSON map.  i.e.
     *
     *  {
     *    // No unknown gender entry exists at this level - we rely on fallback
     *    '*' => {
     *      // no plural entry exists at this level
     *      '*' => {translation},
     *      ...
     *
     *    },
     *    ...
     *  }
     */

    // Yes this is hand-rolled memoization :(
    // TODO: T37795723 - Pull in a lightweight (not bloated) memoization library
    /** @var array */
    private static $_mem;

    private function _getConstraintMap($hash)
    {
        if (array_key_exists($hash, self::$_mem)) {
            return self::$_mem[$hash];
        }

        $constraintMap = [];
        $transData = $this->_translations[$hash] ?? null;
        if (! $transData) {
            // No translation? No constraints.
            return (self::$_mem[$hash] = $constraintMap);
        }

        // For every possible variation combination, create a mapping to its
        // corresponding translation
        foreach ($transData->translations as $translation) {
            $constraints = [];
            foreach ($translation['variations'] as $idx => $variation) {
                // We prune entries that contain non-default variations
                // for tokens we haven't specified.
                $token = $transData->tokens[$idx];
                if (
                    // Token variation type not specified
                    empty($this->_tokenMasks[$token]) ||
                    // Translated variation type is different than token variation type
                    $this->_tokenMasks[$token] !== $transData->types[$idx]
                ) {
                    // Only add default tokens we haven't specified.
                    if (! $this->_config->isDefaultVariation($variation)) {
                        return;
                    }
                }
                $constraints[$token] = $variation;
            }
            // A note about fbt:plurals.  They can introduce global token
            // discrepancies between leaf nodes.  Singular translations don't have
            // number tokens, but their plural counterparts can (when showCount =
            // "ifMany" or "yes").  If we are dealing with the singular leaf of an
            // fbt:plural, since it has a unique hash, we can $it masquerade as
            // default: '*', since no such variation actually exists for a
            // non-existent token
            $constraintKeys = [];
            foreach ($this->_tokenMasks as $k => $mask) {
                $constraintKeys[] = [$k, $constraints[$k] ?? '*'];
            }
            $this->_insertConstraint(
                $constraintKeys,
                $constraintMap,
                $translation['translation'],
                0
            );
        }

        return self::$_mem[$hash] = $constraintMap;
    }

    /**
     * @throws FbtException
     */
    private function _insertConstraint(
        array $keys, // [[token, constraint]]
        &$constraintMap, // {key: translation}
        string $translation, // string
        int $defaultingLevel // int
    ) {
        $aggregateKey = buildConstraintKey($keys);
        if (isset($constraintMap[$aggregateKey])) {
            throw new FbtException(
                'Unexpected duplicate key: ' .
                $aggregateKey .
                "\nOriginal: " .
                $constraintMap[$aggregateKey] .
                "\nNew: " .
                $translation
            );
        }
        $constraintMap[$aggregateKey] = $translation;

        // Also include duplicate '*' entries if it is a default value
        for ($ii = $defaultingLevel; $ii < count($keys); $ii++) {
            list($tok, $val) = $keys[$ii];
            if ($val !== '*' && $this->_config->isDefaultVariation($val)) {
                $keys[$ii] = [$tok, '*'];
                $this->_insertConstraint($keys, $constraintMap, $translation, $ii + 1);
                $keys[$ii] = [$tok, $val]; // return the value back
            }
        }
    }
}

function shouldStore($branch): bool
{
    return $branch !== null && (is_string($branch) || FbtUtils::hasKeys($branch));
}

/**
 * Build the aggregate key with which we access the constraint map.  The
 * constraint map maps the given constraints to the appropriate translation
 */
function buildConstraintKey(
    array $keys // [[token, constraint]]
): string {
    return implode(':', array_map(function ($kv) {
        return $kv[0] . '%' . $kv[1];
    }, $keys));
}

/**
 * @throws \fbt\Exceptions\FbtException
 */
function getTypesFromMask($mask): array
{
    $type = IntlVariations::getType($mask);
    if ($type === IntlVariations::INTL_VARIATION_MASK['NUMBER']) {
        return array_values(IntlVariations::INTL_NUMBER_VARIATIONS);
    } else {
        $gender = IntlVariations::INTL_GENDER_VARIATIONS;

        return [
            $gender['MALE'],
            $gender['FEMALE'],
            $gender['UNKNOWN'],
        ];
    }
}
