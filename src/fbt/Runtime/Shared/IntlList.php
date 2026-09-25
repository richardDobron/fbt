<?php

namespace fbt\Runtime\Shared;

use function fbt;

use fbt\fbt;
use fbt\FbtConfig;

use function fbt\invariant;

class IntlList
{
    public const CONJUNCTIONS = [
        'AND' => 'AND',
        'NONE' => 'NONE',
        'OR' => 'OR',
    ];

    public const DELIMITERS = [
        'BULLET' => 'BULLET',
        'COMMA' => 'COMMA',
        'SEMICOLON' => 'SEMICOLON',
    ];

    /**
     * @param array $items_
     * @param string|null $conjunction
     * @param string|null $delimiter
     *
     * @return mixed|\fbt\fbt|string
     * @throws \fbt\Exceptions\FbtException
     */
    public static function intlList(array $items_, ?string $conjunction = null, ?string $delimiter = null)
    {
        // js~php diff: support arrays that are not 0-indexed
        $items = array_values(array_filter($items_, [self::class, 'isTruthy']));
        if (FbtConfig::get('debug')) {
            foreach ($items as $item) {
                invariant(
                    is_string($item) || (is_object($item) && method_exists($item, '__toString')),
                    'Must provide a string or an fbt result to intlList.'
                );
            }
        }

        $count = count($items);
        if ($count === 0) {
            return '';
        } elseif ($count === 1) {
            return $items[0];
        }

        $lastItem = $items[$count - 1];
        $output = $items[0];

        for ($i = 1; $i < $count - 1; ++$i) {
            switch ($delimiter) {
                case self::DELIMITERS['SEMICOLON']:
                    $output = fbt([
                        fbt::param('previous items', $output),
                        '; ',
                        fbt::param('following items', $items[$i]),
                    ], 'A list of items of various types, for example: ' .
                        '"Menlo Park, CA; Seattle, WA; New York City, NY". ' .
                        '{previous items} and {following items} are themselves ' .
                        'lists that contain one or more items.');

                    break;
                case self::DELIMITERS['BULLET']:
                    $output = fbt([
                        fbt::param('previous items', $output),
                        " \u{2022} ",
                        fbt::param('following items', $items[$i]),
                    ], 'A list of items of various types separated by bullets, for example: ' .
                        "\"Menlo Park, CA \u{2022} Seattle, WA \u{2022} New York City, NY\". " .
                        '{previous items} and {following items} are themselves ' .
                        'lists that contain one or more items.');

                    break;
                default:
                    $output = fbt([
                        fbt::param('previous items', $output),
                        ', ',
                        fbt::param('following items', $items[$i]),
                    ], 'A list of items of various types. {previous items} and' .
                        ' {following items} are themselves lists that contain one or' .
                        ' more items.');
            }
        }

        return self::_getConjunction(
            $output,
            $lastItem,
            $conjunction ?: self::CONJUNCTIONS['AND'],
            $delimiter ?: self::DELIMITERS['COMMA']
        );
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

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    private static function _getConjunction($list, $lastItem, string $conjunction, string $delimiter): fbt
    {
        switch ($conjunction) {
            case self::CONJUNCTIONS['AND']:
                return fbt([
                    fbt::param('list of items', $list),
                    ' and ',
                    fbt::param('last item', $lastItem),
                ], 'A list of items of various types, for example:' .
                    ' "item1, item2, item3 and item4"');

            case self::CONJUNCTIONS['OR']:
                return fbt([
                    fbt::param('list of items', $list),
                    ' or ',
                    fbt::param('last item', $lastItem),
                ], 'A list of items of various types, for example:' .
                    ' "item1, item2, item3 or item4"');

            case self::CONJUNCTIONS['NONE']:
                switch ($delimiter) {
                    case self::DELIMITERS['SEMICOLON']:
                        return fbt([
                            fbt::param('previous items', $list),
                            '; ',
                            fbt::param('last item', $lastItem),
                        ], 'A list of items of various types, for example:' .
                            ' "Menlo Park, CA; Seattle, WA; New York City, NY". ' .
                            '{previous items} itself contains one or more items.');
                    case self::DELIMITERS['BULLET']:
                        return fbt([
                            fbt::param('list of items', $list),
                            " \u{2022} ",
                            fbt::param('last item', $lastItem),
                        ], 'A list of items of various types separated by bullets, for example: ' .
                            "\"Menlo Park, CA \u{2022} Seattle, WA \u{2022} New York City, NY\". " .
                            '{previous items} contains one or more items.');
                    default:
                        return fbt(
                            [
                            fbt::param('list of items', $list),
                            ', ',
                            fbt::param('last item', $lastItem),
                        ],
                            'A list of items of various types, for example:' .
                            ' "item1, item2, item3, item4"'
                        );
                }
                // no break
            default:
                invariant(
                    false,
                    'Invalid conjunction %s provided to intlList',
                    $conjunction
                );
        }
    }
}
