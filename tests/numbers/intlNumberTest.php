<?php

declare(strict_types=1);

namespace tests\numbers;

use fbt\Runtime\Shared\FbtHooks;
use fbt\Runtime\Shared\intlNumUtils;

class intlNumberTest extends \tests\TestCase
{
    private $util;

    public function setUp()
    {
        parent::setUp();
        FbtHooks::locale('en_US');

        $this->util = new IntlNumUtils();
    }

    public function testParserDoesNotHandlePathologicalCases()
    {
        $this->assertNull($this->util->parseNumber('-100-,0%*#$00.00'));
        $this->assertNull($this->util->parseNumber('-$100-,0$!@#00.00'));
        $this->assertNull($this->util->parseNumberRaw('1.45.345', '.'));
        $this->assertNull($this->util->parseNumberRaw('1,45,345', ','));
    }

    public function testParserHandlesCurrenciesWithDots()
    {
        $this->assertEquals(2000, $this->util->parseNumber('kr.2000'));
        $this->assertEquals(2000, $this->util->parseNumber("\u{0631}.\u{0633}.2000"));
        $this->assertEquals(2000, $this->util->parseNumber('S/.2000'));
        $this->assertEquals(0.2, $this->util->parseNumber('S.2000'));
    }

    public function testFormatNumberRaw()
    {
        // Testing integer input
        $this->assertEquals('5', $this->util->formatNumberRaw(5));
        $this->assertEquals('5.000', $this->util->formatNumberRaw(5, 3));

        // Testing string input
        $this->assertEquals('5', $this->util->formatNumberRaw('5'));
        $this->assertEquals('5.000', $this->util->formatNumberRaw('5', 3));

        // Testing no rounding when no decimals are specified
        $this->assertEquals('5.499', $this->util->formatNumberRaw(5.499));
        $this->assertEquals('5.5', $this->util->formatNumberRaw(5.5));
        $this->assertEquals('5.499', $this->util->formatNumberRaw(5.499));
        $this->assertEquals('5.5', $this->util->formatNumberRaw(5.5));

        $this->assertEquals('5.499', $this->util->formatNumberRaw('5.499'));
        $this->assertEquals('5.5', $this->util->formatNumberRaw('5.5'));
        $this->assertEquals('5.499', $this->util->formatNumberRaw('5.499'));
        $this->assertEquals('5.5', $this->util->formatNumberRaw('5.5'));

        // Testing rounding (not truncating) decimals for numbers
        $this->assertEquals('1234.57', $this->util->formatNumberRaw(1234.5655, 2));
        $this->assertEquals('1234.56', $this->util->formatNumberRaw(1234.5644, 2));
        $this->assertEquals('-1234.57', $this->util->formatNumberRaw(-1234.5655, 2));
        $this->assertEquals('-1234.56', $this->util->formatNumberRaw(-1234.5644, 2));

        // Testing truncating decimals for strings
        $this->assertEquals('1234.56', $this->util->formatNumberRaw('1234.5655', 2));
        $this->assertEquals('1234.56', $this->util->formatNumberRaw('1234.5644', 2));
        $this->assertEquals('-1234.56', $this->util->formatNumberRaw('-1234.5655', 2));
        $this->assertEquals('-1234.56', $this->util->formatNumberRaw('-1234.5644', 2));

        // Handling higher precision than given
        $this->assertEquals('1234.10000', $this->util->formatNumberRaw(1234.1, 5));
        $this->assertEquals('1234.10000', $this->util->formatNumberRaw('1234.1', 5));

        // Testing delimiters passed
        $this->assertEquals('1.234,54', $this->util->formatNumberRaw(1234.54, 2, '.', ','));
        $this->assertEquals('1.234,54', $this->util->formatNumberRaw('1234.54', 2, '.', ','));

        // Testing large numbers
        $this->assertEquals('1000000000000000.00', $this->util->formatNumberRaw('1000000000000000', 2));
        $this->assertEquals('1000000000000000.12', $this->util->formatNumberRaw('1000000000000000.123', 2));
        $this->assertEquals('-1000000000000000.12', $this->util->formatNumberRaw('-1000000000000000.123', 2));

        // Testing small numbers
        $this->assertEquals('1.99E-7', $this->util->formatNumberRaw(0.000000199, 9));
        $this->assertEquals('2.0E-7', $this->util->formatNumberRaw(0.000000199, 7));
        $this->assertEquals('0.0000000', $this->util->formatNumberRaw(0.0000000199, 7));
        $this->assertEquals('0.000000199', $this->util->formatNumberRaw('0.000000199', 9));
    }

    public function testIntegerStringFormating()
    {
        $this->assertEquals('-999', $this->util->getIntegerString(-999, ','));
        $this->assertEquals('-100', $this->util->getIntegerString(-100, ','));
        $this->assertEquals('-1', $this->util->getIntegerString(-1, ','));
        $this->assertEquals('0', $this->util->getIntegerString(0, ','));
        $this->assertEquals('1', $this->util->getIntegerString(1, ','));
        $this->assertEquals('100', $this->util->getIntegerString(100, ','));
        $this->assertEquals('999', $this->util->getIntegerString(999, ','));

        $this->assertEquals('-123,456,789', $this->util->getIntegerString(-123456789, ','));
        $this->assertEquals('-12,345', $this->util->getIntegerString(-12345, ','));
        $this->assertEquals('-1,000', $this->util->getIntegerString(-1000, ','));
        $this->assertEquals('1,000', $this->util->getIntegerString(1000, ','));
        $this->assertEquals('12,345', $this->util->getIntegerString(12345, ','));
        $this->assertEquals('123,456,789', $this->util->getIntegerString(123456789, ','));

        $this->assertEquals('1.234', $this->util->getIntegerString(1234, '.'));
        $this->assertEquals("1\u{066C}234", $this->util->getIntegerString(1234, "\u{066C}"));
        $this->assertEquals('1::234', $this->util->getIntegerString(1234, '::'));
    }
}
