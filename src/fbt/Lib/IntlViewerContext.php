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

    /**
     * @param string $locale
     * @return void
     */
    public function setLocale(string $locale)
    {
        self::$locale = $locale;
    }

    /**
     * @param int $gender
     * @return void
     */
    public static function setGender(int $gender)
    {
        self::$gender = $gender;
    }

    public function getGender(): int
    {
        return self::$gender;
    }
}
