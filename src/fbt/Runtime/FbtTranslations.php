<?php

namespace fbt\Runtime;

use fbt\Exceptions\FbtInvalidConfigurationException;
use fbt\FbtConfig;
use fbt\Runtime\Shared\FbtHooks;

class FbtTranslations
{
    public const DEFAULT_SRC_LOCALE = 'en_US';
    /** @var array */
    public static $translatedFbts = [];

    /**
     * @param array{table: string|array, args: array|null, options: array|null} $input
     *
     * @return array{table: string|array, args: array|null}|null
     * @throws FbtInvalidConfigurationException
     */
    public static function getTranslatedInput(array $input): ?array
    {
        $args = $input['args'] ?? null;
        $hashKey = $input['options']['hk'] ?? null;

        $locale = FbtHooks::locale();

        $table = self::$translatedFbts[$locale] ?? null;

        if (FbtConfig::get('debug')) {
            if (! $table && $locale !== self::DEFAULT_SRC_LOCALE) {
                trigger_error('Translations have not been provided', E_USER_WARNING);
            }
        }

        if ($hashKey === null || ! isset($table[$hashKey])) {
            return null;
        }

        return [
            'table' => $table[$hashKey],
            'args' => $args,
        ];
    }

    /**
     * @return void
     */
    public static function registerTranslations(array $translations): void
    {
        Shared\fbt::_purgeCache();
        self::$translatedFbts = $translations;
    }

    public static function getRegisteredTranslations(): array
    {
        return self::$translatedFbts;
    }

    /**
     * @return void
     */
    public static function mergeTranslations(array $newTranslations): void
    {
        Shared\fbt::_purgeCache();
        foreach (array_keys($newTranslations) as $locale) {
            self::$translatedFbts[$locale] = array_replace(
                self::$translatedFbts[$locale] ?? [],
                $newTranslations[$locale]
            );
        }
    }
}
