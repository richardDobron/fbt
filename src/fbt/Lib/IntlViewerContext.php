<?php

namespace fbt\Lib;

use fbt\FbtConfig;
use fbt\Transform\FbtTransform\Translate\IntlVariations;

class IntlViewerContext implements IntlViewerContextInterface
{
    /** @var null|string */
    private static $locale = null;
    /** @var int */
    private static $gender = IntlVariations::GENDER_UNKNOWN;

    public function getLocale(): string
    {
        return self::$locale ?? FbtConfig::get('locale');
    }

    public function setLocale(string $locale): void
    {
        self::$locale = $locale;
    }

    public static function setGender(int $gender): void
    {
        self::$gender = $gender;
    }

    public function getGender(): int
    {
        return self::$gender;
    }
}
