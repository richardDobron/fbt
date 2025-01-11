<?php

declare(strict_types=1);

namespace numbers;

use fbt\Runtime\Shared\intlNumUtils;

class intlNumberAmericanTest extends \tests\TestCase
{
    private $util;

    public function setUp(): void
    {
        parent::setUp();

        $this->util = new IntlNumUtils();
        $this->util->config([
            'decimalSeparator' => '.',
            'numberDelimiter' => ',',
            'minDigitsForThousandsSeparator' => 4,
            'standardDecimalPatternInfo' => [
                'primaryGroupSize' => 3,
                'secondaryGroupSize' => 3,
            ],
            'numberingSystemData' => null,
        ]);
    }

    public function testFormatNumber()
    {
        // Testing integer input
        $this->assertEquals('5', $this->util->formatNumber(5));
        $this->assertEquals('5.000', $this->util->formatNumber(5, 3));

        // Testing no rounding when no decimals are specified
        $this->assertEquals('5.499', $this->util->formatNumber(5.499));
        $this->assertEquals('5.5', $this->util->formatNumber(5.5));
        $this->assertEquals('5.499', $this->util->formatNumber(5.499));
        $this->assertEquals('5.5', $this->util->formatNumber(5.5));

        // Testing rounding (not truncating) decimals
        $this->assertEquals('1234.57', $this->util->formatNumber(1234.5655, 2));
        $this->assertEquals('1234.56', $this->util->formatNumber(1234.5644, 2));
        $this->assertEquals('-1234.57', $this->util->formatNumber(-1234.5655, 2));
        $this->assertEquals('-1234.56', $this->util->formatNumber(-1234.5644, 2));

        // Handling higher precision than given
        $this->assertEquals('1234.10000', $this->util->formatNumber(1234.1, 5));

        // Testing user locale for number formatting
        $this->util->config([
            'decimalSeparator' => '#',
            'numberDelimiter' => '/',
            'minDigitsForThousandsSeparator' => 6,
        ]);

        // Below the thousand separator threshold. No thousand separator.
        $this->assertEquals('1234#1', $this->util->formatNumber(1234.1, 1));
        $this->assertEquals('12345#1', $this->util->formatNumber(12345.1, 1));

        // Above the thousand separator threshold.
        $this->assertEquals('123456#1', $this->util->formatNumber(123456.1, 1));
    }

    public function testFormatNumberWithThousandDelimiters()
    {
        $this->assertEquals('5', $this->util->formatNumberWithThousandDelimiters(5));
        $this->assertEquals('5.000', $this->util->formatNumberWithThousandDelimiters(5, 3));

        // Testing no rounding when no decimals are specified
        $this->assertEquals('5.499', $this->util->formatNumberWithThousandDelimiters(5.499));
        $this->assertEquals('5.5', $this->util->formatNumberWithThousandDelimiters(5.5));
        $this->assertEquals('5.499', $this->util->formatNumberWithThousandDelimiters(5.499, null));
        $this->assertEquals('5.5', $this->util->formatNumberWithThousandDelimiters(5.5, null));

        // Testing rounding (not truncating) decimals
        $this->assertEquals('1,234.57', $this->util->formatNumberWithThousandDelimiters(1234.5655, 2));
        $this->assertEquals('1,234.56', $this->util->formatNumberWithThousandDelimiters(1234.5644, 2));
        $this->assertEquals('-1,234.57', $this->util->formatNumberWithThousandDelimiters(-1234.5655, 2));
        $this->assertEquals('-1,234.56', $this->util->formatNumberWithThousandDelimiters(-1234.5644, 2));

        // Handling higher precision than given
        $this->assertEquals('1,234.10000', $this->util->formatNumberWithThousandDelimiters(1234.1, 5));

        // Testing user locale for number formatting
        $this->util->config([
            'decimalSeparator' => '#',
            'numberDelimiter' => '/',
            'minDigitsForThousandsSeparator' => 6,
        ]);

        // Below the thousand separator threshold
        $this->assertEquals('1234#1', $this->util->formatNumberWithThousandDelimiters(1234.1, 1));
        $this->assertEquals('12345#1', $this->util->formatNumberWithThousandDelimiters(12345.1, 1));

        // Above the thousand separator threshold
        $this->assertEquals('123/456#1', $this->util->formatNumberWithThousandDelimiters(123456.1, 1));
    }

    public function testFormatNumberInSignificantFiguresAndDecimals()
    {
        $this->assertEquals('120,000,000', $this->util->formatNumberWithLimitedSigFig(123456789, 0, 2));
        $this->assertEquals('1.20', $this->util->formatNumberWithLimitedSigFig(1.23456789, 2, 2));
        $this->assertEquals('-12.300', $this->util->formatNumberWithLimitedSigFig(-12.345, 3, 3));
        $this->assertEquals('0.00', $this->util->formatNumberWithLimitedSigFig(0, null, 3));
    }

    public function testReturnsNullForNonNumericInput()
    {
        $this->assertNull($this->util->parseNumber(''));
        $this->assertNull($this->util->parseNumber('asdf'));
    }

    public function testInfersDecimalSymbol()
    {
        $this->assertEquals(0, $this->util->parseNumber('0'));
        $this->assertEquals(100, $this->util->parseNumber('100.00'));
        $this->assertEquals(100, $this->util->parseNumber('$ 100.00'));
        $this->assertEquals(100000, $this->util->parseNumber('100,000.00'));
        $this->assertEquals(100000, $this->util->parseNumber('$100,000.00'));
        $this->assertEquals(100000, $this->util->parseNumber('1,00,0,00.00')); // malformed but OK
        $this->assertEquals(-100000, $this->util->parseNumber('-100,000.00'));
        $this->assertEquals(-100000, $this->util->parseNumber('-$100,000.00'));
        $this->assertEquals(100, $this->util->parseNumber('100.'));
        $this->assertEquals(0.123, $this->util->parseNumber('0.123'));
        $this->assertEquals(2.13, $this->util->parseNumber('US 2.13'));
        $this->assertEquals(2.13, $this->util->parseNumber('2.13 TL'));
        $this->assertEquals(123456789, $this->util->parseNumber('123,456,789'));
        $this->assertEquals(123456789123, $this->util->parseNumber('123,456,789,123'));
        $this->assertEquals(123456789, $this->util->parseNumber('123,456,789,'));
        $this->assertEquals(123456.785, $this->util->parseNumber('123,456.785'));
        $this->assertEquals(-123456789, $this->util->parseNumber('-123,456,789'));
    }

    public function testRespectsDecimalSymbolPassed()
    {
        $this->assertEquals(100.235, $this->util->parseNumberRaw('100,235', ','));
        $this->assertEquals(100, $this->util->parseNumberRaw('100,', ','));
        $this->assertEquals(123456789, $this->util->parseNumberRaw('123.456.789', ',', '.'));
        $this->assertEquals(123456789123, $this->util->parseNumberRaw('123.456.789.123', ',', '.'));
        $this->assertEquals(123456789, $this->util->parseNumberRaw('123.456.789.', ',', '.'));
        $this->assertEquals(-123456789, $this->util->parseNumberRaw('-123.456.789', ',', '.'));
        $this->assertEquals(123456.785, $this->util->parseNumberRaw('123.456,785', ',', '.'));
        $this->assertEquals(30002000.132, $this->util->parseNumberRaw('300,02,000 132', ' ', ','));
    }

    public function testSupportsSpanishThousandDelimiters()
    {
        $this->assertEquals(123456789, $this->util->parseNumberRaw('123 456 789', '.'));
        $this->assertEquals(123456789, $this->util->parseNumberRaw('123 456 789', ','));
        $this->assertEquals(1234.56, $this->util->parseNumberRaw('1 234,56', ','));
    }

    public function testParseNumbersWithEnglishDelimiters()
    {
        $this->assertEquals(0, $this->util->parseNumber('0'));
        $this->assertEquals(100, $this->util->parseNumber('100.00'));
        $this->assertEquals(100, $this->util->parseNumber('$ 100.00'));
        $this->assertEquals(100000, $this->util->parseNumber('100,000.00'));
        $this->assertEquals(100000, $this->util->parseNumber('$100,000.00'));
        $this->assertEquals(100000, $this->util->parseNumber('1,00,0,00.00'));
        $this->assertEquals(-100000, $this->util->parseNumber('-100,000.00'));
        $this->assertEquals(-100000, $this->util->parseNumber('-$100,000.00'));
        $this->assertEquals(100, $this->util->parseNumber('100.'));
        $this->assertEquals(0.123, $this->util->parseNumber('0.123'));
        $this->assertEquals(2.13, $this->util->parseNumber('US 2.13'));
        $this->assertEquals(2.13, $this->util->parseNumber('2.13 TL'));
        $this->assertEquals(123456789, $this->util->parseNumber('123,456,789'));
        $this->assertEquals(123456789123, $this->util->parseNumber('123,456,789,123'));
        $this->assertEquals(123456789, $this->util->parseNumber('123,456,789,'));
        $this->assertEquals(123456.785, $this->util->parseNumber('123,456.785'));
        $this->assertEquals(-123456789, $this->util->parseNumber('-123,456,789'));
    }

    public function testParseNumbersStartingWithCurrencySeparator()
    {
        $this->assertEquals(0.75, $this->util->parseNumber('.75'));
        $this->assertEquals(0.75942345, $this->util->parseNumber('.75942345'));
    }

    public function testParserHandlesSymbolsWithDot()
    {
        $this->assertEquals(450, $this->util->parseNumber('S/. 450.00'));
        $this->assertEquals(0.45, $this->util->parseNumber('S/..45'));
        $this->assertEquals(450, $this->util->parseNumber('p. 450.00'));
        $this->assertEquals(0.45, $this->util->parseNumber('fake.45'));
        $this->assertEquals(0.45, $this->util->parseNumber('p..45'));
        $this->assertEquals(450, $this->util->parseNumber('450.00p.'));
        $this->assertEquals(45, $this->util->parseNumber('45p.'));
        $this->assertEquals(0.45, $this->util->parseNumber('.45p.'));
        $this->assertEquals(0.75, $this->util->parseNumber('.75'));
        $this->assertEquals(0.75942345, $this->util->parseNumber('.75942345'));
    }

    public function testParseNumbersIncludingPeruvianAndRussianCurrency()
    {
        $this->assertEquals(450, $this->util->parseNumber('S/. 450.00'));
        $this->assertEquals(0.45, $this->util->parseNumber('S/..45'));
        $this->assertEquals(450, $this->util->parseNumber('p. 450.00'));
        $this->assertEquals(0.45, $this->util->parseNumber('p..45'));
        $this->assertEquals(450, $this->util->parseNumber('450.00p.'));
        $this->assertEquals(45, $this->util->parseNumber('45p.'));
        $this->assertEquals(0.45, $this->util->parseNumber('.45p.'));
    }

    public function testParserIgnoresSpacesAsMuchAsPossible()
    {
        $this->assertEquals(123456789, $this->util->parseNumberRaw('123 456 789', '.'));
        $this->assertEquals(123456789, $this->util->parseNumberRaw('123 456 789', ','));
        $this->assertEquals(1234.56, $this->util->parseNumberRaw('1 234,56', ','));
    }

    public function testParserHandlesAmericanFormatCorrectly()
    {
        $this->assertEquals(0, $this->util->parseNumber('0'));
        $this->assertEquals(100, $this->util->parseNumber('100.00'));
        $this->assertEquals(100, $this->util->parseNumber('$ 100.00'));
        $this->assertEquals(100000, $this->util->parseNumber('100,000.00'));
        $this->assertEquals(100000, $this->util->parseNumber('$100,000.00'));
        $this->assertEquals(100000, $this->util->parseNumber('1,00,0,00.00'));
        $this->assertEquals(-100000, $this->util->parseNumber('-100,000.00'));
        $this->assertEquals(-100000, $this->util->parseNumber('-$100,000.00'));
        $this->assertEquals(100, $this->util->parseNumber('100.'));
        $this->assertEquals(0.123, $this->util->parseNumber('0.123'));
        $this->assertEquals(2.13, $this->util->parseNumber('US 2.13'));
        $this->assertEquals(2.13, $this->util->parseNumber('2.13 TL'));
        $this->assertEquals(123456789, $this->util->parseNumber('123,456,789'));
        $this->assertEquals(123456789123, $this->util->parseNumber('123,456,789,123'));
        $this->assertEquals(123456789, $this->util->parseNumber('123,456,789,'));
        $this->assertEquals(123456.785, $this->util->parseNumber('123,456.785'));
        $this->assertEquals(-123456789, $this->util->parseNumber('-123,456,789'));
    }
}
