<?php

declare(strict_types=1);

namespace tests\fbt;

use fbt\Runtime\Shared\IntlList;
use function fbt\intlList;

class intlListTest extends \tests\TestCase
{
    public function testEmptyList()
    {
        $this->assertSame('', (string)intlList([]));
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

    public function testBunchItems()
    {
        $this->assertSame('1, 2, 3, 4, 5, 6, 7 and 8', (string)intlList(['1', '2', '3', '4', '5', '6', '7', '8']));
    }

    public function testEmptyConjunction()
    {
        $this->assertSame('first, second, third', (string)intlList(['first', 'second', 'third'], intlList::CONJUNCTIONS['NONE']));
    }

    public function testOptionalDelimiter()
    {
        $this->assertSame('first; second; third', (string)intlList(['first', 'second', 'third'], intlList::CONJUNCTIONS['NONE'], intlList::DELIMITERS['SEMICOLON']));
    }
}
