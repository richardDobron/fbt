<?php

namespace fbt\Runtime;

use fbt\FbtConfig;
use fbt\Runtime\Shared\FbtHooks;

class FbtTranslations
{
    public const DEFAULT_SRC_LOCALE = 'en_US';
    /** @var array */
    public static $translatedFbts = [];

    /**
     * @param array|string $inputTable
     * @param $args
     * @param $options
     *
     * @return array|null
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     */
    public static function getTranslatedInput($inputTable, $args, $options): ?array
    {
        $hashKey = $options['hk'] ?? null;

        $locale = FbtHooks::locale();

        $table = self::$translatedFbts[$locale] ?? null;

        if (FbtConfig::get('debug')) {
            if (! $table && $locale !== self::DEFAULT_SRC_LOCALE) {
                trigger_error('Translations have not been provided', E_USER_WARNING);
            }
        }

        if ($hashKey == null || empty($table[$hashKey])) {
            return null;
        }

        return [
            $table[$hashKey],
            $args,
        ];
    }

    /**
     * @return void
     */
    public static function registerTranslations(array $translations)
    {
        self::$translatedFbts = $translations;
    }

    public static function getRegisteredTranslations(): array
    {
        return self::$translatedFbts;
    }

    /**
     * @return void
     */
    public static function mergeTranslations(array $newTranslations)
    {
        foreach (array_keys($newTranslations) as $locale) {
            self::$translatedFbts[$locale] = array_merge(
                self::$translatedFbts[$locale] ?? [],
                $newTranslations[$locale]
            );
        }
    }
}
