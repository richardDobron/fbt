<?php

namespace fbt\Util;

use fbt\Exceptions\FbtException;

/**
 * js~php diff: JSON serialization that matches JavaScript's `JSON.stringify()`
 * byte for byte, which is required to compute the same hashes as upstream.
 *
 * - PHP arrays are always serialized as JS objects (never as lists), since
 *   JSFBT trees may have sequential integer keys (e.g. enum keys `0`, `1`)
 * - keys are ordered like the properties of a JS object: integer-like keys
 *   (array indices) in ascending order first, then the other keys in insertion
 *   order
 */
class JsJson
{
    private const FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS;

    /**
     * @param mixed $value
     *
     * @throws FbtException
     */
    public static function stringify($value): string
    {
        if (is_array($value) || $value instanceof \stdClass) {
            $parts = [];
            foreach (self::orderedEntries((array)$value) as [$key, $item]) {
                $parts[] = self::encodeScalar((string)$key) . ':' . self::stringify($item);
            }

            return '{' . implode(',', $parts) . '}';
        }

        return self::encodeScalar($value);
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public static function toJsObject($value)
    {
        if (! is_array($value) && ! $value instanceof \stdClass) {
            return $value;
        }

        $object = new \stdClass();
        foreach (self::orderedEntries((array)$value) as [$key, $item]) {
            $object->{$key} = self::toJsObject($item);
        }

        return $object;
    }

    /**
     * @return array<int, array{0: string|int, 1: mixed}>
     */
    private static function orderedEntries(array $value): array
    {
        $indices = [];
        $others = [];

        foreach ($value as $key => $item) {
            if (self::isArrayIndex($key)) {
                $indices[] = [$key, $item];
            } else {
                $others[] = [$key, $item];
            }
        }

        usort($indices, function (array $a, array $b): int {
            return (int)$a[0] <=> (int)$b[0];
        });

        return array_merge($indices, $others);
    }

    /**
     * @param string|int $key
     */
    private static function isArrayIndex($key): bool
    {
        if (is_int($key)) {
            return $key >= 0 && $key <= 4294967294;
        }

        return preg_match('/^(0|[1-9]\d*)$/', $key) === 1 && (float)$key <= 4294967294;
    }

    /**
     * @param mixed $value
     *
     * @throws FbtException
     */
    private static function encodeScalar($value): string
    {
        $json = json_encode($value, self::FLAGS);

        if ($json === false) {
            throw new FbtException('Unable to serialize to JSON: ' . json_last_error_msg());
        }

        return $json;
    }
}
