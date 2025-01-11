<?php

declare(strict_types=1);

namespace tests\numbers;

use fbt\Runtime\Shared\intlNumUtils;

class intlNumberPersianTest extends \tests\TestCase
{
    private $util;

    public function setUp(): void
    {
        parent::setUp();

        $this->util = new IntlNumUtils();
        $this->util->config([
            "decimalSeparator" => "\u{066B}",
            "numberDelimiter" => "\u{066C}",
            "numberingSystemData" => [
                "digits" => "\u{06F0}\u{06F1}\u{06F2}\u{06F3}\u{06F4}\u{06F5}\u{06F6}\u{06F7}\u{06F8}\u{06F9}",
            ],
        ]);
    }

    public function testParseNumbersWithPersianKeyboardInputCharacters()
    {
        $this->assertEquals(0.123, $this->util->parseNumber("\u{06f0}\u{066B}\u{06f1}\u{06f2}\u{06f3}"));
        $this->assertEquals(1234567890, $this->util->parseNumber("\u{06f1}\u{06f2}\u{06f3}\u{06f4}\u{06f5}\u{06f6}\u{06f7}\u{06f8}\u{06f9}\u{06f0}"));
        $this->assertEquals(1234567891230, $this->util->parseNumber("\u{06f1}\u{06f2}\u{06f3}\u{066C}\u{06f4}\u{06f5}\u{06f6}\u{066C}\u{06f7}\u{06f8}\u{06f9}\u{066C}\u{06f1}\u{06f2}\u{06f3}\u{06f0}"));
        $this->assertEquals(123456789, $this->util->parseNumber("\u{06f1}\u{06f2}\u{06f3}\u{066C}\u{06f4}\u{06f5}\u{06f6}\u{066C}\u{06f7}\u{06f8}\u{06f9}\u{066C}"));
        $this->assertEquals(123456.789, $this->util->parseNumber("\u{06f1}\u{06f2}\u{06f3}\u{066C}\u{06f4}\u{06f5}\u{06f6}\u{066B}\u{06f7}\u{06f8}\u{06f9}"));
        $this->assertEquals(-123456789, $this->util->parseNumber("-\u{06f1}\u{06f2}\u{06f3}\u{066C}\u{06f4}\u{06f5}\u{06f6}\u{066C}\u{06f7}\u{06f8}\u{06f9}"));
    }
}
