<?php

declare(strict_types=1);

namespace tests\fbt;

use fbt\Exceptions\FbtException;
use fbt\FbtConfig;

use function fbt\intlList;

use fbt\Runtime\Shared\IntlList;
use fbt\Transform\FbtTransform\FbtTransform;

class intlListTest extends \tests\TestCase
{
    public function testEmptyList()
    {
        $this->assertSame('', (string)intlList([]));
    }

    public function testListFullOfNullItems()
    {
        $this->assertSame('', (string)intlList([null, null]));
    }

    public function testSingleItem()
    {
        $this->assertSame('first', (string)intlList(['first']));
    }

    public function testTwoItems()
    {
        $this->assertSame('first and second', (string)intlList(['first', 'second']));
    }

    public function testThreeItems()
    {
        $this->assertSame('first, second and third', (string)intlList(['first', 'second', 'third']));
    }

    public function testBunchOfItems()
    {
        $this->assertSame('1, 2, 3, 4, 5, 6, 7 and 8', (string)intlList(['1', '2', '3', '4', '5', '6', '7', '8']));
    }

    public function testBunchOfItemsSomeOfWhichAreNull()
    {
        $this->assertSame(
            '1, 2, 3, 4, 5, 6, 7 and 8',
            (string)intlList(['1', '2', '3', '4', null, '5', null, '6', '7', '8'])
        );
    }

    public function testNoConjunction()
    {
        $this->assertSame('first, second, third', (string)intlList(['first', 'second', 'third'], 'none'));
    }

    public function testOptionalDelimiter()
    {
        $this->assertSame('first; second; third', (string)intlList(['first', 'second', 'third'], 'none', 'semicolon'));
    }

    public function testBulletDelimiters()
    {
        $this->assertSame("first \u{2022} second \u{2022} third", (string)intlList(['first', 'second', 'third'], 'none', 'bullet'));
    }

    public function testSerialComma()
    {
        $serialComma = ['serialComma' => true];

        // should not use serial comma by default for three items
        $this->assertSame('first, second and third', (string)intlList(['first', 'second', 'third']));
        // should use serial comma when serialComma is true for three items
        $this->assertSame('first, second, and third', (string)intlList(['first', 'second', 'third'], 'and', 'comma', $serialComma));
        // should use serial comma when serialComma is true for multiple items
        $this->assertSame('1, 2, 3, and 4', (string)intlList(['1', '2', '3', '4'], 'and', 'comma', $serialComma));
        // should use serial comma with "or" conjunction
        $this->assertSame('first, second, or third', (string)intlList(['first', 'second', 'third'], 'or', 'comma', $serialComma));
        // should not affect two-item lists
        $this->assertSame('first and second', (string)intlList(['first', 'second'], 'and', 'comma', $serialComma));
        // should not affect single-item lists
        $this->assertSame('first', (string)intlList(['first'], 'and', 'comma', $serialComma));
        // should not use serial comma with non-comma delimiters
        $this->assertSame('first; second and third', (string)intlList(['first', 'second', 'third'], 'and', 'semicolon', $serialComma));
        // should not use serial comma with "none" conjunction
        $this->assertSame('first, second, third', (string)intlList(['first', 'second', 'third'], 'none', 'comma', $serialComma));
    }

    // js~php diff: PHP-specific cases

    public function testConstants()
    {
        $this->assertSame(
            "first \u{2022} second or third",
            (string)intlList(['first', 'second', 'third'], IntlList::CONJUNCTIONS['OR'], IntlList::DELIMITERS['BULLET'])
        );
    }

    public function testFalsyItemsAreIgnored()
    {
        $this->assertSame('0 and 1', (string)intlList(['', '0', false, '1']));
    }

    public function testNonSequentialKeys()
    {
        $this->assertSame('first and second', (string)intlList([3 => 'first', 7 => 'second']));
        $this->assertSame('first', (string)intlList(['a' => 'first']));
    }

    public function testInvalidConjunction()
    {
        $this->expectException(FbtException::class);
        $this->expectExceptionMessage("Invalid conjunction AND provided to '<fbt:list>'.");

        intlList(['first', 'second'], 'AND');
    }

    /**
     * The strings of the lists are the default strings of fbtee (Strings.json)
     */
    public function testStringsAreTheDefaultStringsOfFbtee()
    {
        FbtConfig::set('collectFbt', true);
        \fbt\fbt::_purgeCache();
        FbtTransform::$phrases = [];

        $items = ['a', 'b', 'c'];
        foreach (['and', 'or', 'none'] as $conjunction) {
            foreach (['comma', 'semicolon', 'bullet'] as $delimiter) {
                (string)intlList($items, $conjunction, $delimiter);
                (string)intlList($items, $conjunction, $delimiter, ['serialComma' => true]);
            }
        }

        $strings = array_map(function (array $phrase) {
            return [$phrase['jsfbt']['t']['text'], $phrase['jsfbt']['t']['desc']];
        }, FbtTransform::$phrases);
        sort($strings);

        $expected = [
            ['{previous items}; {following items}', 'A list of items of various types, for example: "San Francisco; London; Tokyo". {previous items} and {following items} are themselves lists that contain one or more items.'],
            ["{previous items} \u{2022} {following items}", 'A list of items of various types separated by bullets, for example: "San Francisco \u2022 London \u2022 Tokyo". {previous items} and {following items} are themselves lists that contain one or more items.'],
            ['{previous items}, {following items}', 'A list of items of various types separated by commas, for example: "San Francisco, London, Tokyo". {previous items} and {following items} are themselves lists that contain one or more items.'],
            ['{list of items}, and {last item}', 'A list of items of various types with a serial comma, for example: "item1, item2, and item3"'],
            ['{list of items} and {last item}', 'A list of items of various types, for example: "item1, item2, item3 and item4"'],
            ['{list of items}, or {last item}', 'A list of items of various types with a serial comma, for example: "item1, item2, or item3"'],
            ['{list of items} or {last item}', 'A list of items of various types, for example: "item1, item2, item3 or item4"'],
            ['{previous items}; {last item}', 'A list of items of various types, for example: "San Francisco; London; Tokyo". {previous items} itself contains one or more items.'],
            ["{list of items} \u{2022} {last item}", 'A list of items of various types separated by bullets, for example: "San Francisco \u2022 London \u2022 Tokyo". {previous items} contains one or more items.'],
            ['{list of items}, {last item}', 'A list of items of various types, for example: "item1, item2, item3, item4"'],
        ];
        sort($expected);

        $this->assertSame($expected, $strings);
    }
}
