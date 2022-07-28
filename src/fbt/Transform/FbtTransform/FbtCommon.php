<?php

namespace fbt\Transform\FbtTransform;

use fbt\Exceptions\FbtException;

use function fbt\invariant;

class FbtCommon
{
    /* @var array */
    public static $textToDesc = [];

    /**
     * @return void
     * @throws \fbt\Exceptions\FbtException
     */
    public static function init(array $opts = [])
    {
        if (! empty($opts['fbtCommon']) && is_array($opts['fbtCommon'])) {
            self::$textToDesc = array_merge(self::$textToDesc, $opts['fbtCommon']);
        }

        // js~php diff:
        if (! empty($opts['fbtCommonPath'])) {
            $array = explode('.', basename($opts['fbtCommonPath']));
            $extension = end($array);

            try {
                if ($extension === 'json') {
                    $fbtCommonData = json_decode(file_get_contents($opts['fbtCommonPath']), true);
                } else {
                    $fbtCommonData = require($opts['fbtCommonPath']);
                }
            } catch (\Throwable $e) {
                throw new FbtException($e->getMessage());
            }
            invariant(is_array($fbtCommonData), 'File content (' . $opts['fbtCommonPath'] . ') must be an array.');
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
