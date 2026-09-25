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

    public function testTranslateStrictMode()
    {
        $input = self::translationInput();
        $input['translationGroups'][0]['translations']['h1'] = null;

        $this->expectExceptionMessage('Missing sk_SK translation for string (h1)');
        TranslationsGeneratorService::processJSON($input, ['strict' => true]);
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
