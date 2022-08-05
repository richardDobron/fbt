<?php

namespace fbt\Runtime\Shared;

use function fbt\invariant;

class IntlList
{
    private $items;
    private $conjunction;
    private $delimiter;

    const CONJUNCTIONS = [
        'AND' => 'AND',
        'NONE' => 'NONE',
        'OR' => 'OR',
    ];

    const DELIMITERS = [
        'COMMA' => 'COMMA',
        'SEMICOLON' => 'SEMICOLON',
    ];

    public function __construct(array $items, string $conjunction = null, string $delimiter = null)
    {
        $this->items = $items;
        $this->conjunction = $conjunction;
        $this->delimiter = $delimiter;
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     * @return \fbt\fbt|string
     */
    public function format()
    {
        $count = count($this->items);
        if ($count === 0) {
            return '';
        } elseif ($count === 1) {
            return $this->items[0];
        }

        $lastItem = $this->items[$count - 1];
        $output = $this->items[0];

        for ($i = 1; $i < $count - 1; ++$i) {
            switch ($this->delimiter) {
                case self::DELIMITERS['SEMICOLON']:
                    $output = fbt([
                        \fbt\fbt::param('previous items', $output),
                        '; ',
                        \fbt\fbt::param('following items', $this->items[$i]),
                    ], 'A list of items of various types, for example: ' .
                        '"Menlo Park, CA; Seattle, WA; New York City, NY". ' .
                        '{previous items} and {following items} are themselves ' .
                        'lists that contain one or more items.');

                    break;
                default:
                    $output = fbt([
                        \fbt\fbt::param('previous items', $output),
                        ', ',
                        \fbt\fbt::param('following items', $this->items[$i]),
                    ], 'A list of items of various types. {previous items} and' .
                        ' {following items} are themselves lists that contain one or' .
                        ' more items.');
            }
        }

        return self::_getConjunction(
            $output,
            $lastItem,
            $this->conjunction ?? self::CONJUNCTIONS['AND'],
            $this->delimiter ?? self::DELIMITERS['COMMA']
        );
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    private static function _getConjunction($list, $lastItem, $conjunction, $delimiter): \fbt\fbt
    {
        switch ($conjunction) {
            case self::CONJUNCTIONS['AND']:
                return fbt([
                    \fbt\fbt::param('list of items', $list),
                    ' and ',
                    \fbt\fbt::param('last item', $lastItem),
                ], 'A list of items of various types, for example:' .
                    ' "item1, item2, item3 and item4"');

            case self::CONJUNCTIONS['OR']:
                return fbt([
                    \fbt\fbt::param('list of items', $list),
                    ' or ',
                    \fbt\fbt::param('last item', $lastItem),
                ], 'A list of items of various types, for example:' .
                    ' "item1, item2, item3 or item4"');

            case self::CONJUNCTIONS['NONE']:
                switch ($delimiter) {
                    case self::DELIMITERS['SEMICOLON']:
                        return fbt([
                            \fbt\fbt::param('previous items', $list),
                            '; ',
                            \fbt\fbt::param('last item', $lastItem),
                        ], 'A list of items of various types, for example:' .
                            ' "Menlo Park, CA; Seattle, WA; New York City, NY". ' .
                            '{previous items} itself contains one or more items.');
                    default:
                        return fbt(
                            [
                            \fbt\fbt::param('list of items', $list),
                            ', ',
                            \fbt\fbt::param('last item', $lastItem),
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
