<?php

declare(strict_types=1);

namespace numbers;

use fbt\Runtime\Shared\intlNumUtils;

class intlNumberArabicTest extends \tests\TestCase
{
    private $util;

    public function setUp(): void
    {
        parent::setUp();

        $this->util = new IntlNumUtils();
        $this->util->config([
            'decimalSeparator' => "\u{066B}",
            'numberDelimiter' => "\u{066C}",
            'numberingSystemData' => [
                'digits' => "\u{0660}\u{0661}\u{0662}\u{0663}\u{0664}\u{0665}\u{0666}\u{0667}\u{0668}\u{0669}",
            ],
        ]);
    }

    public function testParseNumbersWithArabicKeyboardInputCharacters()
    {
        $this->assertEquals(0.123, $this->util->parseNumber("\u{0660}\u{066b}\u{0661}\u{0662}\u{0663}"));
        $this->assertEquals(1234567890, $this->util->parseNumber("\u{0661}\u{0662}\u{0663}\u{0664}\u{0665}\u{0666}\u{0667}\u{0668}\u{0669}\u{0660}"));
        $this->assertEquals(1234567891230, $this->util->parseNumber("\u{0661}\u{0662}\u{0663}\u{066C}\u{0664}\u{0665}\u{0666}\u{066C}" . "\u{0667}\u{0668}\u{0669}\u{066C}\u{0661}\u{0662}\u{0663}\u{0660}"));
        $this->assertEquals(123456789, $this->util->parseNumber("\u{0661}\u{0662}\u{0663}\u{066C}\u{0664}\u{0665}\u{0666}\u{066C}\u{0667}\u{0668}\u{0669}\u{066C}"));
        $this->assertEquals(123456.789, $this->util->parseNumber("\u{0661}\u{0662}\u{0663}\u{066C}\u{0664}\u{0665}\u{0666}\u{066b}\u{0667}\u{0668}\u{0669}"));
        $this->assertEquals(-123456789, $this->util->parseNumber("-\u{0661}\u{0662}\u{0663}\u{066C}\u{0664}\u{0665}\u{0666}\u{066C}\u{0667}\u{0668}\u{0669}"));
    }
}
