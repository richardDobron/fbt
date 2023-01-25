<?php

namespace fbt\Transform\FbtTransform\Translate\Gender;

use fbt\Transform\FbtTransform\Translate\FBLocaleToLang;

class IntlGenderType
{
    const MERGED_LOCALES = [
        "ht_HT" => 1,
        "lv_LV" => 1,
        "ar_AR" => 1,
        "ks_IN" => 1,
    ];

    const MERGED_LANGS = [
        "ht" => 1,
        "lv" => 1,
        "ar" => 1,
        "ks" => 1,
    ];

    /**
     * @param string $lang
     * @return IntlDefaultGenderType|IntlMergedUnknownGenderType
     */
    public static function forLanguage(string $lang)
    {
        if (array_key_exists($lang, self::MERGED_LANGS)) {
            return new IntlMergedUnknownGenderType();
        }

        return new IntlDefaultGenderType();
    }

    /**
     * @param string $locale
     * @return IntlDefaultGenderType|IntlMergedUnknownGenderType
     */
    public static function forLocale(string $locale)
    {
        if (array_key_exists($locale, self::MERGED_LOCALES)) {
            return new IntlMergedUnknownGenderType();
        }

        return IntlGenderType::forLanguage(FBLocaleToLang::get($locale));
    }
}
