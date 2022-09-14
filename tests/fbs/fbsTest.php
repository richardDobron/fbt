<?php

declare(strict_types=1);

namespace tests\fbs;

use fbt\Exceptions\FbtParserException;
use fbt\FbtConfig;
use fbt\Transform\FbtTransform\FbtTransform;

class fbsTest extends \tests\TestCase
{
    private static function transform($document): string
    {
        return FbtTransform::transform($document);
    }

    public function testFbsFunction()
    {
        FbtConfig::set('fbtCommonPath', __DIR__ . '/commonStrings.php');

        $html = fbs('Accept', [
            'common' => true,
        ]);

        $this->assertSame('Accept', $html);
    }

    public function testConvertASimpleString()
    {
        $fbs = <<<FBT
<fbs desc='str_description'>a simple string</fbs>
FBT;

        $this->assertSame('a simple string', self::transform($fbs));
    }

    public function testConvertAStringWithAParameter()
    {
        $fbs = <<<FBT
<fbs desc='str_description'>
    a string with a
    <fbs:param name="param name">parameter</fbs:param>
</fbs>
FBT;

        $this->assertSame('a string with a parameter', self::transform($fbs));
    }

    public function testConvertACommonString()
    {
        FbtConfig::set('fbtCommon', [
            'Post' => 'Button to post a comment',
        ]);

        $fbs = <<<FBT
<fbs common="true">Post</fbs>
FBT;

        $this->assertSame('Post', self::transform($fbs));
    }

    public function testConvertACommonStringWithCommonPath()
    {
        FbtConfig::set('fbtCommonPath', __DIR__ . '/commonStrings.php');

        $fbs = <<<FBT
<fbs common="true">Accept</fbs>
FBT;

        $this->assertSame('Accept', self::transform($fbs));
    }

    public function testConvertACommonStringWithJsonCommonPath()
    {
        FbtConfig::set('fbtCommonPath', __DIR__ . '/commonStrings.json');

        $fbs = <<<FBT
<fbs common="true">Use the form below to see FBT in action.</fbs>
FBT;

        $this->assertSame('Use the form below to see FBT in action.', self::transform($fbs));

        $fbs = \fbt\fbt::c('Use the form below to see FBT in action.');

        $this->assertSame('Use the form below to see FBT in action.', self::transform($fbs));
    }

    public function testNestedFbsInFbs()
    {
        $this->expectException(FbtParserException::class);
        $fbs = <<<FBT
<fbs desc="str_description">
    a simple string
    <fbs desc="test">nested</fbs>
</fbs>
FBT;

        $this->assertSame('Post', self::transform($fbs));
    }

    public function testUnknownCommonStringError()
    {
        $this->expectExceptionMessage("Unknown string \"basic\" for");
        $fbs = <<<FBT
<fbs common="true">
    basic
</fbs>
FBT;

        $this->assertSame('Post', self::transform($fbs));
    }

    public function testNestedFbtInFbs()
    {
        $this->expectExceptionMessage("Don't put <fbt> directly within <fbs>.");
        self::transform(
            <<<FBT
<fbs desc='str_description'>
    a simple string
    <fbt desc="test">nested</fbt>
</fbs>
FBT
        );
    }

    public function testFbtParamInFbs()
    {
        $this->expectExceptionMessage("Don't mix <fbt> and <fbs> HTML namespaces.");
        self::transform(
            <<<FBT
<fbs desc='str_description'>
    a simple string
    <fbt:param name="param name">parameter</fbt:param>
</fbs>
FBT
        );
    }
}
