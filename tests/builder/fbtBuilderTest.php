<?php

declare(strict_types=1);

namespace tests\builder;

use function fbt;
use function fbt\createElement;
use fbt\fbt;

class fbtBuilderTest extends \tests\TestCase
{
    public function testOptions()
    {
        $html = fbt('A string that moved files', 'options!', [
            'author' => 'jwatson',
            'project' => 'Super Secret',
            'transform' => false,
        ]);

        $this->assertSame('<fbt desc="options!" author="jwatson" project="Super Secret">A string that moved files</fbt>', (string)$html);
    }

    public function testParam()
    {
        $html = fbt(['a' . ' b ', fbt::param('name1', 'x'), ' c ', ' d ', fbt::param('name2', 'y'), ' e '], 'a', [
            'transform' => false,
        ]);

        $this->assertSame('<fbt desc="a">a b <fbt:param name="name1">x</fbt:param> c  d <fbt:param name="name2">y</fbt:param> e </fbt>', (string)$html);
    }

    public function testName()
    {
        $html = fbt(['You just friended ', fbt::name('name', 'Sarah', 2)], 'names', [
            'transform' => false,
        ]);

        $this->assertSame('<fbt desc="names">You just friended <fbt:name name="name" gender="2">Sarah</fbt:name></fbt>', (string)$html);
    }

    public function testPlural()
    {
        $html = fbt(
            fbt::plural('cat', 7, ['name' => 'cat_token', 'showCount' => 'yes']) .
            ' and ' .
            fbt::plural('dog', 6, ['name' => 'dog_token', 'showCount' => 'yes']),
            'plurals',
            [
                'transform' => false,
            ]
        );

        $this->assertSame('<fbt desc="plurals"><fbt:plural count="7" name="cat_token" showCount="yes">cat</fbt:plural> and <fbt:plural count="6" name="dog_token" showCount="yes">dog</fbt:plural></fbt>', (string)$html);
    }

    public function testPronoun()
    {
        $html = fbt(
            'I know' .
            fbt::pronoun('object', 2) .
            '.',
            'object pronoun',
            [
                'transform' => false,
            ]
        );

        $this->assertSame('<fbt desc="object pronoun">I know<fbt:pronoun type="object" gender="2"/>.</fbt>', (string)$html);
    }

    public function testSameParam()
    {
        $html = fbt([
            fbt::param('name1', 'abc'),
            ' and ',
            fbt::sameParam('name1'),
        ], 'd', [
            'transform' => false,
        ]);

        $this->assertSame('<fbt desc="d"><fbt:param name="name1">abc</fbt:param> and <fbt:same-param name="name1"/></fbt>', (string)$html);
    }

    public function testSubject()
    {
        $html = fbt(['Foo'], 'Bar', [
            'subject' => 2,
            'transform' => false,
        ]);

        $this->assertSame('<fbt desc="Bar" subject="2">Foo</fbt>', (string)$html);
    }

    public function testNestedFbt()
    {
        $enum = [
            'LINK' => "link",
            'PAGE' => "page",
            'PHOTO' => "photo",
            'POST' => "post",
            'VIDEO' => "video",
        ];

        $html = fbt([
            fbt::param('name', 'Mark'),
            ' has a ',
            createElement('strong', fbt::enum('LINK', $enum)),
            ' to share!',
        ], 'Example enum', [
            'transform' => false,
        ]);

        $this->assertSame(implode('', [
            '<fbt desc="Example enum">',
                '<fbt:param name="name">Mark</fbt:param>',
                ' has a ',
                '<strong><fbt:enum enum-range="{&quot;LINK&quot;:&quot;link&quot;,&quot;PAGE&quot;:&quot;page&quot;,&quot;PHOTO&quot;:&quot;photo&quot;,&quot;POST&quot;:&quot;post&quot;,&quot;VIDEO&quot;:&quot;video&quot;}" value="LINK"/></strong>',
                ' to share!',
             '</fbt>',
        ]), (string)$html);
    }
}
