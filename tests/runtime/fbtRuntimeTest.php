<?php

namespace tests\runtime;

use fbt\Exceptions\FbtException;
use fbt\FbtConfig;
use fbt\Lib\FbtQTOverrides;
use fbt\Runtime\Gender;
use fbt\Runtime\Shared\fbs;
use fbt\Runtime\Shared\fbt;
use fbt\Runtime\Shared\FbtHooks;
use fbt\Runtime\Shared\FbtPureStringResult;
use fbt\Runtime\Shared\FbtResult;
use fbt\Runtime\Shared\FbtResultBase;
use fbt\Runtime\Shared\IFbtErrorListener;
use fbt\Runtime\Shared\intlNumUtils;
use fbt\Transform\FbtTransform\Translate\IntlVariations;

/**
 * Port of the runtime tests from facebook/fbt v1.0.0:
 * runtime/shared/__tests__/fbt-test.js, fbs-test.js, FbtResult-test.js
 * runtime/nonfb/__tests__/fbt-runtime-test.js
 */
class fbtRuntimeTest extends \tests\TestCase
{
    /** @var fbt */
    private $fbtRuntime;

    public function setUp(): void
    {
        parent::setUp();

        FbtConfig::set('collectFbt', false);
        $this->fbtRuntime = new fbt();
    }

    protected function tearDown(): void
    {
        foreach (['getTranslatedInput', 'getFbtResult', 'getFbsResult', 'errorListener'] as $hook) {
            FbtHooks::unregister($hook);
        }

        FbtHooks::locale(null);
        FbtHooks::inlineMode('NO_INLINE');
        FbtQTOverrides::$overrides = [];
        FbtConfig::set('collectFbt', true);
        FbtConfig::set('debug', false);
        fbt::_purgeCache();

        parent::tearDown();
    }

    public function testShouldMemoizeNewStrings()
    {
        $first = $this->fbtRuntime->_('sample string', null, [], false);

        $this->assertSame($first, $this->fbtRuntime->_('sample string', null, [], false));
        $this->assertSame('sample string', (string)$first);
    }

    public function testShouldTriviallyHandleTokenlessStrings()
    {
        $this->assertSame('without tokens', (string)fbt('without tokens', 'test'));
    }

    public function testShouldReplaceTokensWithNamedValues()
    {
        $this->assertSame(
            'with token A here',
            (string)fbt('with token ' . \fbt\fbt::param('token', 'A') . ' here', 'test')
        );
        $this->assertSame(
            'with tokens A and B',
            (string)fbt(
                'with tokens ' .
                \fbt\fbt::param('tokenA', 'A') .
                ' and ' .
                \fbt\fbt::param('tokenB', 'B'),
                'test'
            )
        );
    }

    public function testShouldSupportObjectsAsTokenValues()
    {
        $argument = new \ArrayObject();
        $result = $this->fbtRuntime->_(
            'with token {token} here',
            [fbt::_param('token', $argument)]
        );

        $this->assertInstanceOf(FbtResult::class, $result);
        $this->assertSame(['with token ', $argument, ' here'], $result->getContents());
    }

    public function testShouldRenderEmptyStringForNullValues()
    {
        $this->assertSame('', (string)$this->fbtRuntime->_('{null_value}', [fbt::_param('null_value', null)]));
    }

    public function testShouldUseWildcardDefaults()
    {
        $this->assertSame(
            'with something like 42 wildcards',
            (string)fbt('with something like ' . \fbt\fbt::param('count', 42, ['number' => true]) . ' wildcards', 'test')
        );
    }

    public function testShouldLogTheDuplicateTokenComingFromTheSameTypeOfConstruct()
    {
        $this->expectException(FbtException::class);
        $this->expectExceptionMessage('Cannot register a substitution with token=`tokenName` more than once');

        $this->fbtRuntime->_('Just a {tokenName}', [
            fbt::_param('tokenName', 'substitute'),
            fbt::_param('tokenName', 'substitute'),
        ]);
    }

    public function testShouldLogTheDuplicateTokenComingFromTheDifferentConstructs()
    {
        $this->expectException(FbtException::class);
        $this->expectExceptionMessage('Cannot register a substitution with token=`tokenName` more than once');

        $this->fbtRuntime->_('Just a {tokenName}', [
            fbt::_param('tokenName', 'substitute'),
            fbt::_name('tokenName', 'person name', \fbt\Lib\IntlVariations::GENDER_UNKNOWN),
        ]);
    }

    public function testShouldReplaceQuickTranslationStrings()
    {
        FbtQTOverrides::$overrides = [
            '1_8b0c31a270a324f26d2417a358106611' => 'override',
            '1_fakeHash1' => 'This is an override with a {param}',
            '1_fakeHash2' => 'These are overrides and a {param}',
            '1_fakeHash3' => 'Override a {param}',
        ];

        $this->assertSame(
            'override',
            (string)$this->fbtRuntime->_(['This is a QT string', '8b0c31a270a324f26d2417a358106611'], null)
        );
        $this->assertSame(
            'Override a substitute',
            (string)$this->fbtRuntime->_(['Just a {param}', 'fakeHash3'], [fbt::_param('param', 'substitute')])
        );

        $runtimeArg = [
            's' => ['This is a QT with a {param}', 'fakeHash1'],
            'p' => ['These are QTs with a {param}', 'fakeHash2'],
        ];
        $this->assertSame(
            'This is an override with a word',
            (string)$this->fbtRuntime->_($runtimeArg, [
                fbt::_param('param', 'word'),
                fbt::_enum('s', ['s' => 'one', 'p' => 'other']),
            ])
        );
        $this->assertSame(
            'These are overrides and a test',
            (string)$this->fbtRuntime->_($runtimeArg, [
                fbt::_param('param', 'test'),
                fbt::_enum('p', ['s' => 'one', 'p' => 'other']),
            ])
        );
        $this->assertSame(
            "This isn't",
            (string)$this->fbtRuntime->_(["This isn't", '8b0c31a270a324f26d2417a358106612'], null)
        );
    }

    public function testShouldCreateATupleForFbtSubjectIfValid()
    {
        $this->assertSame(
            [[\fbt\Lib\IntlVariations::GENDER_MALE, '*'], null],
            fbt::_subject(\fbt\Lib\IntlVariations::GENDER_MALE)
        );

        $this->expectExceptionMessage('Invalid gender provided');
        fbt::_subject(0);
    }

    public function testShouldAccessTableWithMultipleTokensContainingSubject()
    {
        $this->assertSame(
            'Invited by 1 friend.',
            (string)fbt(
                'Invited by ' . \fbt\fbt::plural('friend', 1, ['showCount' => 'yes']) . '.',
                'Test Description',
                ['subject' => \fbt\Lib\IntlVariations::GENDER_UNKNOWN]
            )
        );
    }

    public function testShouldDeferToFbtHooksGetTranslatedInput()
    {
        FbtHooks::register('getTranslatedInput', function (array $input): array {
            return ['table' => 'ALL YOUR TRANSLATION ARE BELONG TO US', 'args' => null];
        });

        $this->assertSame(
            'ALL YOUR TRANSLATION ARE BELONG TO US',
            (string)$this->fbtRuntime->_('sample string', null)
        );
    }

    public function testShouldPassExtraOptionsToFbtHooksGetFbtResult()
    {
        FbtHooks::register('getFbtResult', function (array $input) {
            $fbtResult = (string)FbtResult::get($input);
            if (($input['extraOptions']['renderStringInBracket'] ?? null) === 'yes') {
                return "[$fbtResult]";
            }

            return $fbtResult;
        });

        $this->assertSame('A simple string', $this->fbtRuntime->_('A simple string', null));
        $this->assertSame(
            '[Another simple string]',
            $this->fbtRuntime->_('Another simple string', null, [
                'eo' => ['renderStringInBracket' => 'yes'],
            ])
        );
    }

    public function testShouldReturnAListOfTokenValuesWithoutEmptyStrings()
    {
        $fbtParams = [new \ArrayObject(['hello']), new \ArrayObject(['world'])];

        $result = $this->fbtRuntime->_('{hello}{world}', [
            fbt::_param('hello', $fbtParams[0]),
            fbt::_param('world', $fbtParams[1]),
        ]);

        $this->assertSame($fbtParams, $result->getContents());
    }

    public function testShouldHandleVariatedNumbers()
    {
        FbtHooks::locale('br_FR'); // IntlCLDRNumberType31

        $numToType = [
            '21' => IntlVariations::INTL_NUMBER_VARIATIONS['ONE'],
            '22' => IntlVariations::INTL_NUMBER_VARIATIONS['TWO'],
            '103' => IntlVariations::INTL_NUMBER_VARIATIONS['FEW'],
            '1000000' => IntlVariations::INTL_NUMBER_VARIATIONS['MANY'],
            '15' => IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'],
        ];

        foreach ($numToType as $n => $type) {
            $displayNumber = intlNumUtils::formatNumberWithThousandDelimiters((float)$n);
            $this->assertSame(
                [[$type, '*'], ['num' => $displayNumber]],
                fbt::_param('num', (int)$n, [0])
            );
        }
    }

    public function testShouldHandleFractionalPluralCounts()
    {
        FbtHooks::locale('en_US');

        $this->assertSame(
            'I have 1.5 apples',
            (string)fbt('I have ' . \fbt\fbt::plural('apple', 1.5, ['showCount' => 'yes']), 'test')
        );
        $this->assertSame(
            [[IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'], '*'], []],
            fbt::_plural(1.5)
        );
        $this->assertSame(
            ['_1', IntlVariations::INTL_NUMBER_VARIATIONS['ONE'], '*'],
            fbt::_plural(1.0)[0]
        );
        $this->assertSame(
            ['_1', IntlVariations::INTL_NUMBER_VARIATIONS['ONE'], '*'],
            fbt::_plural('1')[0]
        );
    }

    public function testShouldUseCountForAnEmptyPluralValue()
    {
        $this->assertSame([[IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'], '*'], ['number' => '5']], fbt::_plural(5, 'number', ''));
        $this->assertSame([[IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'], '*'], ['number' => 'five']], fbt::_plural(5, 'number', 'five'));
    }

    /**
     * @dataProvider pronounProvider
     */
    public function testShouldResolvePronounGenderKeys(string $usage, int $gender, string $expected)
    {
        $this->assertSame(
            "Look at $expected now",
            \fbt\Transform\FbtTransform\FbtTransform::transform(
                '<fbt desc="pronoun test">Look at <fbt:pronoun type="' . $usage . '" gender="' . $gender . '"/> now</fbt>'
            )
        );
    }

    public function pronounProvider(): array
    {
        return [
            ['object', Gender::GENDER_CONST['NOT_A_PERSON'], 'this'],
            ['object', Gender::GENDER_CONST['FEMALE_SINGULAR'], 'her'],
            ['object', Gender::GENDER_CONST['MALE_SINGULAR'], 'him'],
            ['object', Gender::GENDER_CONST['UNKNOWN_SINGULAR'], 'them'],
            ['reflexive', Gender::GENDER_CONST['NOT_A_PERSON'], 'themself'],
            ['reflexive', Gender::GENDER_CONST['NEUTER_SINGULAR'], 'themself'],
            ['reflexive', Gender::GENDER_CONST['UNKNOWN_SINGULAR'], 'themself'],
            ['reflexive', Gender::GENDER_CONST['MALE_SINGULAR_GUESS'], 'himself'],
            ['reflexive', Gender::GENDER_CONST['UNKNOWN_PLURAL'], 'themselves'],
            ['subject', Gender::GENDER_CONST['NOT_A_PERSON'], 'they'],
            ['possessive', Gender::GENDER_CONST['FEMALE_SINGULAR_GUESS'], 'her'],
        ];
    }

    public function testPronounAcceptsUsageNames()
    {
        $this->assertSame(
            fbt::_pronoun(0, Gender::GENDER_CONST['NOT_A_PERSON']),
            fbt::_pronoun('object', Gender::GENDER_CONST['NOT_A_PERSON'])
        );
        $this->assertSame(
            [[Gender::GENDER_CONST['NOT_A_PERSON'], '*'], null],
            fbt::_pronoun(0, Gender::GENDER_CONST['NOT_A_PERSON'])
        );
    }

    public function testPronounFallsBackToNotAPersonForAnUnknownGender()
    {
        $this->assertSame(
            [[Gender::GENDER_CONST['NOT_A_PERSON'], '*'], null],
            fbt::_pronoun(3, 99)
        );
    }

    public function testMissingParameterThrowsInDebugMode()
    {
        // In debug mode, a warning is triggered for locales without translations
        FbtHooks::locale('en_US');
        FbtConfig::set('debug', true);

        $this->expectExceptionMessage('Expected fbt parameter names (`tokenA`) to also contain `tokenB`');

        $this->fbtRuntime->_('{tokenA} and {tokenB}', [fbt::_param('tokenA', 'A')]);
    }

    public function testLiteralBracesArePreservedWithoutParams()
    {
        $this->assertSame('Use {curly} braces', (string)fbt('Use {curly} braces', 'test'));
    }

    public function testIsFbtInstance()
    {
        $this->assertTrue(fbt::isFbtInstance($this->fbtRuntime->_('sample string', null)));
        $this->assertFalse(fbt::isFbtInstance('sample string'));
    }

    public function testFbtResultCanBeFlattenedIntoArray()
    {
        $obj1 = new FbtResult(['prefix']);
        $obj2 = new FbtResult(['suffix']);
        $obj3 = new FbtResult([$obj1, 'content', $obj2]);
        $this->assertSame('prefix content suffix', implode(' ', $obj3->flattenToArray()));

        $stringable = new class () {
            public function __toString(): string
            {
                return 'stringable';
            }
        };
        $obj3 = new FbtResult([new FbtResult(['prefix']), 'content', $stringable]);
        $this->assertSame('prefix content stringable', implode(' ', $obj3->flattenToArray()));
        $this->assertSame('prefixcontentstringable', (string)$obj3);
    }

    public function testFbtResultInvokesTheErrorListenerOnlyForNonStringContents()
    {
        $errorListener = new class () implements IFbtErrorListener {
            public $errors = [];

            public function onStringSerializationError($content): void
            {
                $this->errors[] = $content;
            }
        };

        $result = new FbtResult(['hello', new FbtResult(['world'], $errorListener)], $errorListener);
        $this->assertSame('helloworld', (string)$result);
        $this->assertSame([], $errorListener->errors);

        $array = new \ArrayObject();
        $result = new FbtResult(['hello', $array, 42], $errorListener);
        $this->assertSame('hello', (string)$result);
        $this->assertSame([$array, 42], $errorListener->errors);
    }

    public function testErrorListenerHookReceivesTheContext()
    {
        $contexts = [];
        FbtHooks::register('errorListener', function (array $context) use (&$contexts): ?IFbtErrorListener {
            $contexts[] = $context;

            return null;
        });

        $this->fbtRuntime->_(['Hello', 'someHash'], null);

        // Requested for the token substitution and for the result
        $this->assertSame([
            ['translation' => 'Hello', 'hash' => 'someHash'],
            ['translation' => 'Hello', 'hash' => 'someHash'],
        ], $contexts);
    }

    private function registerMissingParameterListener(array &$contexts): \stdClass
    {
        $errors = new \stdClass();
        $errors->missing = [];
        FbtHooks::register('errorListener', function (array $context) use (&$contexts, $errors): IFbtErrorListener {
            $contexts[] = $context;

            return new class ($errors) implements IFbtErrorListener {
                private $errors;

                public function __construct(\stdClass $errors)
                {
                    $this->errors = $errors;
                }

                public function onStringSerializationError($content): void
                {
                }

                public function onMissingParameterError(array $providedParamNames, string $missingParamName): void
                {
                    $this->errors->missing[] = [$providedParamNames, $missingParamName];
                }
            };
        });

        return $errors;
    }

    public function testShouldInvokeOnMissingParameterErrorListener()
    {
        $contexts = [];
        $errors = $this->registerMissingParameterListener($contexts);

        $pattern = 'Just a {tokenName}';
        $this->fbtRuntime->_($pattern, []);

        $this->assertSame(['translation' => $pattern, 'hash' => null], end($contexts));
        $this->assertSame([[[], 'tokenName']], $errors->missing);
    }

    public function testMissingParameterIsReportedInsteadOfThrownInDebugMode()
    {
        FbtHooks::locale('en_US');
        FbtConfig::set('debug', true);
        $contexts = [];
        $errors = $this->registerMissingParameterListener($contexts);

        $result = $this->fbtRuntime->_('{tokenA} and {tokenB}', [fbt::_param('tokenA', 'A')]);

        $this->assertSame('A and ', (string)$result);
        $this->assertSame([[['tokenA'], 'tokenB']], $errors->missing);
    }

    public function testShouldLogImpressionWithTheTableAndTheKeysUsedToAccessIt()
    {
        $impressions = [];
        FbtHooks::register('logImpression', function (string $hash, ?array $options = null) use (&$impressions) {
            $impressions[] = [$hash, $options];
        });
        FbtHooks::locale('en_US');

        try {
            $table = [
                '*' => ['{count} cats', 'hashOther'],
                '_1' => ['One cat', 'hashOne'],
            ];
            $this->assertSame('One cat', (string)$this->fbtRuntime->_($table, [fbt::_plural(1)]));
            $this->assertSame('2 cats', (string)$this->fbtRuntime->_($table, [fbt::_plural(2, 'count')]));
        } finally {
            FbtHooks::unregister('logImpression');
        }

        $this->assertSame([
            ['hashOne', ['inputTable' => $table, 'tokens' => ['_1']]],
            ['hashOther', ['inputTable' => $table, 'tokens' => ['*']]],
        ], $impressions);
    }

    public function testFbtResultPreventsReentrantSerialization()
    {
        $result = null;
        $reentrant = new class ($result) {
            public $result;

            public function __construct(&$result)
            {
                $this->result = &$result;
            }

            public function __toString(): string
            {
                return (string)$this->result;
            }
        };
        $result = new FbtResult(['a', $reentrant]);

        $this->assertSame('a<<Reentering fbt.toString() is forbidden>>', (string)$result);
    }

    public function testFbtResultIsJsonSerializable()
    {
        $this->assertSame('"hello world"', json_encode(new FbtResult(['hello ', new FbtResult(['world'])])));
    }

    public function testFbsReturnsPureStringResults()
    {
        $result = (new fbs())->_('A simple string', null);

        $this->assertInstanceOf(FbtPureStringResult::class, $result);
        $this->assertSame('A simple string', (string)$result);

        // The result cache is separated from the fbt() one
        $this->assertInstanceOf(FbtResultBase::class, $this->fbtRuntime->_('A simple string', null, [], false));
        $this->assertNotInstanceOf(FbtPureStringResult::class, $this->fbtRuntime->_('A simple string', null, [], false));
        $this->assertInstanceOf(FbtPureStringResult::class, (new fbs())->_('A simple string', null, [], false));
    }

    public function testFbsParamsWork()
    {
        $this->assertSame(
            'Hello Bob!',
            (string)fbs('Hello ' . \fbt\fbs::param('name', 'Bob') . '!', 'test')
        );
        $this->assertSame(
            'Hello Bob!',
            (string)(new fbs())->_('Hello {name}!', [fbs::_param('name', (new fbs())->_('Bob', null))])
        );
    }

    public function testFbsPluralWorks()
    {
        $this->assertSame(
            'You see 2 cats',
            (string)fbs('You see ' . \fbt\fbs::plural('cat', 2, ['showCount' => 'yes']), 'test')
        );
    }

    public function testFbsParamRejectsRichContents()
    {
        $this->expectExceptionMessage(
            'Expected fbs parameter value to be the result of fbs(), <fbs/>, or a string; instead we got `fbt\Runtime\Shared\FbtResult` (type: object)'
        );

        fbs::_param('name', new FbtResult(['rich']));
    }

    public function testFbsPluralRejectsRichContents()
    {
        $this->expectExceptionMessage(
            'Expected fbs plural UI value to be nullish or the result of fbs(), <fbs/>, or a string; instead we got `fbt\Runtime\Shared\FbtResult` (type: object)'
        );

        fbs::_plural(2, 'count', new FbtResult(['rich']));
    }

    public function testFbsIsNeverInlined()
    {
        FbtHooks::inlineMode('TRANSLATION');
        FbtHooks::register('canInline', function () {
            return true;
        });

        $this->assertSame(
            'A simple string',
            (string)(new fbs())->_(['A simple string', 'someHash'], null)
        );
        $this->assertSame(
            '<em class="intlInlineMode_translatable" data-intl-hash="someHash" data-intl-locale="sk_SK">A simple string</em>',
            (string)$this->fbtRuntime->_(['A simple string', 'someHash'], null)
        );

        FbtHooks::unregister('canInline');
    }

    public function testNestedInlineResultsKeepTheirWrapper()
    {
        FbtHooks::inlineMode('TRANSLATION');
        FbtHooks::register('canInline', function () {
            return true;
        });

        $inner = $this->fbtRuntime->_(['inner', 'innerHash'], null);
        $outer = new FbtResult(['outer ', $inner]);

        $this->assertSame(
            'outer <em class="intlInlineMode_translatable" data-intl-hash="innerHash" data-intl-locale="sk_SK">inner</em>',
            (string)$outer
        );

        FbtHooks::unregister('canInline');
    }
}
