<?php

namespace fbt\Runtime\Shared;

use fbt\Exceptions\FbtException;
use fbt\fbt;

class IntlList
{
    public const CONJUNCTIONS = [
        'AND' => 'and',
        'NONE' => 'none',
        'OR' => 'or',
    ];

    public const DELIMITERS = [
        'BULLET' => 'bullet',
        'COMMA' => 'comma',
        'SEMICOLON' => 'semicolon',
    ];

    /**
     * @param array $items
     * @param string|null $conjunction
     * @param string|null $delimiter
     * @param array{serialComma?: bool}|null $options
     * @param string $fbt - js~php diff: the class of the fbt API (fbt or fbs)
     *
     * @return mixed|\fbt\fbt|string
     * @throws \fbt\Exceptions\FbtException
     */
    public static function listWithRuntime(
        array $items,
        ?string $conjunction = null,
        ?string $delimiter = null,
        ?array $options = null,
        string $fbt = fbt::class
    ) {
        $conjunction = $conjunction ?? self::CONJUNCTIONS['AND'];
        $delimiter = $delimiter ?? self::DELIMITERS['COMMA'];
        // js~php diff: support arrays that are not 0-indexed
        $items = array_values(array_filter($items, [self::class, 'isTruthy']));

        $count = count($items);
        if ($count === 0) {
            return '';
        } elseif ($count === 1) {
            return $items[0];
        }

        $lastItem = $items[$count - 1];
        $output = $items[0];

        for ($index = 1; $index < $count - 1; $index++) {
            switch ($delimiter) {
                case self::DELIMITERS['SEMICOLON']:
                    $output = new $fbt([
                        $fbt::param('previous items', $output),
                        '; ',
                        $fbt::param('following items', $items[$index]),
                    ], 'A list of items of various types, for example: "San Francisco; London; Tokyo". {previous items} and {following items} are themselves lists that contain one or more items.');

                    break;
                case self::DELIMITERS['BULLET']:
                    $output = new $fbt([
                        $fbt::param('previous items', $output),
                        " \u{2022} ",
                        $fbt::param('following items', $items[$index]),
                    ], 'A list of items of various types separated by bullets, for example: "San Francisco \u2022 London \u2022 Tokyo". {previous items} and {following items} are themselves lists that contain one or more items.');

                    break;
                default:
                    $output = new $fbt([
                        $fbt::param('previous items', $output),
                        ', ',
                        $fbt::param('following items', $items[$index]),
                    ], 'A list of items of various types separated by commas, for example: "San Francisco, London, Tokyo". {previous items} and {following items} are themselves lists that contain one or more items.');
            }
        }

        switch ($conjunction) {
            case self::CONJUNCTIONS['AND']:
                if (($options['serialComma'] ?? false) && $delimiter === self::DELIMITERS['COMMA'] && $count > 2) {
                    return new $fbt([
                        $fbt::param('list of items', $output),
                        ', and ',
                        $fbt::param('last item', $lastItem),
                    ], 'A list of items of various types with a serial comma, for example: "item1, item2, and item3"');
                }

                return new $fbt([
                    $fbt::param('list of items', $output),
                    ' and ',
                    $fbt::param('last item', $lastItem),
                ], 'A list of items of various types, for example: "item1, item2, item3 and item4"');

            case self::CONJUNCTIONS['OR']:
                if (($options['serialComma'] ?? false) && $delimiter === self::DELIMITERS['COMMA'] && $count > 2) {
                    return new $fbt([
                        $fbt::param('list of items', $output),
                        ', or ',
                        $fbt::param('last item', $lastItem),
                    ], 'A list of items of various types with a serial comma, for example: "item1, item2, or item3"');
                }

                return new $fbt([
                    $fbt::param('list of items', $output),
                    ' or ',
                    $fbt::param('last item', $lastItem),
                ], 'A list of items of various types, for example: "item1, item2, item3 or item4"');

            case self::CONJUNCTIONS['NONE']:
                switch ($delimiter) {
                    case self::DELIMITERS['SEMICOLON']:
                        return new $fbt([
                            $fbt::param('previous items', $output),
                            '; ',
                            $fbt::param('last item', $lastItem),
                        ], 'A list of items of various types, for example: "San Francisco; London; Tokyo". {previous items} itself contains one or more items.');
                    case self::DELIMITERS['BULLET']:
                        return new $fbt([
                            $fbt::param('list of items', $output),
                            " \u{2022} ",
                            $fbt::param('last item', $lastItem),
                        ], 'A list of items of various types separated by bullets, for example: "San Francisco \u2022 London \u2022 Tokyo". {previous items} contains one or more items.');
                    default:
                        return new $fbt([
                            $fbt::param('list of items', $output),
                            ', ',
                            $fbt::param('last item', $lastItem),
                        ], 'A list of items of various types, for example: "item1, item2, item3, item4"');
                }
                // no break
            default:
                throw new FbtException("Invalid conjunction $conjunction provided to '<fbt:list>'.");
        }
    }

    /**
     * @param array{serialComma?: bool}|null $options
     *
     * @return mixed|\fbt\fbt|string
     * @throws FbtException
     */
    public static function intlList(array $items, ?string $conjunction = null, ?string $delimiter = null, ?array $options = null)
    {
        return self::listWithRuntime($items, $conjunction, $delimiter, $options);
    }

    /**
     * js~php diff: equivalent of JS `items.filter(Boolean)`, e.g. the string "0" is kept
     *
     * @param mixed $item
     */
    private static function isTruthy($item): bool
    {
        if (is_float($item)) {
            return $item != 0 && ! is_nan($item);
        }

        return $item !== null && $item !== false && $item !== '' && $item !== 0;
    }
}
