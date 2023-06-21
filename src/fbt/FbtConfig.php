<?php

namespace fbt;

use fbt\Exceptions\FbtInvalidConfigurationException;

class FbtConfig
{
    /** @var array */
    protected static $config = [
        /*
         * Project to which the text belongs.
         */
        'project' => 'website app',

        /*
         * Default text author name.
         */
        'author' => null,

        /*
         * Logging of string impressions.
         */
        'logger' => false,

        /*
         * Collect fbt instances from the source and store them in a database.
         */
        'collectFbt' => true,

        /*
         * Viewer Context class name.
         */
        'viewerContext' => \fbt\Lib\IntlViewerContext::class,

        /*
         * User locale.
         */
        'locale' => 'en_US',

        /*
         * Hash module.
         * md5 / tiger
         */
        'hash_module' => 'md5',

        /*
         * Hash digest for md5 hash.
         * hex / base64
         */
        'md5_digest' => 'hex',

        /*
         * Cache storage path for generated translations & source strings.
         */
        'path' => null,

        /*
         * Pretty print source strings in a JSON file.
         */
        'prettyPrint' => true,

        /*
         * Common string's, e.g. [['text' => 'desc'], ...].
         */
        'fbtCommon' => [],

        /*
         * Path to the common string's module.
         */
        'fbtCommonPath' => null,

        /*
         * Driver for storage.
         */
        'driver' => 'json',

        /*
         * Debug.
         */
        'debug' => false,
    ];

    /**
     * @param string $key
     *
     * @return mixed
     * @throws FbtInvalidConfigurationException
     */
    public static function get(string $key)
    {
        if (! array_key_exists($key, self::$config)) {
            throw new FbtInvalidConfigurationException('Invalid config key ' . $key);
        }

        return static::$config[$key];
    }

    /**
     * @param string $key
     * @param mixed $value
     *
     * @return void
     * @throws FbtInvalidConfigurationException
     */
    public static function set(string $key, $value): void
    {
        if (! array_key_exists($key, self::$config)) {
            throw new FbtInvalidConfigurationException('Invalid config key ' . $key);
        }

        static::$config[$key] = $value;
    }

    /**
     * @return void
     * @throws FbtInvalidConfigurationException
     */
    public static function setMultiple(array $config): void
    {
        foreach ($config as $key => $value) {
            self::set($key, $value);
        }
    }
}
