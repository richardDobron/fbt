<?php

namespace tests\transform;

use fbt\Transform\FbtTransform\fbtHash;
use fbt\Transform\FbtTransform\JSFbtUtil;
use fbt\Transform\FbtTransform\Utils\AddLeafToTree;
use fbt\Util\JsJson;

/**
 * The expected hash keys are asserted by the upstream tests
 * (packages/babel-plugin-fbt-runtime/__tests__/fbtRuntime-test.js).
 */
class fbtHashKeyTest extends \tests\TestCase
{
    /**
     * @dataProvider hashKeyProvider
     */
    public function testFbtHashKey(array $jsfbt, string $expected)
    {
        $this->assertSame($expected, fbtHash::fbtHashKey($jsfbt));
    }

    public function hashKeyProvider(): array
    {
        return [
            'text' => [['desc' => 'Bar', 'text' => 'Foo'], '3ktBJ2'],
            'enum' => [
                [
                    'a' => ['desc' => 'Bar', 'text' => 'Foo A'],
                    'b' => ['desc' => 'Bar', 'text' => 'Foo B'],
                    'c' => ['desc' => 'Bar', 'text' => 'Foo C'],
                ],
                'NT3sR',
            ],
            'multiline param name' => [['desc' => 'd', 'text' => '{two lines} test'], '2xRGl8'],
            'simple' => [['desc' => 'test', 'text' => 'simple'], '2pjKFw'],
            'plural with token aliases' => [
                [
                    '*' => ['desc' => 'd', 'text' => '{=Your} friends {=shared}{number} photos', 'tokenAliases' => ['=Your' => '=m0', '=shared' => '=m2']],
                    '_1' => ['desc' => 'd', 'text' => '{=Your} friends {=shared} a photo', 'tokenAliases' => ['=Your' => '=m0', '=shared' => '=m2']],
                ],
                '2mDoBt',
            ],
            'inner string with different descs' => [
                [
                    '*' => ['desc' => 'In the phrase: "{=Your} friends {=shared}{number} photos"', 'text' => 'Your'],
                    '_1' => ['desc' => 'In the phrase: "{=Your} friends {=shared} a photo"', 'text' => 'Your'],
                ],
                '3AIVHA',
            ],
            'outer string' => [
                [
                    '*' => ['desc' => 'd', 'text' => 'I wrote {=[number] inner strings}', 'tokenAliases' => ['=[number] inner strings' => '=m1']],
                    '_1' => ['desc' => 'd', 'text' => 'I wrote {=an inner string}', 'tokenAliases' => ['=an inner string' => '=m1']],
                ],
                'fglLv',
            ],
            'inner string' => [
                [
                    '*' => ['desc' => 'In the phrase: "I wrote {=[number] inner strings}"', 'text' => '{number} inner strings'],
                    '_1' => ['desc' => 'In the phrase: "I wrote {=an inner string}"', 'text' => 'an inner string'],
                ],
                '2HM8na',
            ],
        ];
    }

    public function testJsJsonFollowsTheJsPropertyOrder()
    {
        $this->assertSame(
            '{"0":{"x":1},"1":"b","2":"c","*":"a","_1":"d"}',
            JsJson::stringify(['*' => 'a', 1 => 'b', 2 => 'c', 0 => ['x' => 1], '_1' => 'd'])
        );
        $this->assertSame('{"0":"a","1":"b"}', JsJson::stringify(['a', 'b']));
        $this->assertSame('{"a":"</ü >"}', str_replace("\u{2028}", ' ', JsJson::stringify(['a' => "</ü\u{2028}>"])));
        $this->assertSame(
            '{"0":"a","1":"b"}',
            json_encode(JsJson::toJsObject(['1' => 'b', 0 => 'a']))
        );
    }

    public function testMapLeaves()
    {
        $tree = [
            '*' => ['desc' => 'd', 'text' => 'many'],
            '_1' => ['desc' => 'd', 'text' => 'one'],
        ];

        $this->assertSame(['*' => 'many', '_1' => 'one'], JSFbtUtil::mapLeaves($tree, function (array $leaf) {
            return $leaf['text'];
        }));
        $this->assertSame('text', JSFbtUtil::mapLeaves(['desc' => 'd', 'text' => 'text'], function (array $leaf) {
            return $leaf['text'];
        }));
    }

    public function testAddLeafToTree()
    {
        $tree = [];
        AddLeafToTree::addLeafToTree($tree, ['a', 'b', 'c'], ['val' => 111]);
        AddLeafToTree::addLeafToTree($tree, ['a', 'b', 'd'], ['val' => 222]);

        $this->assertSame(['a' => ['b' => ['c' => ['val' => 111], 'd' => ['val' => 222]]]], $tree);

        $this->expectExceptionMessage('Overwriting an existing tree leaf is not allowed. keys=`["a","b","c"]`');
        AddLeafToTree::addLeafToTree($tree, ['a', 'b', 'c'], ['val' => 333]);
    }
}
