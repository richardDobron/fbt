<?php

namespace fbt\Transform\FbtTransform\Translate;

/**
 * Corresponds to IntlJSTranslatationDataEntry in Hack
 */
class TranslationData
{
    /** @var array<int, string> */
    public $tokens;
    /** @var array<int, int> */
    public $types;
    /**
     * [['translation' => string, 'id' => ?int, 'variations' => [index => int|string]], ...]
     * @var array
     */
    public $translations;
    /** @var string|null|false - false when not computed yet */
    private $_defaultTranslation = false;

    public function __construct(array $tokens, array $types, array $translations)
    {
        $this->tokens = $tokens;
        $this->types = $types;
        $this->translations = $translations;
    }

    public static function fromJSON(?array $json): ?TranslationData
    {
        if ($json === null) {
            // Hash key is logged to stderr in `processTranslations`
            return null;
        }

        return new TranslationData($json['tokens'] ?? [], $json['types'] ?? [], $json['translations'] ?? []);
    }

    public function hasTranslation(): bool
    {
        return count($this->translations) > 0;
    }

    /**
     * Makes a best effort attempt at finding the default translation.
     */
    public function getDefaultTranslation(TranslationConfig $config): ?string
    {
        if ($this->_defaultTranslation === false) {
            foreach ($this->translations as $trans) {
                $isDefault = true;
                foreach ($trans['variations'] ?? [] as $variation) {
                    if (! $config->isDefaultVariation($variation)) {
                        $isDefault = false;

                        break;
                    }
                }
                if ($isDefault) {
                    return $this->_defaultTranslation = $trans['translation'];
                }
            }
            $this->_defaultTranslation = null;
        }

        return $this->_defaultTranslation;
    }
}
