<?php

namespace fbt\Transform\FbtTransform;

use function fbt\invariant;
use function fbt\unsignedRightShift;

class fbtHash
{
    const BASE_N_SYMBOLS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    // Compute the baseN string for a given unsigned integer.
    public static function uintToBaseN($numberArg, $base): string
    {
        $number = $numberArg;
        if ($base < 2 || $base > 62 || $number < 0) {
            return '';
        }

        $output = '';
        do {
            $output = self::BASE_N_SYMBOLS[$number % $base] . $output;
            $number = floor($number / $base);
        } while ($number > 0);

        return $output;
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    public static function fbtHashKey($jsfbt, $desc, $noStringify = false): string
    {
        return self::uintToBaseN(self::fbtJenkinsHash($jsfbt, $desc, $noStringify), 62);
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    public static function fbtJenkinsHash($jsfbt, $desc, $noStringify = false): int
    {
        $payload = $noStringify ? $jsfbt : json_encode($jsfbt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        invariant(
            is_string($payload),
            'JSFBT is not a string type. Please disable noStringify'
        );
        $key = $payload . '|' . $desc;

        return self::jenkinsHash($key);
    }

    public static function toUtf8($str): array
    {
        return array_map('mb_ord', mb_str_split($str));
    }

    // Hash computation for each string that matches the dump script in i18n's php.
    public static function jenkinsHash(string $str): int
    {
        if (! $str) {
            return 0;
        }

        $utf8 = self::toUtf8($str);
        $hash = 0;
        $len = count($utf8);
        for ($i = 0; $i < $len; $i++) {
            $hash = $hash + $utf8[$i];
            $hash = unsignedRightShift($hash + ($hash << 10), 0);
            $hash = $hash ^ unsignedRightShift($hash, 6);
        }

        $hash = unsignedRightShift($hash + ($hash << 3), 0);
        $hash = $hash ^ unsignedRightShift($hash, 11);
        $hash = unsignedRightShift($hash + ($hash << 15), 0);

        return $hash;
    }
}
