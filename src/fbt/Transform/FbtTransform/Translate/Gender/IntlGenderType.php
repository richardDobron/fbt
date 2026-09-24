<?php

namespace fbt\Transform\FbtTransform\Translate\Gender;

use fbt\Transform\FbtTransform\Translate\FBLocaleToLang;

class IntlGenderType
{
    public const MERGED_LOCALES = [
        "ar_AR" => 1,
        "ks_IN" => 1,
        "lv_LV" => 1,
        "ps_AF" => 1,
        "qk_DZ" => 1,
        "qs_DE" => 1,
        "qv_IT" => 1,
        "sq_AL" => 1,
        "ti_ET" => 1,
    ];

    public const MERGED_LANGS = [
        "ar" => 1,
        "ks" => 1,
        "lv" => 1,
        "ps" => 1,
        "kab" => 1,
        "dsb" => 1,
        "vec" => 1,
        "sq" => 1,
        "ti" => 1,
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
