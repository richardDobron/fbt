<?php

namespace tests\transform;

use fbt\Exceptions\FbtParserException;
use fbt\fbs;
use fbt\fbt;
use fbt\FbtConfig;
use fbt\Runtime\Shared\FbtHooks;
use fbt\Transform\FbtTransform\FbtCallExpression;
use fbt\Transform\FbtTransform\FbtExpression;
use fbt\Transform\FbtTransform\FbtTransform;

/**
 * fbt constructs of the functional form are call expressions (the equivalent of the
 * babel nodes of upstream fbt), whose runtime values are expressions.
 */
class callExpressionTest extends \tests\TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        FbtConfig::set('collectFbt', false);
        FbtHooks::locale('en_US');
        fbt::_purgeCache();
    }

    protected function tearDown(): void
    {
        FbtHooks::locale(null);
        FbtConfig::set('collectFbt', true);
        FbtConfig::set('fbtCommon', []);
        fbt::_purgeCache();

        parent::tearDown();
    }

    private static function compiledCount(): int
    {
        $compiled = new \ReflectionProperty(FbtTransform::class, 'compiled');
        $compiled->setAccessible(true);

        return count($compiled->getValue());
    }

    public function testConstructsAreCallExpressions()
    {
        $param = fbt::param('name', 'Bob', ['gender' => 1]);

        $this->assertInstanceOf(FbtCallExpression::class, $param);
        $this->assertSame('fbt', $param->moduleName);
        $this->assertSame('param', $param->name);
        $this->assertSame('name', $param->arguments[0]);
        $this->assertInstanceOf(FbtExpression::class, $param->arguments[1]);
        $this->assertSame('Bob', $param->arguments[1]->value);
        $this->assertSame(1, $param->arguments[2]['gender']->value);

        $this->assertSame('fbs', fbs::plural('cat', 2)->moduleName);
    }

    public function testCallsiteIsCompiledOnceForAllValues()
    {
        $results = [];
        foreach ([['Ann', 1], ['Bob', 5], ['<b>Tom & Jerry</b>', 0]] as [$name, $count]) {
            $results[] = (string)fbt([
                'Hi ',
                fbt::param('name', $name),
                ', you have ',
                fbt::plural('a photo', $count, ['many' => 'photos', 'showCount' => 'ifMany']),
                '.',
            ], 'greeting');
        }

        $this->assertSame([
            'Hi Ann, you have a photo.',
            'Hi Bob, you have 5 photos.',
            'Hi <b>Tom & Jerry</b>, you have 0 photos.',
        ], $results);
        $this->assertSame(1, self::compiledCount());
    }

    public function testConstructsConcatenatedWithStrings()
    {
        $results = [];
        foreach ([1, 7] as $count) {
            $results[] = (string)fbt(
                'I have ' . \fbt\createElement('a', fbt::plural('cat', $count, ['showCount' => 'yes']), ['href' => '#']) . ' now',
                'implicit param'
            );
        }

        $this->assertSame(['I have <a href="#">1 cat</a> now', 'I have <a href="#">7 cats</a> now'], $results);
        $this->assertSame(1, self::compiledCount());
    }

    public function testNestedFbtAsParamValue()
    {
        $this->assertSame(
            'Hi inner &',
            (string)fbt(['Hi ', fbt::param('p', fbt('inner &', 'inner'))], 'outer')
        );
    }

    public function testNestedConstructsError()
    {
        $this->expectException(FbtParserException::class);
        $this->expectExceptionMessage(
            'Expected fbt constructs to not nest inside fbt constructs, but found fbt.param nest inside fbt.plural'
        );

        (string)fbt(['a ', fbt::plural('cat ' . fbt::param('y', 1), 2)], 'd');
    }

    public function testConstructOfAnotherModuleError()
    {
        $this->expectException(FbtParserException::class);
        $this->expectExceptionMessage('fbs: unsupported node: ' . FbtCallExpression::class);

        (string)fbs(['a ', fbt::param('x', 'y')], 'd');
    }

    public function testConstructWithinHtmlDocument()
    {
        $this->assertSame(
            '<p>Hello Bob!</p>',
            FbtTransform::transform('<p><fbt desc="d">Hello ' . fbt::param('name', 'Bob') . '!</fbt></p>')
        );
    }

    public function testCommonStrings()
    {
        FbtConfig::set('fbtCommon', ['Photo' => 'A still image']);

        $this->assertSame('Photo', (string)fbt::c('Photo'));
        $this->assertSame('Photo', (string)fbs('Photo', ['common' => true]));
        $this->assertSame('Photo', (string)fbt('Photo', 'custom description', ['common' => true]));
    }
}
