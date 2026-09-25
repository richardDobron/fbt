<?php

namespace tests\runtime;

use fbt\FbtConfig;

use function fbt\intlList;

use fbt\Lib\IntlNumberType;
use fbt\Lib\IntlViewerContext;
use fbt\Runtime\FbtTranslations;
use fbt\Runtime\Gender;
use fbt\Runtime\GenderConst;
use fbt\Runtime\Shared\escapeRegex;
use fbt\Runtime\Shared\fbs;
use fbt\Runtime\Shared\fbt;
use fbt\Runtime\Shared\FbtHooks;
use fbt\Transform\FbtTransform\FbtTransform;
use fbt\Transform\FbtTransform\Translate\IntlVariations;

/**
 * Runtime behaviors aligned with upstream fbt, and ported upstream tests
 * (runtime/shared/__tests__/escapeRegex-test.js, runtime/nonfb/__tests__/FbtTranslations-test.js)
 */
class runtimeParityTest extends \tests\TestCase
{
    /** @var fbt */
    private $fbtRuntime;

    public function setUp(): void
    {
        parent::setUp();

        FbtConfig::set('collectFbt', false);
        FbtHooks::locale('en_US');
        $this->fbtRuntime = new fbt();
        fbt::_purgeCache();
    }

    protected function tearDown(): void
    {
        foreach (['getTranslatedInput', 'getViewerContext', 'getFbtResult', 'logImpression'] as $hook) {
            FbtHooks::unregister($hook);
        }
        FbtHooks::locale(null);
        FbtHooks::inlineMode('NO_INLINE');
        FbtTranslations::registerTranslations([]);
        FbtConfig::set('collectFbt', true);
        FbtConfig::set('debug', false);
        fbt::_purgeCache();

        parent::tearDown();
    }

    // escapeRegex-test.js

    public function testEscapesIndividualSpecialCharacters()
    {
        foreach (['.', '\\', '[', ']', '(', ')', '{', '}', '^', '$', '-', '|', '?', '*', '+'] as $char) {
            $this->assertSame('\\' . $char, escapeRegex::escapeRegex($char));
        }
    }

    public function testDoesntChangeCharactersThatHaveEscapeSequences()
    {
        foreach (["\n", "\t", "\x08", "\f", "\v", "\r", "\0"] as $char) {
            $this->assertSame($char, escapeRegex::escapeRegex($char));
        }
    }

    public function testEscapesMultipleSpecialCharacters()
    {
        $this->assertSame('hello\\? good\\-bye\\.\\.\\.', escapeRegex::escapeRegex('hello? good-bye...'));
        $this->assertSame('1 \\+ 1 \\* 3 \\- 2 = 2', escapeRegex::escapeRegex('1 + 1 * 3 - 2 = 2'));
        $this->assertSame('\\[\\]\\{\\}\\(\\)', escapeRegex::escapeRegex('[]{}()'));
    }

    public function testDoesntChangeNonSpecialCharacters()
    {
        foreach (['ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz', '0123456789', '~`!@#%&_=:;"\'<>,/'] as $chars) {
            $this->assertSame($chars, escapeRegex::escapeRegex($chars));
        }
    }

    // FbtTranslations-test.js

    public function testTranslationsAreMerged()
    {
        FbtTranslations::registerTranslations(['en_US' => ['h1' => 'A', 'h2' => 'B']]);
        FbtTranslations::mergeTranslations(['en_US' => ['h2' => 'C', 'h3' => 'D'], 'sk_SK' => ['h1' => 'E']]);

        $this->assertSame([
            'en_US' => ['h1' => 'A', 'h2' => 'C', 'h3' => 'D'],
            'sk_SK' => ['h1' => 'E'],
        ], FbtTranslations::getRegisteredTranslations());
    }

    public function testGetTranslatedInput()
    {
        FbtTranslations::registerTranslations(['en_US' => ['hk1' => 'Translated', 'hk2' => '']]);

        $this->assertSame(
            ['table' => 'Translated', 'args' => null],
            FbtTranslations::getTranslatedInput(['table' => 'Source', 'args' => null, 'options' => ['hk' => 'hk1']])
        );
        // an empty translation is a translation
        $this->assertSame(
            ['table' => '', 'args' => null],
            FbtTranslations::getTranslatedInput(['table' => 'Source', 'args' => null, 'options' => ['hk' => 'hk2']])
        );
        $this->assertNull(FbtTranslations::getTranslatedInput(['table' => 'Source', 'args' => null, 'options' => ['hk' => 'hk3']]));
        $this->assertNull(FbtTranslations::getTranslatedInput(['table' => 'Source', 'args' => null, 'options' => null]));
    }

    // fbt._()

    public function testNullOptions()
    {
        $this->assertSame('Hello', (string)$this->fbtRuntime->_('Hello', null, null));
    }

    public function testResultsWithoutSubstitutionsAreMemoized()
    {
        $result = $this->fbtRuntime->_('Memoized string', null);

        $this->assertSame($result, fbt::_getCachedFbt('Memoized string'));
        $this->assertSame($result, $this->fbtRuntime->_('Memoized string', null));
    }

    public function testTableWithoutArgs()
    {
        $this->expectExceptionMessage('Table access did not result in string: {"*":"a","_1":"b"}, Type: array');

        $this->fbtRuntime->_(['*' => 'a', '_1' => 'b'], null);
    }

    public function testFloatParamsAreFormattedLikeJs()
    {
        $this->assertSame('1000000000000000', (string)$this->fbtRuntime->_('{n}', [fbt::_param('n', 1e15)]));
        $this->assertSame('0.30000000000000004', (string)$this->fbtRuntime->_('{n}', [fbt::_param('n', 0.1 + 0.2)]));
    }

    public function testPluralLabelZero()
    {
        $this->assertSame(
            '2 items',
            (string)$this->fbtRuntime->_(['*' => '{0} items', '_1' => '1 item'], [fbt::_plural(2, '0')])
        );
    }

    public function testIntlListItemsAreValidatedInDebugMode()
    {
        FbtConfig::set('debug', true);
        $this->expectExceptionMessage('Must provide a string or an fbt result to intlList.');

        intlList(['a', 1]);
    }

    public function testFbsRejectsRichContents()
    {
        try {
            FbtTransform::transform('<fbs desc="some desc">Hello <fbs:param name="name"><strong>world!</strong></fbs:param></fbs>');
            $this->fail('Expected exception');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('instead we got an HTML element', $e->getMessage());
        }

        $this->expectExceptionMessage('instead we got an HTML element');
        FbtTransform::transform('<fbs desc="some desc">Hello <strong>world!</strong></fbs>');
    }

    // FbtHooks

    public function testHooksCanBeRegisteredAtOnce()
    {
        FbtHooks::register([
            'getTranslatedInput' => function (array $input) {
                return ['table' => 'Hooked', 'args' => null];
            },
        ]);

        $this->assertSame('Hooked', (string)$this->fbtRuntime->_('Source', null));
    }

    public function testViewerContextHook()
    {
        $viewerContext = new class () extends IntlViewerContext {
            public function getLocale(): string
            {
                return 'sk_SK';
            }
        };
        FbtHooks::locale(null);
        FbtHooks::register('getViewerContext', function () use ($viewerContext) {
            return $viewerContext;
        });

        $this->assertSame('sk_SK', FbtHooks::locale());
    }

    public function testFbtInit()
    {
        \fbt\fbtInit([
            'translations' => ['en_US' => ['h' => 'x']],
            'hooks' => ['logImpression' => function () {
            }],
        ]);

        $this->assertSame(['en_US' => ['h' => 'x']], FbtTranslations::getRegisteredTranslations());
    }

    // Constants

    public function testRuntimeConstants()
    {
        $this->assertSame(28, \fbt\Lib\IntlVariations::BITMASK_NUMBER);
        $this->assertSame(3, \fbt\Lib\IntlVariations::BITMASK_GENDER);
        $this->assertSame(
            [16, 4, 8, 20, 12, 24],
            [\fbt\Lib\IntlVariations::NUMBER_ZERO, \fbt\Lib\IntlVariations::NUMBER_ONE, \fbt\Lib\IntlVariations::NUMBER_TWO, \fbt\Lib\IntlVariations::NUMBER_FEW, \fbt\Lib\IntlVariations::NUMBER_MANY, \fbt\Lib\IntlVariations::NUMBER_OTHER]
        );
        $this->assertSame(Gender::GENDER_CONST['UNKNOWN_PLURAL'], GenderConst::UNKNOWN_PLURAL);
        $this->assertSame(
            get_class(IntlNumberType::getNumberModuleForLang('sk')),
            get_class(IntlNumberType::forLanguage('sk'))
        );
    }

    /**
     * fbt-test.js: given a string with implicit parameters (the snapshots as HTML)
     */
    public function implicitParamsProvider(): array
    {
        $cases = [];
        $viewers = [
            ['Bob', \fbt\Lib\IntlVariations::GENDER_MALE],
            ['Betty', \fbt\Lib\IntlVariations::GENDER_FEMALE],
            ['Kim', \fbt\Lib\IntlVariations::GENDER_UNKNOWN],
        ];
        $owners = ['FEMALE_SINGULAR' => 'her', 'MALE_SINGULAR' => 'his', 'UNKNOWN_PLURAL' => 'their'];

        foreach ($viewers as [$name, $gender]) {
            foreach ($owners as $ownerGender => $pronoun) {
                foreach (['photo', 'comment'] as $object) {
                    foreach ([1, 10] as $count) {
                        $cases["$name, $ownerGender, $object, $count"] = [
                            $name,
                            $gender,
                            Gender::GENDER_CONST[$ownerGender],
                            $object,
                            $count,
                            "$name clicked on <strong>$pronoun<a href=\"#link\">$object</a></strong> " .
                            '<em>' . $count . ($count === 1 ? ' time' : ' times') . '</em>',
                        ];
                    }
                }
            }
        }

        return $cases;
    }

    /**
     * @dataProvider implicitParamsProvider
     */
    public function testImplicitParams(string $name, int $viewerGender, int $ownerGender, string $object, int $count, string $expected)
    {
        FbtConfig::set('collectFbt', true);

        $this->assertSame($expected, (string)fbt([
            \fbt\fbt::name('name', $name, $viewerGender),
            ' clicked on ',
            '<strong>',
            \fbt\fbt::pronoun('possessive', $ownerGender),
            '<a href="#link">',
            \fbt\fbt::enum($object, ['photo' => 'photo', 'comment' => 'comment']),
            '</a>',
            '</strong> ',
            '<em>',
            \fbt\fbt::plural('time', $count, ['showCount' => 'yes']),
            '</em>',
        ], 'description'));
    }

    // fbs-test.js, fbt-test.js

    public function testFbsWithNestedFbs()
    {
        FbtConfig::set('collectFbt', true);

        $this->assertSame('Hello world', (string)fbs(['Hello ', \fbt\fbs::param('name', fbs('world', 'param text'))], 'some desc'));
        $this->assertSame(
            'Hello world',
            FbtTransform::transform('<fbs desc="some desc">Hello <fbs:param name="name"><fbs desc="param text">world</fbs></fbs:param></fbs>')
        );
    }

    public function testFbsPluralWithValue()
    {
        FbtConfig::set('collectFbt', true);

        $this->assertSame('I have three dreams.', (string)fbs([
            'I have ',
            \fbt\fbs::plural('a dream', 3, [
                'many' => 'dreams',
                'showCount' => 'yes',
                'value' => fbs('three', 'custom UI value'),
            ]),
            '.',
        ], 'desc'));
    }

    public function testCommonStrings()
    {
        FbtConfig::set('collectFbt', true);
        FbtConfig::set('fbtCommon', ['Accept' => 'Button/Link: Accept conditions']);

        try {
            $this->assertSame((string)fbt('Accept', 'Button/Link: Accept conditions'), (string)\fbt\fbt::c('Accept'));
        } finally {
            FbtConfig::set('fbtCommon', []);
        }
    }

    public function testNumericValues()
    {
        FbtConfig::set('collectFbt', true);

        $this->assertSame('A total amount is 10,000', (string)fbt('A total amount is ' . \fbt\fbt::param('count', 10000, ['number' => true]), 'Test string'));
        $this->assertSame('A total amount is 10000', (string)fbt('A total amount is ' . \fbt\fbt::param('count', 10000), 'Test string'));
    }

    public function testFbsRuntime()
    {
        $this->assertSame('Hello', (string)(new fbs())->_('Hello', null, null));
    }
}
