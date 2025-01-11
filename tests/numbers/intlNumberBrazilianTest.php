<?php

declare(strict_types=1);

namespace numbers;

use fbt\Runtime\Shared\intlNumUtils;

class intlNumberBrazilianTest extends \tests\TestCase
{
    private $util;

    public function setUp(): void
    {
        parent::setUp();

        $this->util = new IntlNumUtils();
        $this->util->config([
            'decimalSeparator' => ',',
            'numberDelimiter' => '.',
            'minDigitsForThousandsSeparator' => 4,
            'standardDecimalPatternInfo' => [
                'primaryGroupSize' => 3,
                'secondaryGroupSize' => 3,
            ],
            'numberingSystemData' => null,
        ]);
    }

    public function testParseNumbersWithFrenchGermanEtcDelimiters()
    {
        $this->assertEquals(100, $this->util->parseNumber('100,00'));
        $this->assertEquals(100, $this->util->parseNumber('$ 100,00'));
        $this->assertEquals(100000, $this->util->parseNumber('100.000,00'));
        $this->assertEquals(100000, $this->util->parseNumber('$100.000,00'));
        $this->assertEquals(100000, $this->util->parseNumber('1.00.0.00,00'));
        $this->assertEquals(-100000, $this->util->parseNumber('-100.000,00'));
        $this->assertEquals(-100000, $this->util->parseNumber('-$100.000,00'));
        $this->assertEquals(100, $this->util->parseNumber('100,'));
        $this->assertEquals(0.123, $this->util->parseNumber('0,123'));
        $this->assertEquals(2.13, $this->util->parseNumber('US 2,13'));
        $this->assertEquals(2.13, $this->util->parseNumber('2,13 TL'));
        $this->assertEquals(123456789, $this->util->parseNumber('123.456.789'));
        $this->assertEquals(123456789123, $this->util->parseNumber('123.456.789.123'));
        $this->assertEquals(123456789, $this->util->parseNumber('123.456.789.'));
        $this->assertEquals(123456.785, $this->util->parseNumber('123.456,785'));
        $this->assertEquals(-123456789, $this->util->parseNumber('-123.456.789'));
    }

    public function testParserHandlesBrazilianFormatCorrectly()
    {
        $this->assertEquals(100, $this->util->parseNumber('100,00'));
        $this->assertEquals(100, $this->util->parseNumber('$ 100,00'));
        $this->assertEquals(100000, $this->util->parseNumber('100.000,00'));
        $this->assertEquals(100000, $this->util->parseNumber('$100.000,00'));
        $this->assertEquals(100000, $this->util->parseNumber('1.00.0.00,00'));
        $this->assertEquals(-100000, $this->util->parseNumber('-100.000,00'));
        $this->assertEquals(-100000, $this->util->parseNumber('-$100.000,00'));
        $this->assertEquals(100, $this->util->parseNumber('100,'));
        $this->assertEquals(0.123, $this->util->parseNumber('0,123'));
        $this->assertEquals(2.13, $this->util->parseNumber('US 2,13'));
        $this->assertEquals(2.13, $this->util->parseNumber('2,13 TL'));
        $this->assertEquals(123456789, $this->util->parseNumber('123.456.789'));
        $this->assertEquals(123456789123, $this->util->parseNumber('123.456.789.123'));
        $this->assertEquals(123456789, $this->util->parseNumber('123.456.789.'));
        $this->assertEquals(123456.785, $this->util->parseNumber('123.456,785'));
        $this->assertEquals(-123456789, $this->util->parseNumber('-123.456.789'));
    }

    public function testParseNumbersIncludingPeruvianAndRussianCurrency()
    {
        $this->assertEquals(0.45, $this->util->parseNumber('S/.,45'));
    }

    public function testParseNumbersStartingWithCurrencySeparator()
    {
        $this->assertEquals(0.75, $this->util->parseNumber(',75'));
        $this->assertEquals(0.75942345, $this->util->parseNumber(',75942345'));
    }
}
