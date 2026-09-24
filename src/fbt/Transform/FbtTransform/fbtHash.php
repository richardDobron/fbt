<?php

namespace fbt\Transform\FbtTransform;

use function fbt\invariant;
use function fbt\unsignedRightShift;

use fbt\Util\JsJson;

class fbtHash
{
    public const BASE_N_SYMBOLS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    // Compute the baseN string for a given unsigned integer.
    public static function uintToBaseN(int $number, int $base): string
    {
        if ($base < 2 || $base > 62 || $number < 0) {
            return '';
        }

        $output = '';
        do {
            $output = self::BASE_N_SYMBOLS[$number % $base] . $output;
            $number = intdiv($number, $base);
        } while ($number > 0);

        return $output;
    }

    /**
     * @param array $jsfbt - TableJSFBTTree (a leaf or a tree of leaves)
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public static function fbtHashKey(array $jsfbt): string
    {
        return self::uintToBaseN(self::fbtJenkinsHash($jsfbt), 62);
    }

    /**
     * @param array $jsfbt - TableJSFBTTree (a leaf or a tree of leaves)
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public static function fbtJenkinsHash(array $jsfbt): int
    {
        $desc = null;
        $leavesHaveSameDesc = true;
        JSFbtUtil::onEachLeaf(['jsfbt' => ['t' => $jsfbt, 'm' => []]], function (array $leaf) use (&$desc, &$leavesHaveSameDesc) {
            if ($desc === null) {
                $desc = $leaf['desc'];
            } elseif ($desc !== $leaf['desc']) {
                $leavesHaveSameDesc = false;
            }
        });

        if ($leavesHaveSameDesc) {
            $hashInputTree = JSFbtUtil::mapLeaves($jsfbt, function (array $leaf) {
                return isset($leaf['tokenAliases'])
                    ? ['text' => $leaf['text'], 'tokenAliases' => $leaf['tokenAliases']]
                    : $leaf['text'];
            });
            invariant(
                $desc !== null,
                'Expect `desc` to be nonnull as `TableJSFBTTree` should contain at least ' .
                'one leaf.'
            );
            $key = JsJson::stringify($hashInputTree) . '|' . $desc;

            return self::jenkinsHash($key);
        }

        $hashInputTree = JSFbtUtil::mapLeaves($jsfbt, function (array $leaf) {
            $newLeaf = ['desc' => $leaf['desc'], 'text' => $leaf['text']];

            return isset($leaf['tokenAliases'])
                ? $newLeaf + ['tokenAliases' => $leaf['tokenAliases']]
                : $newLeaf;
        });

        return self::jenkinsHash(JsJson::stringify($hashInputTree));
    }

    public static function toUtf8(string $str): array
    {
        return array_values(unpack('C*', $str));
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

    public static function oldTigerHash(string $input, int $digestBitLen = 128)
    {
        return substr(
            implode(
                array_map(
                    function (string $h) {
                        return str_pad(bin2hex(strrev($h)), 16, "0");
                    },
                    str_split(hash("tiger192,3", $input, true), 8)
                )
            ),
            0,
            48 - (192 - $digestBitLen) / 4
        );
    }
}
