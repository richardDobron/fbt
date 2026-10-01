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

    // prepareTranslations-test.tsx of fbtee: preserves pre-existing order and appends new hashes at the end
    public function testUpdateTranslations()
    {
        $phrase = function (string $desc, string $text): array {
            return ['desc' => $desc, 'text' => $text];
        };
        $existingEntry = function (string $translation, string $description = 'desc'): array {
            return [
                'description' => $description,
                'status' => 'translated',
                'tokens' => [],
                'translations' => [['translation' => $translation, 'variations' => []]],
                'types' => [],
            ];
        };

        $result = TranslationsGeneratorService::updateTranslations([
            'zzz' => $phrase('z desc', 'z text'),
            'aaa' => $phrase('a desc', 'a text'),
            'mmm' => $phrase('m desc', 'm text'),
            'newOne' => $phrase('new desc', 'new text'),
        ], [
            'zzz' => $existingEntry('Z translated'),
            'aaa' => $existingEntry('A translated'),
            'mmm' => $existingEntry('M translated'),
            'removed' => $existingEntry('R translated'),
            'removedNull' => null,
        ]);

        $this->assertSame(['zzz', 'aaa', 'mmm', 'removedNull', 'newOne'], array_keys($result));
        $this->assertSame('Z translated', $result['zzz']['translations'][0]['translation']);
        $this->assertSame('new', $result['newOne']['status']);
        $this->assertEquals([
            'description' => 'new desc',
            'status' => 'new',
            'tokens' => [],
            'translations' => [['translation' => 'new text', 'variations' => new \stdClass()]],
            'types' => [],
        ], $result['newOne']);
    }

    // prepareTranslations-test.tsx of fbtee: with sortByHash=true
    public function testUpdateTranslationsSortByHash()
    {
        $phrase = function (string $desc, string $text): array {
            return ['desc' => $desc, 'text' => $text];
        };
        $existingEntry = function (string $translation): array {
            return [
                'description' => 'desc',
                'status' => 'translated',
                'tokens' => [],
                'translations' => [['translation' => $translation, 'variations' => []]],
                'types' => [],
            ];
        };

        // sorts pre-existing entries by hash and preserves translation values
        $result = TranslationsGeneratorService::updateTranslations([
            'zzz' => $phrase('z desc', 'z text'),
            'aaa' => $phrase('a desc', 'a text'),
            'mmm' => $phrase('m desc', 'm text'),
        ], [
            'zzz' => $existingEntry('Z translated'),
            'aaa' => $existingEntry('A translated'),
            'mmm' => $existingEntry('M translated'),
        ], true);
        $this->assertSame(['aaa', 'mmm', 'zzz'], array_keys($result));
        $this->assertSame('A translated', $result['aaa']['translations'][0]['translation']);
        $this->assertSame('M translated', $result['mmm']['translations'][0]['translation']);
        $this->assertSame('Z translated', $result['zzz']['translations'][0]['translation']);

        // sorts mixed existing + new entries and drops removed ones
        $result = TranslationsGeneratorService::updateTranslations([
            'keep2' => $phrase('keep2 desc', 'keep2 text'),
            'new1' => $phrase('new1 desc', 'new1 text'),
            'keep1' => $phrase('keep1 desc', 'keep1 text'),
            'new2' => $phrase('new2 desc', 'new2 text'),
        ], [
            'keep1' => $existingEntry('keep1 translated'),
            'remove1' => $existingEntry('remove1 translated'),
            'keep2' => $existingEntry('keep2 translated'),
        ], true);
        $this->assertSame(['keep1', 'keep2', 'new1', 'new2'], array_keys($result));
        $this->assertArrayNotHasKey('remove1', $result);
        $this->assertSame('keep1 translated', $result['keep1']['translations'][0]['translation']);
        $this->assertSame('new', $result['new1']['status']);
        $this->assertSame('new1 text', $result['new1']['translations'][0]['translation']);
        $this->assertSame('new', $result['new2']['status']);

        // sorts when there are no pre-existing translations
        $this->assertSame(['aaa', 'mmm', 'zzz'], array_keys(TranslationsGeneratorService::updateTranslations([
            'zzz' => $phrase('z desc', 'z text'),
            'aaa' => $phrase('a desc', 'a text'),
            'mmm' => $phrase('m desc', 'm text'),
        ], [], true)));

        // returns an empty object when all pre-existing entries are removed
        $this->assertSame([], TranslationsGeneratorService::updateTranslations([], [
            'gone1' => $existingEntry('gone1 translated'),
            'gone2' => $existingEntry('gone2 translated'),
        ], true));

        // produces deterministic output regardless of input insertion order
        $this->assertEquals(
            TranslationsGeneratorService::updateTranslations([
                'zzz' => $phrase('z desc', 'z text'),
                'aaa' => $phrase('a desc', 'a text'),
                'mmm' => $phrase('m desc', 'm text'),
            ], ['zzz' => $existingEntry('Z translated'), 'aaa' => $existingEntry('A translated')], true),
            TranslationsGeneratorService::updateTranslations([
                'mmm' => $phrase('m desc', 'm text'),
                'zzz' => $phrase('z desc', 'z text'),
                'aaa' => $phrase('a desc', 'a text'),
            ], ['aaa' => $existingEntry('A translated'), 'zzz' => $existingEntry('Z translated')], true)
        );
    }

    public function testGeneratedTranslationsAreNew()
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

        $this->assertSame(
            '{"description":"d","status":"new","tokens":[],"translations":[{"translation":"{name} shared a photo.","variations":{}}],"types":[]}',
            json_encode(json_decode(file_get_contents($translations))->translations->h1)
        );
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
