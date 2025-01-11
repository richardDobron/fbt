<?php

declare(strict_types=1);

namespace tests\numbers;

use fbt\Runtime\Shared\intlNumUtils;

class intlNumberHindiTest extends \tests\TestCase
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

    protected function prepareForHindiFormat(array $config)
    {
        $this->util->config(array_merge([
        'decimalSeparator' => '.',
    'numberDelimiter' => ',',
    'minDigitsForThousandsSeparator' => 4,
    'standardDecimalPatternInfo' => [
            'primaryGroupSize' => 3,
      'secondaryGroupSize' => 2,
    ],
    'numberingSystemData' => null,
        ], $config));
    }

    protected function prepareForHindiLatinFormat()
    {
        $this->prepareForHindiFormat([]);
    }

    protected function prepareForHindiDevanagariFormat()
    {
        $this->prepareForHindiFormat([
            'numberingSystemData' => [
        'digits' => "\u{0966}\u{0967}\u{0968}\u{0969}\u{096A}\u{096B}\u{096C}\u{096D}\u{096E}\u{096F}",
      ],
        ]);
    }

    public function testRespectPrimaryAndSecondaryGroupingSizesInHindi()
    {
        $this->prepareForHindiLatinFormat();

        $this->assertSame('12', $this->util->formatNumberWithThousandDelimiters(12));
        $this->assertSame('1,234', $this->util->formatNumberWithThousandDelimiters(1234));
        $this->assertSame('12,345', $this->util->formatNumberWithThousandDelimiters(12345));
        $this->assertSame('1,23,456', $this->util->formatNumberWithThousandDelimiters(123456));
        $this->assertSame('12,34,567.1', $this->util->formatNumberWithThousandDelimiters(1234567.1));
    }

    public function testRenderNativeDigitsWhenAvailable()
    {
        $this->prepareForHindiDevanagariFormat();

        $this->assertSame("\u{0966}", $this->util->formatNumberWithThousandDelimiters(0)); // Unicode for Hindi 0
        $this->assertSame("\u{0967},\u{0968}\u{0969}\u{096A}", $this->util->formatNumberWithThousandDelimiters(1234)); // 1,234 in Hindi
        $this->assertSame("\u{0967}\u{0968},\u{0969}\u{096A}\u{096B}", $this->util->formatNumberWithThousandDelimiters(12345)); // 12,345
        $this->assertSame("\u{0967},\u{0968}\u{0969},\u{096A}\u{096B}\u{096C}", $this->util->formatNumberWithThousandDelimiters(123456)); // 1,23,456
        $this->assertSame("\u{0967}\u{0968},\u{0969}\u{096A},\u{096B}\u{096C}\u{096D}.\u{0967}", $this->util->formatNumberWithThousandDelimiters(1234567.1)); // 12,34,567.1
    }
}
