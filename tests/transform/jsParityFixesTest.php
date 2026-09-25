<?php

namespace tests\transform;

use fbt\Exceptions\FbtParserException;
use fbt\fbt;
use fbt\FbtConfig;
use fbt\Runtime\Shared\FbtHooks;
use fbt\Services\CollectFbtsService;
use fbt\Services\TranslationsGeneratorService;
use fbt\Transform\FbtTransform\FbtTransform;
use fbt\Transform\FbtTransform\Translate\IntlVariations;

/**
 * Behaviors aligned with upstream fbt
 */
class jsParityFixesTest extends \tests\TestCase
{
    /** @var string */
    private $dir;

    public function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/fbt-parity-' . uniqid();
        mkdir($this->dir);
        fbt::_purgeCache();
    }

    protected function tearDown(): void
    {
        array_map('unlink', array_filter(glob($this->dir . '/{,.}*', GLOB_BRACE) ?: [], 'is_file'));
        @rmdir($this->dir);
        FbtHooks::locale(null);
        FbtConfig::set('collectFbt', true);
        fbt::_purgeCache();

        parent::tearDown();
    }

    public function testPhonologicalRulesApplyToStringsWithoutParameters()
    {
        FbtConfig::set('collectFbt', false);
        FbtHooks::locale('tr_TR');

        // upstream: substituteTokens() always applies the phonological rules
        $this->assertSame('It&#039;s', (string)fbt("It\u{2019}s", 'no parameters'));
    }

    public function testOnlyNumbersAreFormatted()
    {
        FbtConfig::set('collectFbt', false);
        FbtHooks::locale('en_US');

        // upstream: `typeof value === 'number'`
        $this->assertSame('Zip 12345', (string)fbt(['Zip ', fbt::param('zip', '12345', ['number' => true])], 'zip'));
        $this->assertSame('Count 12,345', (string)fbt(['Count ', fbt::param('count', 12345, ['number' => true])], 'count'));
        $this->assertSame('Value 007 items', (string)fbt(['Value ', fbt::plural('item', 3, ['showCount' => 'yes', 'value' => '007'])], 'value'));

        // HTML values are strings, a numeric value is the equivalent of a JSX number expression
        $this->assertSame(
            'Count 12,345',
            FbtTransform::transform('<fbt desc="count html">Count <fbt:param name="count" number="true">12345</fbt:param></fbt>')
        );
    }

    public function testGeneratedTranslationFilesCanBeTranslated()
    {
        $source = $this->dir . '/.source_strings.json';
        file_put_contents($source, json_encode([
            'phrases' => [
                [
                    'hashToLeaf' => ['h1' => ['text' => 'Hello', 'desc' => 'greeting']],
                    'jsfbt' => ['t' => ['desc' => 'greeting', 'text' => 'Hello'], 'm' => []],
                ],
            ],
        ]));

        // a file of an older version (the translation group by locale)
        $translations = $this->dir . '/sk_SK.json';
        file_put_contents($translations, json_encode([
            'sk_SK' => [
                'fb-locale' => 'sk_SK',
                'translations' => [
                    'h1' => ['tokens' => [], 'types' => [], 'translations' => [['translation' => 'Ahoj', 'variations' => []]]],
                ],
            ],
        ]));

        // `generate-translations` writes a translation group (the format of upstream `translate`)
        (new TranslationsGeneratorService())->generateTranslations($source, $this->dir . '/*.json', $this->dir . '/input.json');
        $this->assertSame('sk_SK', json_decode(file_get_contents($translations), true)['fb-locale']);

        $this->assertSame(
            [['fb-locale' => 'sk_SK', 'translatedPhrases' => ['Ahoj']]],
            TranslationsGeneratorService::processFiles($source, [$translations])
        );
    }

    public function testGeneratedTranslationSkeletonAlignsTokensAndTypes()
    {
        $source = $this->dir . '/.source_strings.json';
        file_put_contents($source, json_encode([
            'phrases' => [
                [
                    'hashToLeaf' => ['h1' => ['text' => '{name} shared a photo.', 'desc' => 'd']],
                    'jsfbt' => [
                        't' => ['*' => ['*' => ['desc' => 'd', 'text' => '{name} shared a photo.']]],
                        'm' => [['type' => 3], ['token' => 'name', 'type' => 1]],
                    ],
                ],
            ],
        ]));
        $translations = $this->dir . '/fbt_AC.json';
        file_put_contents($translations, '');

        (new TranslationsGeneratorService())->generateTranslations($source, $this->dir . '/*.json', $this->dir . '/input.json');

        $generated = json_decode(file_get_contents($translations), true)['translations']['h1'];
        $this->assertSame([null, 'name'], $generated['tokens']);
        $this->assertCount(2, $generated['types']);
    }

    public function testFbtElementNodesOfExtractedStringsOnly()
    {
        $file = $this->dir . '/source.php';
        file_put_contents($file, "<?php\necho fbt('Hello <b>world</b>', 'greeting', ['doNotExtract' => true]);\n");

        $service = new CollectFbtsService();
        $output = json_decode(CollectFbtsService::toJSON($service->collect([$file], ['genFbtNodes' => true]), false), true);

        $this->assertSame([], $output['phrases']);
        $this->assertSame([], $output['fbtElementNodes']);
    }

    public function testDescAttributeWithoutValue()
    {
        $this->expectException(FbtParserException::class);
        $this->expectExceptionMessage('<fbt> requires a "desc" attribute');

        FbtTransform::transform('<fbt desc>Hello</fbt>');
    }

    public function testIsValidValue()
    {
        $this->assertTrue(IntlVariations::isValidValue('*'));
        // upstream: the key of the special entry is the literal `EXACTLY_ONE`
        $this->assertTrue(IntlVariations::isValidValue('EXACTLY_ONE'));
        $this->assertFalse(IntlVariations::isValidValue(IntlVariations::EXACTLY_ONE));
        $this->assertTrue(IntlVariations::isValidValue((string)IntlVariations::INTL_NUMBER_VARIATIONS['ONE']));
        $this->assertFalse(IntlVariations::isValidValue('foo'));
    }

    public function testJsonOutput()
    {
        // translations by locale and hash are a JS object, also when empty
        $this->assertSame('{}', TranslationsGeneratorService::toJSON([], false, false));
        $this->assertSame('[]', TranslationsGeneratorService::toJSON([], false, true));

        // like JSON.stringify(), line terminators are not escaped
        $this->assertSame("{\"a\":\"\u{2028}\"}", TranslationsGeneratorService::toJSON(['a' => "\u{2028}"], false));
        $this->assertSame("{\"a\":\"\u{2029}\"}", CollectFbtsService::toJSON(['a' => "\u{2029}"], false));
    }
}
