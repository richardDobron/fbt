<?php

namespace fbt\Transform\FbtTransform\Translate;

class TranslationData
{
    public function __construct(
        $tokens,
        $types,
        $translations // [{translation: "...", variations:[...], ?id: "..."}]
    ) {
        $this->tokens = $tokens;
        $this->types = $types;
        $this->translations = $translations;
    }

    public static function fromJSON(array $json): TranslationData
    {
        return new TranslationData($json['tokens'], $json['types'], $json['translations']);
    }

    public static function deserialize(string $jsonStr)
    {
        self::fromJSON(json_decode($jsonStr));
    }

    public function hasTranslation(): bool
    {
        return count($this->translations) > 0;
    }

    // Makes a best effort attempt at finding the default translation.
    public function getDefaultTranslation($config)
    {
        if (empty($this->_defaultTranslation)) {
            for ($i = 0; $i < count($this->translations); ++$i) {
                $trans = $this->translations[$i];
                $isDefault = true;
                foreach ($trans['variations'] as $v) {
                    if (! $config->isDefaultVariation($v)) {
                        $isDefault = false;

                        break;
                    }
                }
                if ($isDefault) {
                    return ($this->_defaultTranslation = $trans['translation']);
                }
            }
            $this->_defaultTranslation = null;
        }

        return $this->_defaultTranslation;
    }
}
