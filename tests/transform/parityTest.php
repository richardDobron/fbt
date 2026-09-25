<?php

namespace tests\transform;

use fbt\FbtConfig;
use fbt\Lib\FbtQTOverrides;
use fbt\Runtime\FbtTranslations;
use fbt\Runtime\Shared\FbtHooks;
use fbt\Transform\FbtTransform\FbtTransform;

/**
 * Behaviors aligned with upstream fbt (compiler and functional API)
 */
class parityTest extends \tests\TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        FbtHooks::locale('en_US');
        \fbt\fbt::_purgeCache();
        FbtTransform::$phrases = [];
        FbtTransform::$childToParent = [];
    }

    protected function tearDown(): void
    {
        FbtHooks::unregister('logImpression');
        FbtHooks::locale(null);
        FbtQTOverrides::$overrides = [];
        FbtTranslations::registerTranslations([]);
        FbtTransform::$phrases = [];
        FbtTransform::$childToParent = [];

        parent::tearDown();
    }

    private static function leaves(): array
    {
        return array_column(array_column(FbtTransform::$phrases, 'jsfbt'), 't');
    }

    public function testFunctionalAttributesAreNotHtmlEncoded()
    {
        $this->assertSame(
            'Hi Bob at café',
            (string)fbt('Hi ' . \fbt\fbt::param("user's name", 'Bob') . ' at café', 'Greeting for "café" visitors')
        );
        $this->assertSame(
            ['desc' => 'Greeting for "café" visitors', 'text' => "Hi {user's name} at café"],
            self::leaves()[0]
        );

        // The tag form collects the same phrase (attribute values are decoded like in JSX)
        FbtTransform::$phrases = [];
        FbtTransform::transform('<fbt desc="Greeting for &quot;café&quot; visitors">Hi <fbt:param name="user\'s name">Bob</fbt:param> at café</fbt>');
        $this->assertSame(
            ['desc' => 'Greeting for "café" visitors', 'text' => "Hi {user's name} at café"],
            self::leaves()[0]
        );
    }

    public function testFunctionalValuesAreNotParsedAsHtml()
    {
        $this->assertSame(
            'Hello <b>Sarah</b> <i>Smith</i>!',
            (string)fbt('Hello ' . \fbt\fbt::name('name', '<b>Sarah</b> <i>Smith</i>', 2) . '!', 'names')
        );
        $this->assertSame(
            'Value: <fbt:param name="x">y</fbt:param>',
            (string)fbt('Value: ' . \fbt\fbt::param('v', '<fbt:param name="x">y</fbt:param>'), 'raw value')
        );
    }

    public function testNestedFbtAsParamValueIsCollectedAfterItsEnclosingFbt()
    {
        $this->assertSame(
            'Outer: Inner <b>text</b>',
            (string)fbt('Outer: ' . \fbt\fbt::param('inner', fbt(['Inner ', '<b>text</b>'], 'inner desc')), 'outer desc')
        );
        // the inner fbt is an expression, rendered when the runtime call is created
        $this->assertSame(
            ['Outer: {inner}', 'Inner {=text}', 'text'],
            array_column(self::leaves(), 'text')
        );
    }

    public function testRuntimeIsExecutedOnEveryRender()
    {
        $impressions = 0;
        FbtHooks::register('logImpression', function () use (&$impressions) {
            $impressions++;
        });
        // impressions are logged for translated strings (with a hash)
        FbtHooks::register('getTranslatedInput', function (array $input) {
            return ['table' => ['Impression', 'someHash'], 'args' => $input['args']];
        });

        try {
            for ($i = 0; $i < 3; $i++) {
                (string)fbt('Impression', 'logged');
            }
        } finally {
            FbtHooks::unregister('getTranslatedInput');
        }

        $this->assertSame(3, $impressions);
    }

    public function testCompiledCallsitesDontDependOnValues()
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->assertSame(
                "You have $i new messages",
                (string)fbt('You have ' . \fbt\fbt::param('count', $i) . ' new messages', 'count')
            );
        }

        // collected once
        $this->assertCount(1, FbtTransform::$phrases);
    }

    public function testNestedBoldElementsAreNotClosedImplicitly()
    {
        FbtTransform::transform('<fbt desc="d">This is <b>an inner string and <b>another inner string</b></b></fbt>');

        $this->assertSame(
            ['This is {=an inner string and another inner string}', 'an inner string and {=another inner string}', 'another inner string'],
            array_column(self::leaves(), 'text')
        );
        $this->assertSame([1 => 0, 2 => 1], FbtTransform::$childToParent);
    }

    public function testCommentsAndWhitespaceInConstructs()
    {
        $this->assertSame(
            '2 cats',
            FbtTransform::transform('<fbt desc="d"><fbt:plural count="2" showCount="yes">cat<!-- c --> </fbt:plural></fbt>')
        );
        $this->assertSame(
            'Hi Bob',
            FbtTransform::transform('<fbt desc="d">Hi <fbt:name name="n" gender="1">Bob<!-- c --> </fbt:name></fbt>')
        );
    }

    public function testParamNamesAreNormalizedOnlyWhenTheySpanMultipleLines()
    {
        // the text is normalized (tokens included) unless preserveWhitespace is set
        FbtTransform::transform('<fbt desc="d" preserveWhitespace="true">A <fbt:param name="a  b">x</fbt:param> <fbt:same-param name="a  b"/></fbt>');
        FbtTransform::transform("<fbt desc=\"d\" preserveWhitespace=\"true\">B <fbt:param name=\"c\n   d\">x</fbt:param></fbt>");
        (string)fbt('C ' . \fbt\fbt::param("e\n f", 'x'), 'd', ['preserveWhitespace' => true]);
        FbtTransform::transform('<fbt desc="d">D <fbt:param name="g  h">x</fbt:param></fbt>');

        $this->assertSame(['A {a  b} {a  b}', 'B {c d}', "C {e\n f}", 'D {g h}'], array_column(self::leaves(), 'text'));
    }

    public function testEmptyParam()
    {
        // an empty value of the functional form is tested by fbtTest::testEmptyParameter
        $this->expectExceptionMessage('fbt:param expects an {expression} or HTML element, and only one');
        FbtTransform::transform('<fbt desc="d">Hi <fbt:param name="x"></fbt:param></fbt>');
    }

    public function testEnumValuesAndAttributes()
    {
        $this->assertSame(
            'Tom &amp; Jerry',
            FbtTransform::transform('<fbt desc="d"><fbt:enum enum-range=\'{"a":"Tom &amp;amp; Jerry"}\' value="a" foo="ignored"/></fbt>')
        );
        $this->assertSame(
            "Rock 'n' roll",
            (string)fbt(\fbt\fbt::enum("it's", ["it's" => "Rock 'n' roll"]), 'd')
        );
    }

    public function testConstructOutsideOfFbt()
    {
        $this->expectExceptionMessage('Fbt constructs can only be used within the scope of an fbt string.');

        FbtTransform::transform('<div><fbt:param name="x">y</fbt:param></div>');
    }

    public function testPronounBooleanOptions()
    {
        $this->assertSame(
            'Her birthday',
            (string)fbt(\fbt\fbt::pronoun('possessive', 1, ['capitalize' => true, 'human' => true]) . ' birthday', 'd')
        );
    }

    public function testFbsLocationAndDocblock()
    {
        fbs('A string', 'desc');
        (string)fbs('An fbs string', 'desc');
        $line = __LINE__ - 1;

        $this->assertSame('tests/transform/parityTest.php', FbtTransform::$phrases[0]['filepath']);
        $this->assertSame($line, FbtTransform::$phrases[0]['line_beg']);
    }

    public function testCommonStrings()
    {
        FbtConfig::set('fbtCommon', ['Done' => 'The action is done']);

        try {
            $this->assertSame('Done', (string)\fbt\fbt::c('Done'));
            $this->assertSame('The action is done', self::leaves()[0]['desc']);

            // the functional form keeps its description
            $this->assertSame('Done', (string)fbt('Done', 'Button label', ['common' => true]));
            $this->assertSame('Button label', self::leaves()[1]['desc']);
        } finally {
            FbtConfig::set('fbtCommon', []);
        }
    }

    public function testValuelessAttributeBeforeSelfClosingEnd()
    {
        $this->assertSame(
            'Is she here',
            FbtTransform::transform('<fbt desc="d">Is <fbt:pronoun type="subject" gender="1" human/> here</fbt>')
        );
    }

    public function testErrorsContainTheLineOfTheCallsite()
    {
        try {
            (string)fbt('x', 'd', ['foo' => 'bar']);
            $this->fail('Expected exception');
        } catch (\fbt\Exceptions\FbtParserException $e) {
            $this->assertStringStartsWith(
                'Line ' . (__LINE__ - 4) . ': Invalid option "foo". Only allowed: author, common, doNotExtract, preserveWhitespace, project, subject',
                $e->getMessage()
            );
        }
    }

    public function testDocblockOptionsFromTheFirstComment()
    {
        $file = sys_get_temp_dir() . '/fbt-docblock-' . uniqid() . '.php';
        file_put_contents($file, "<?php\n// @fbt {\"project\": \"line comment\"}\n");

        try {
            $fbt = fbt('Docblock project', 'd');
            $fbt->_trace(['file' => $file, 'line' => 2]);
            (string)$fbt;
        } finally {
            unlink($file);
        }

        $this->assertSame('line comment', FbtTransform::$phrases[0]['project']);
    }

    public function testDocblockOptionsAreTypeChecked()
    {
        $file = sys_get_temp_dir() . '/fbt-docblock-' . uniqid() . '.php';
        file_put_contents($file, "<?php\n/** @fbt {\"project\": true} */\n");

        try {
            $fbt = fbt('Docblock type', 'd');
            $fbt->_trace(['file' => $file, 'line' => 2]);
            // upstream: '%sExpected string value instead of %s (%s)'
            $this->expectExceptionMessage('Expected string value instead of true (bool)');
            (string)$fbt;
        } finally {
            unlink($file);
        }
    }

    public function testFbtCommonPathErrors()
    {
        FbtConfig::set('fbtCommonPath', __DIR__ . '/missing-common-strings.php');

        try {
            $this->expectExceptionMessage("Please double check your fbtCommonPath setting.");
            (string)fbt('x', 'd');
        } finally {
            FbtConfig::set('fbtCommonPath', null);
        }
    }

    public function testPhrasesOfDifferentProjectsAreStored()
    {
        $path = sys_get_temp_dir() . '/fbt-projects-' . uniqid();
        mkdir($path);
        FbtConfig::set('path', $path);

        try {
            (string)fbt('Same text', 'same desc', ['project' => 'p1']);
            (string)fbt('Same text', 'same desc', ['project' => 'p2']);
            FbtHooks::storePhrases();

            $sourceStrings = json_decode(file_get_contents($path . '/.source_strings.json'), true);
            $this->assertSame(['p1', 'p2'], array_column($sourceStrings['phrases'], 'project'));
        } finally {
            @unlink($path . '/.source_strings.json');
            @rmdir($path);
            FbtConfig::set('path', self::storagePath());
        }
    }

    public function testMergedTranslationsKeepNumericHashes()
    {
        FbtTranslations::registerTranslations(['sk_SK' => ['123456' => 'A']]);
        FbtTranslations::mergeTranslations(['sk_SK' => ['abc' => 'B', '789' => 'C']]);

        $this->assertSame(
            ['123456' => 'A', 'abc' => 'B', '789' => 'C'],
            FbtTranslations::getRegisteredTranslations()['sk_SK']
        );
    }
}
