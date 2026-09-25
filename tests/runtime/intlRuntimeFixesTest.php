<?php

namespace tests\runtime;

use fbt\FbtConfig;
use fbt\Lib\DisplayGenderConst;
use fbt\Lib\IntlNumberType;
use fbt\Lib\IntlViewerContext;
use fbt\Runtime\FbtTranslations;
use fbt\Runtime\Gender;
use fbt\Runtime\Shared\fbt;
use fbt\Runtime\Shared\FbtHooks;
use fbt\Runtime\Shared\formatNumber;
use fbt\Runtime\Shared\InlineFbtResult;
use fbt\Runtime\Shared\IntlGender;
use fbt\Runtime\Shared\intlNumUtils;
use fbt\Runtime\Shared\IntlVariationResolverImpl;
use fbt\Transform\FbtTransform\FbtTransform;
use fbt\Transform\FbtTransform\Translate\Gender\IntlDefaultGenderType;
use fbt\Transform\FbtTransform\Translate\Gender\IntlGenderType;
use fbt\Transform\FbtTransform\Translate\Gender\IntlMergedUnknownGenderType;
use fbt\Transform\FbtTransform\Translate\IntlVariations;

/**
 * Regression tests for the runtime divergences from facebook/fbt v1.0.0.
 * Expected values were produced by the upstream JavaScript implementation.
 */
class intlRuntimeFixesTest extends \tests\TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        FbtConfig::set('collectFbt', false);
        FbtHooks::locale('en_US');
    }

    protected function tearDown(): void
    {
        FbtHooks::locale(null);
        FbtHooks::inlineMode('NO_INLINE');
        FbtConfig::set('collectFbt', true);
        FbtTranslations::registerTranslations([]);
        IntlViewerContext::setGender(IntlVariations::INTL_GENDER_VARIATIONS['UNKNOWN']);

        parent::tearDown();
    }

    public function testNumberVariationsSupportFractions()
    {
        $OTHER = IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'];
        $ONE = IntlVariations::INTL_NUMBER_VARIATIONS['ONE'];

        $this->assertSame([$OTHER, '*'], IntlVariationResolverImpl::getNumberVariations(1.5));
        $this->assertSame(['_1', $ONE, '*'], IntlVariationResolverImpl::getNumberVariations(1.0));

        FbtHooks::locale('ru_RU');
        $this->assertSame($ONE, IntlNumberType::get('ru_RU')->getVariation(21));
        $this->assertNotSame($ONE, IntlNumberType::get('ru_RU')->getVariation(21.5));

        FbtHooks::locale('sk_SK');
        $this->assertSame($OTHER, IntlNumberType::get('sk_SK')->getVariation(1.5));
    }

    public function testKirundiNumberVariations()
    {
        $numberType = IntlNumberType::get('rn_BI');

        $this->assertSame(IntlVariations::INTL_NUMBER_VARIATIONS['ONE'], $numberType->getVariation(1));
        $this->assertSame(IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'], $numberType->getVariation(0));
        $this->assertSame(IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'], $numberType->getVariation(2));
    }

    public function testMergedUnknownGenderLocales()
    {
        foreach (['ar_AR', 'sq_AL', 'ti_ET', 'ps_AF', 'qk_DZ', 'dsb_DE', 'vec_IT'] as $locale) {
            $this->assertInstanceOf(IntlMergedUnknownGenderType::class, IntlGenderType::forLocale($locale), $locale);
        }

        $this->assertInstanceOf(IntlDefaultGenderType::class, IntlGenderType::forLocale('ht_HT'));
        $this->assertInstanceOf(IntlDefaultGenderType::class, IntlGenderType::forLocale('sk_SK'));
    }

    public function testHebrewNumberVariations()
    {
        $numberType = IntlNumberType::get('he_IL');

        $this->assertSame(IntlVariations::INTL_NUMBER_VARIATIONS['ONE'], $numberType->getVariation(1));
        $this->assertSame(IntlVariations::INTL_NUMBER_VARIATIONS['TWO'], $numberType->getVariation(2));
        $this->assertSame(IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'], $numberType->getVariation(101));
        $this->assertSame(IntlVariations::INTL_NUMBER_VARIATIONS['OTHER'], $numberType->getVariation(102));
        $this->assertSame(IntlVariations::INTL_NUMBER_VARIATIONS['MANY'], $numberType->getVariation(20));
    }

    public function testNewlyMappedLocales()
    {
        $this->assertStringEndsWith('IntlCLDRNumberType05', get_class(IntlNumberType::get('fbt_AC')));
        $this->assertStringEndsWith('IntlCLDRNumberType09', get_class(IntlNumberType::get('fn_IT')));
        $this->assertStringEndsWith('IntlCLDRNumberType09', get_class(IntlNumberType::get('nh_MX')));
        $this->assertStringEndsWith('IntlCLDRNumberType47', get_class(IntlNumberType::get('tq_AR')));
    }

    public function testExplicitNumberVariationValues()
    {
        $this->assertSame(
            'You have 0 items',
            (string)fbt('You have ' . \fbt\fbt::param('count', '0', ['number' => 0]) . ' items', 'test')
        );
        $this->assertSame(
            'Rated 1.5 stars',
            (string)fbt('Rated ' . \fbt\fbt::param('rating', '1.5', ['number' => 1.5]) . ' stars', 'test')
        );
    }

    /**
     * @dataProvider formatNumberProvider
     */
    public function testFormatNumberMatchesUpstream(string $locale, string $method, array $args, string $expected)
    {
        FbtHooks::locale($locale);

        $this->assertSame($expected, intlNumUtils::{$method}(...$args));
    }

    public function formatNumberProvider(): array
    {
        return [
            ['en_US', 'formatNumber', [1, 1], '1.0'],
            ['de_DE', 'formatNumberWithThousandDelimiters', [10, 1], '10,0'],
            ['ar_AR', 'formatNumberWithThousandDelimiters', [10, 1], '١٠٫٠'],
            ['en_US', 'formatNumberRaw', ['5.0', 1], '5.0'],
            ['en_US', 'formatNumberRaw', [0.1 + 0.2], '0.30000000000000004'],
            ['en_US', 'formatNumberRaw', [1e15, 0, ','], '1,000,000,000,000,000'],
            ['en_US', 'formatNumberRaw', [0.00001, 5], '0.00001'],
            ['en_US', 'formatNumberRaw', [1e21], '1e+21'],
            ['en_US', 'formatNumberRaw', [-2.5, 0], '-2'],
            ['en_US', 'formatNumberRaw', [1.005, 2], '1.00'],
            ['en_US', 'formatNumberRaw', [NAN], 'NaN'],
            ['en_US', 'formatNumberRaw', [INF, 2], 'Infinity.00'],
            ['en_US', 'formatNumberRaw', [1234567, 0, ',', '.', 0, ['primaryGroupSize' => 0, 'secondaryGroupSize' => 0]], '1,234,567'],
            ['en_US', 'formatNumberWithThousandDelimiters', [1234567.123456789], '1,234,567.123456789'],
            ['en_US', 'formatNumberRaw', ['12345678901234567890.123', 2, ','], '12,345,678,901,234,567,890.12'],
            ['en_US', 'formatNumberRaw', ['12345.678', 2, ','], '12,345.67'],
            // js~php diff: numeric strings are rounded by the locale-aware formatters
            ['en_US', 'formatNumberWithThousandDelimiters', ['12345.678', 2], '12,345.68'],
            ['en_US', 'formatNumber', ['12345.678', 0], '12346'],
            ['en_US', 'formatNumberRaw', ['1234.50', null, ','], '1,234.50'],
            ['en_US', 'formatNumberWithLimitedSigFig', [0.0123, null, 2], '0.012'],
            ['en_US', 'formatNumberWithLimitedSigFig', [0.5, null, 1], '0.5'],
            ['en_US', 'formatNumberWithLimitedSigFig', [-0.5, null, 1], '-0.5'],
            ['en_US', 'formatNumberWithLimitedSigFig', [1e20, null, 2], '100,000,000,000,000,000,000'],
            ['en_US', 'getFloatString', ['1234.50', ',', '.'], '1,234.50'],
            ['en_US', 'getIntegerString', ['12345678901234567890', ','], '12,345,678,901,234,567,890'],
        ];
    }

    public function testParseNumberMatchesUpstream()
    {
        $this->assertSame(12.0, intlNumUtils::parseNumberRaw('p12', '.'));
        $this->assertSame(1000.0, intlNumUtils::parseNumberRaw('fr1000', '.'));
        $this->assertNull(intlNumUtils::parseNumberRaw('.', '.'));
        $this->assertNull(intlNumUtils::parseNumberRaw('-', '.'));
        $this->assertNull(intlNumUtils::parseNumberRaw('abc-', '.'));
        $this->assertNull(intlNumUtils::parseNumber('१२,३४,५६७.८'));
        $this->assertSame(2000.0, intlNumUtils::parseNumber('S/.2000'));
    }

    public function testFormatNumberWithLimits()
    {
        $this->assertSame('1,500', formatNumber::withMaxLimit(1500, 2000));
        $this->assertSame('2,000+', (string)formatNumber::withMaxLimit(2500, 2000));
        $this->assertSame('1,000.3+', (string)formatNumber::withMaxLimit(1500.5, 1000.25, 1));
        $this->assertSame('30', formatNumber::withMinLimit(30, 10));
        $this->assertSame('&lt;10', (string)formatNumber::withMinLimit(3, 10));
        $this->assertSame('1,234.5', formatNumber::withThousandDelimiters(1234.5));
        $this->assertSame('1234.50', formatNumber::formatNumber(1234.5, 2));
    }

    public function testIntlGender()
    {
        $this->assertSame(Gender::GENDER_CONST['FEMALE_SINGULAR'], IntlGender::fromMultiple([Gender::GENDER_CONST['FEMALE_SINGULAR']]));
        $this->assertSame(Gender::GENDER_CONST['UNKNOWN_PLURAL'], IntlGender::fromMultiple([1, 2]));
        $this->assertSame(Gender::GENDER_CONST['MALE_SINGULAR'], IntlGender::fromDisplayGender(DisplayGenderConst::MALE));
        $this->assertSame(Gender::GENDER_CONST['FEMALE_SINGULAR'], IntlGender::fromDisplayGender(DisplayGenderConst::FEMALE));
        $this->assertSame(Gender::GENDER_CONST['NEUTER_SINGULAR'], IntlGender::fromDisplayGender(DisplayGenderConst::NEUTER));
        $this->assertSame(Gender::GENDER_CONST['NOT_A_PERSON'], IntlGender::fromDisplayGender(DisplayGenderConst::UNKNOWN));

        $this->expectExceptionMessage('Cannot have pronoun for zero people');
        IntlGender::fromMultiple([]);
    }

    public function testGenderDataFallsBackSilentlyForUnknownGenders()
    {
        $this->assertSame('this', Gender::getData(12, 'object'));
    }

    public function testRenderedResultsAreSeparatedPerLocale()
    {
        FbtTranslations::registerTranslations([
            'sk_SK' => ['3ppzgb' => 'Pre vybraný časový úsek nemáme dostatok údajov na zobrazenie.'],
            'de_DE' => ['3ppzgb' => 'Nicht genügend Daten für den ausgewählten Zeitraum.'],
        ]);

        $render = function () {
            return (string)fbt('We have insufficient data to show for the selected time period.', 'Text for null state of insights data');
        };

        FbtHooks::locale('sk_SK');
        $this->assertSame('Pre vybraný časový úsek nemáme dostatok údajov na zobrazenie.', $render());

        FbtHooks::locale('de_DE');
        $this->assertSame('Nicht genügend Daten für den ausgewählten Zeitraum.', $render());

        FbtHooks::locale('en_US');
        $this->assertSame('We have insufficient data to show for the selected time period.', $render());
    }

    public function testPhrasesAreCollectedOnlyOnce()
    {
        FbtConfig::set('collectFbt', true);
        $count = count(FbtTransform::$phrases);

        $render = function () {
            return (string)fbt('A phrase collected only once', 'test');
        };

        foreach (['sk_SK', 'de_DE', 'en_US'] as $locale) {
            FbtHooks::locale($locale);
            $render();
        }

        FbtHooks::inlineMode('TRANSLATION');
        for ($i = 0; $i < 5; $i++) {
            $render();
        }

        $this->assertCount($count + 1, FbtTransform::$phrases);
    }

    public function testInlineFbtResultCanBeCreatedFromHookInput()
    {
        FbtHooks::inlineMode('TRANSLATION');

        $result = InlineFbtResult::get([
            'contents' => ['text'],
            'errorListener' => null,
            'patternString' => 'text',
            'patternHash' => null,
        ]);

        $this->assertInstanceOf(InlineFbtResult::class, $result);
        $this->assertSame('TRANSLATION', $result->inlineMode);
        $this->assertSame('text', (string)$result);
    }

    public function testTableAccessRequiresALeaf()
    {
        $this->expectExceptionMessage('Expected leaf, but got: {"*":"many {x}","_1":"one {x}"}');

        (new fbt())->_(['*' => 'many {x}', '_1' => 'one {x}'], [fbt::_param('x', 'y')]);
    }

    public function testRenderedResultsAreSeparatedPerViewerGender()
    {
        FbtHooks::locale('sk_SK');
        FbtTranslations::registerTranslations([
            'sk_SK' => [
                '131ypy' => [
                    '__vcg' => 1,
                    '*' => 'Ste pripojený',
                    IntlVariations::INTL_GENDER_VARIATIONS['FEMALE'] => 'Ste pripojená',
                ],
            ],
        ]);

        $render = function () {
            return (string)fbt('You are connected', 'Connection status');
        };

        IntlViewerContext::setGender(IntlVariations::INTL_GENDER_VARIATIONS['MALE']);
        $male = $render();
        IntlViewerContext::setGender(IntlVariations::INTL_GENDER_VARIATIONS['FEMALE']);
        $female = $render();

        $this->assertSame('Ste pripojený', $male);
        $this->assertSame('Ste pripojená', $female);
    }
}
