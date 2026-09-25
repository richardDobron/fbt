<?php

namespace fbt\Transform\FbtTransform;

use fbt\Exceptions\FbtException;

use function fbt\invariant;

class FbtCommon
{
    /* @var array */
    public static $textToDesc = [];
    /**
     * js~php diff: loaded modules (the equivalent of the cache of `require()`)
     * @var array<string, array>
     */
    private static $modules = [];

    /**
     * @return void
     * @throws \fbt\Exceptions\FbtException
     */
    public static function init(array $opts = []): void
    {
        if (! empty($opts['fbtCommon']) && is_array($opts['fbtCommon'])) {
            self::$textToDesc = array_merge(self::$textToDesc, $opts['fbtCommon']);
        }

        // js~php diff: the module is a JSON file, or a PHP file returning an array
        if (! empty($opts['fbtCommonPath'])) {
            $fbtCommonData = self::$modules[$opts['fbtCommonPath']] ?? null;
            if ($fbtCommonData === null) {
                $array = explode('.', basename($opts['fbtCommonPath']));
                $extension = end($array);

                try {
                    if ($extension === 'json') {
                        $fbtCommonData = json_decode(file_get_contents($opts['fbtCommonPath']), true);
                    } else {
                        $fbtCommonData = require($opts['fbtCommonPath']);
                    }
                } catch (\Throwable $e) {
                    throw new FbtException(
                        $e->getMessage() .
                        "\nopts.fbtCommonPath: " . $opts['fbtCommonPath'] .
                        "\nPlease double check your fbtCommonPath setting."
                    );
                }
                invariant(is_array($fbtCommonData), 'File content (' . $opts['fbtCommonPath'] . ') must be an array.');
                self::$modules[$opts['fbtCommonPath']] = $fbtCommonData;
            }
            self::$textToDesc = array_merge(self::$textToDesc, $fbtCommonData);
        }
    }

    /**
     * @param string $text
     * @return string|null
     */
    public static function getDesc(string $text): ?string
    {
        return self::$textToDesc[$text] ?? null;
    }

    public static function getUnknownCommonStringErrorMessage(string $moduleName, string $text): string
    {
        return "Unknown string \"$text\" for <$moduleName common=\"true\">";
    }
}
