<?php

namespace tests\transform;

use fbt\FbtConfig;
use fbt\Services\CollectFbtsService;
use fbt\Services\TranslationsGeneratorService;
use fbt\Transform\FbtTransform\fbtHash;

/**
 * collect-fbts and translate aligned with upstream (collectFbt.js, translate.js)
 */
class cliParityTest extends \tests\TestCase
{
    /** @var string */
    private $dir;

    public function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/fbt-cli-' . uniqid();
        mkdir($this->dir);
        \fbt\fbt::_purgeCache();
    }

    protected function tearDown(): void
    {
        array_map('unlink', array_filter(glob($this->dir . '/{,.}*', GLOB_BRACE) ?: [], 'is_file'));
        @rmdir($this->dir);
        FbtConfig::set('generateOuterTokenName', false);

        parent::tearDown();
    }

    private function collect(string $source, array $options = []): array
    {
        $file = $this->dir . '/source.php';
        file_put_contents($file, $source);

        $service = new CollectFbtsService();
        $output = $service->collect([$file], $options);
        $this->assertSame(0, $service->getErrorCount());

        return json_decode(CollectFbtsService::toJSON($output, false), true);
    }

    public function testCollectsAllKindsOfCallsites()
    {
        FbtConfig::set('fbtCommon', ['Done' => 'Button label']);
        $output = $this->collect(
            <<<'PHP'
<?php
echo fbt('A ' . \fbt\fbt::param('n', $n, ['number' => $n]) . ' ' . \fbt\fbt::param('g', $g, ['gender' => $gender]), 'fbt');
echo fbs('An fbs string', 'fbs');
echo \fbt\fbs::c('Done');
echo (new \fbt\fbt('A new string', 'new'));
echo fbt(\fbt\fbt::plural('cat', $count, ['showCount' => 'yes', 'value' => $value]), 'plural');
?>
<p><fbt desc="markup">Markup string</fbt></p>
PHP
        );

        $texts = array_map(function (array $phrase) {
            return array_column(array_values($phrase['hashToLeaf']), 'text');
        }, $output['phrases']);

        $this->assertContains(['A {n} {g}'], $texts);
        $this->assertContains(['An fbs string'], $texts);
        $this->assertContains(['A new string'], $texts);
        $this->assertContains(['{number} cats', '1 cat'], $texts);
        $this->assertContains(['Markup string'], $texts);
        $this->assertContains(['Done'], $texts);
        FbtConfig::set('fbtCommon', []);
    }

    public function testEveryCallsiteIsCollected()
    {
        $output = $this->collect(
            <<<'PHP'
<?php
echo fbt('Same', 'same');
echo fbt(
    'Same',
    'same'
);
PHP
        );

        $this->assertSame([2, 3], array_column($output['phrases'], 'line_beg'));
        $this->assertSame([2, 6], array_column($output['phrases'], 'line_end'));
    }

    public function testPackagers()
    {
        $source = "<?php\necho fbt('Hello', 'greeting');\n";
        $jsfbtTable = ['desc' => 'greeting', 'text' => 'Hello'];

        $text = $this->collect($source)['phrases'][0];
        $this->assertArrayHasKey('hashToLeaf', $text);
        $this->assertArrayNotHasKey('hash_key', $text);

        $phrase = $this->collect($source, ['packager' => 'phrase'])['phrases'][0];
        $this->assertSame(['hash_key', 'hash_code'], array_slice(array_keys($phrase), 0, 2));
        $this->assertSame(fbtHash::fbtHashKey($jsfbtTable), $phrase['hash_key']);
        $this->assertSame(fbtHash::fbtJenkinsHash($jsfbtTable), $phrase['hash_code']);
        $this->assertArrayNotHasKey('hashToLeaf', $phrase);

        $both = $this->collect($source, ['packager' => 'both'])['phrases'][0];
        $this->assertSame(['hash_key', 'hash_code', 'hashToLeaf'], array_slice(array_keys($both), 0, 3));

        $none = $this->collect($source, ['packager' => 'none'])['phrases'][0];
        $this->assertArrayNotHasKey('hashToLeaf', $none);
        $this->assertArrayNotHasKey('hash_key', $none);

        $terse = $this->collect($source, ['terse' => true])['phrases'][0];
        $this->assertArrayNotHasKey('jsfbt', $terse);
    }

    public function testFbtElementNodes()
    {
        $output = $this->collect("<?php\necho fbt('Hello <b>world</b>', 'greeting');\n", ['genFbtNodes' => true]);

        $this->assertCount(1, $output['fbtElementNodes']);
        $this->assertSame(0, $output['fbtElementNodes'][0]['phraseIndex']);
        $this->assertSame('element', $output['fbtElementNodes'][0]['type']);
        $this->assertSame(1, $output['fbtElementNodes'][0]['children'][1]['phraseIndex']);
    }

    public function testErrorsAreCounted()
    {
        $file = $this->dir . '/source.php';
        file_put_contents($file, "<?php\necho fbt('x', 'd', ['unknown' => 'option']);\n");

        $service = new CollectFbtsService();
        $service->collect([$file]);

        $this->assertSame(1, $service->getErrorCount());
    }

    private static function translationInput(): array
    {
        return [
            'phrases' => [
                [
                    'hashToLeaf' => ['h1' => ['text' => 'Hello', 'desc' => 'greeting']],
                    'jsfbt' => ['t' => ['desc' => 'greeting', 'text' => 'Hello'], 'm' => []],
                ],
            ],
            'translationGroups' => [
                [
                    'fb-locale' => 'sk_SK',
                    'translations' => [
                        'h1' => ['tokens' => [], 'types' => [], 'translations' => [['translation' => 'Ahoj', 'variations' => []]]],
                    ],
                ],
            ],
        ];
    }

    public function testTranslateOutputs()
    {
        $input = self::translationInput();
        $hk = fbtHash::fbtHashKey($input['phrases'][0]['jsfbt']['t']);

        $this->assertSame(
            [['fb-locale' => 'sk_SK', 'translatedPhrases' => ['Ahoj']]],
            TranslationsGeneratorService::processJSON($input)
        );
        $this->assertSame(
            ['sk_SK' => [$hk => 'Ahoj']],
            TranslationsGeneratorService::processJSON($input, ['jenkins' => true])
        );
        $this->assertSame(
            ['sk_SK' => ['custom-Hello' => 'Ahoj']],
            TranslationsGeneratorService::processJSON($input, ['hashModule' => function (array $table) {
                return 'custom-' . $table['text'];
            }])
        );
        $this->assertSame(
            "[\n  {\n    \"fb-locale\": \"sk_SK\",\n    \"translatedPhrases\": [\n      \"Ahoj\"\n    ]\n  }\n]",
            TranslationsGeneratorService::toJSON(TranslationsGeneratorService::processJSON($input), true)
        );
    }

    /**
     * fbtee: runtime_catalogs_omit_untranslated_messages
     *
     * @dataProvider untranslatedMessagesProvider
     */
    public function testRuntimeCatalogsOmitUntranslatedMessages(array $translations)
    {
        $input = self::translationInput();
        $input['translationGroups'][0]['translations'] = $translations;

        $this->assertSame(['sk_SK' => []], TranslationsGeneratorService::processJSON($input, ['jenkins' => true]));
        // positional (non-Jenkins) output retains its source fallback
        $this->assertSame(
            [['fb-locale' => 'sk_SK', 'translatedPhrases' => ['Hello']]],
            TranslationsGeneratorService::processJSON($input)
        );
    }

    public function untranslatedMessagesProvider(): array
    {
        return [
            'missing' => [[]],
            'new' => [['h1' => ['status' => 'new', 'translations' => [['translation' => 'A', 'variations' => []]]]]],
            'no translations' => [['h1' => ['translations' => []]]],
        ];
    }

    // fbtee: explicit_translations_equal_to_source_are_preserved
    public function testExplicitTranslationsEqualToSourceArePreserved()
    {
        $input = self::translationInput();
        $input['translationGroups'][0]['translations']['h1']['translations'][0]['translation'] = 'Hello';
        $hk = fbtHash::fbtHashKey($input['phrases'][0]['jsfbt']['t']);

        $this->assertSame(['sk_SK' => [$hk => 'Hello']], TranslationsGeneratorService::processJSON($input, ['jenkins' => true]));
    }

    /**
     * fbtee: strict_translation_rejects_incomplete_source_entries
     *
     * @dataProvider incompleteTranslationsProvider
     */
    public function testStrictTranslationRejectsIncompleteSourceEntries(array $translations)
    {
        $input = self::translationInput();
        $input['translationGroups'][0]['translations'] = $translations;

        foreach ([['jenkins' => false], ['jenkins' => true]] as $options) {
            try {
                TranslationsGeneratorService::processJSON($input, ['strict' => true] + $options);
                $this->fail('Expected an exception');
            } catch (\Exception $e) {
                $this->assertSame('Missing sk_SK translation for string (h1)', $e->getMessage());
            }
        }
        $this->assertSame(['sk_SK' => []], TranslationsGeneratorService::processJSON($input, ['jenkins' => true]));
    }

    public function incompleteTranslationsProvider(): array
    {
        return [
            'missing' => [[]],
            'null' => [['h1' => null]],
            'new' => [['h1' => ['status' => 'new', 'translations' => [['translation' => 'A', 'variations' => []]]]]],
            'no translations' => [['h1' => ['translations' => []]]],
        ];
    }

    // fbtee: strict_translation_preserves_empty_and_source_equal_translations
    public function testStrictTranslationPreservesEmptyAndSourceEqualTranslations()
    {
        $hk = fbtHash::fbtHashKey(self::translationInput()['phrases'][0]['jsfbt']['t']);
        foreach (['', 'Hello', 'Ahoj'] as $translation) {
            $input = self::translationInput();
            $input['translationGroups'][0]['translations'] = [
                'h1' => ['translations' => [['translation' => $translation, 'variations' => []]]],
                'obsolete' => ['status' => 'new', 'translations' => []],
            ];

            $this->assertSame(
                ['sk_SK' => [$hk => $translation]],
                TranslationsGeneratorService::processJSON($input, ['jenkins' => true, 'strict' => true])
            );
        }
    }

    public function testTranslateStrictMode()
    {
        $input = self::translationInput();
        $input['translationGroups'][0]['translations']['h1'] = null;

        $this->expectExceptionMessage('Missing sk_SK translation for string (h1)');
        TranslationsGeneratorService::processJSON($input, ['strict' => true]);
    }

    private function runCli(string $command): string
    {
        exec(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/bin/fbt') . ' ' . $command . ' 2>&1',
            $output,
            $exitCode
        );
        $this->assertSame(0, $exitCode, implode("\n", $output));

        return implode("\n", $output);
    }

    public function testTranslateOutputFile()
    {
        $input = self::translationInput();
        $input['translationGroups'][1] = $input['translationGroups'][0];
        $input['translationGroups'][1]['fb-locale'] = 'de_DE';
        $input['translationGroups'][1]['translations']['h1']['translations'][0]['translation'] = 'Hallo';
        file_put_contents($this->dir . '/.source_strings.json', json_encode(['phrases' => $input['phrases']]));
        file_put_contents($this->dir . '/sk_SK.json', json_encode($input['translationGroups'][0]));
        file_put_contents($this->dir . '/de_DE.json', json_encode($input['translationGroups'][1]));

        $args = '--jenkins --source-strings=' . escapeshellarg($this->dir . '/.source_strings.json')
            . ' --translations=' . escapeshellarg($this->dir . '/sk_SK.json,' . $this->dir . '/de_DE.json');
        $stdout = $this->runCli('translate --pretty ' . $args);
        $this->runCli('translate ' . $args . ' --output-file=' . escapeshellarg($this->dir . '/translations.json'));

        $hk = fbtHash::fbtHashKey($input['phrases'][0]['jsfbt']['t']);
        $this->assertSame($stdout, file_get_contents($this->dir . '/translations.json'));
        $this->assertSame(
            ['sk_SK' => [$hk => 'Ahoj'], 'de_DE' => [$hk => 'Hallo']],
            json_decode(file_get_contents($this->dir . '/translations.json'), true)
        );
    }

    public function testTranslateFiles()
    {
        $input = self::translationInput();
        file_put_contents($this->dir . '/.source_strings.json', json_encode(['phrases' => $input['phrases']]));
        file_put_contents($this->dir . '/sk_SK.json', json_encode($input['translationGroups'][0]));

        $this->assertSame(
            [['fb-locale' => 'sk_SK', 'translatedPhrases' => ['Ahoj']]],
            TranslationsGeneratorService::processFiles($this->dir . '/.source_strings.json', [$this->dir . '/sk_SK.json'])
        );

        // the runtime dictionary accepts translation groups too
        (new TranslationsGeneratorService())->exportTranslations($this->dir, $this->dir . '/sk_SK.json', null, false);
        $hk = fbtHash::fbtHashKey($input['phrases'][0]['jsfbt']['t']);
        $this->assertSame(
            ['sk_SK' => [$hk => ['Ahoj', 'h1']]],
            json_decode(file_get_contents($this->dir . '/translatedFbts.json'), true)
        );
    }
}
