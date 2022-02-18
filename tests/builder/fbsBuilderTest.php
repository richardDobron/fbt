<?php

declare(strict_types=1);

namespace tests\builder;

use function fbs;
use fbt\fbs;
use fbt\fbt;

class fbsBuilderTest extends \tests\TestCase
{
    public function testOptions()
    {
        $html = fbs('Post', [
            'common' => true,
            'transform' => false,
        ]);

        $this->assertSame('<fbs common="true">Post</fbs>', $html);

        $html = fbt::c('Post', [
            'transform' => false,
        ]);

        $this->assertSame('<fbs common="true">Post</fbs>', $html);
    }

    public function testSimpleText()
    {
        $html = fbs(
            'a string with a ' .
            fbs::param('param name', 'parameter', ['gender' => 1]),
            'str_description',
            [
                'transform' => false,
            ]
        );

        $this->assertSame('<fbs desc="str_description">a string with a <fbs:param name="param name" gender="1">parameter</fbs:param></fbs>', $html);
    }

    public function testSimpleTextWithoutDescription()
    {
        $html = fbs('Accept', [
            'transform' => false,
        ]);

        $this->assertSame('<fbs>Accept</fbs>', $html);
    }
}
