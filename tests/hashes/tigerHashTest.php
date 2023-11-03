<?php

declare(strict_types=1);

namespace tests\hashes;

use fbt\Transform\FbtTransform\fbtHash;

class tigerHashTest extends \tests\TestCase
{
    public function dataProvider(): array
    {
        return [
            ['', '24f0130c63ac933216166e76b1bb925f'],
            ['abc', 'f258c1e88414ab2a527ab541ffc5b8bf'],
            ['Tiger', '9f00f599072300dd276abb38c8eb6dec'],
            ['ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+-', '87fb2a9083851cf7470d2cf810e6df9e'],
            ['ABCDEFGHIJKLMNOPQRSTUVWXYZ=abcdefghijklmnopqrstuvwxyz+0123456789', '467db80863ebce488df1cd1261655de9'],
            ['Tiger - A Fast New Hash Function, by Ross Anderson and Eli Biham', '0c410a042968868a1671da5a3fd29a72'],
            ['Tiger - A Fast New Hash Function, by Ross Anderson and Eli Biham, proceedings of Fast Software Encryption 3, Cambridge.', 'ebf591d5afa655ce7f22894ff87f54ac'],
            ['Tiger - A Fast New Hash Function, by Ross Anderson and Eli Biham, proceedings of Fast Software Encryption 3, Cambridge, 1996.', '3d9aeb03d1bd1a6357b2774dfd6d5b24'],
            ['ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+-ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+-', '00b83eb4e53440c576ac6aaee0a74858'],
            ["\u{1F4AF}k\u{00E5}\u{0019}-\u{00D4}\u{00B5}\u{00A6}\u{00A8}\u{00B7}G:\u{00ED}p\u{00D0}\u{FFFD}\u{001F}NT\u{0012}H\u{00E3}\u{FFFD}\u{001A}\u{00D2}", 'ed31a59cd617d2a2b0402d5af48ea74a'],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testTigerHash($input, $expected)
    {
        $this->assertEquals($expected, fbtHash::oldTigerHash($input));
    }
}
