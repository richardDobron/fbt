<?php

namespace tests\transform;

use fbt\FbtConfig;
use fbt\Services\CollectFbtsService;
use fbt\Transform\FbtTransform\FbtTransform;

class collectFbtsTest extends \tests\TestCase
{
    /** @var string */
    private $dir;

    public function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/fbt-collect-' . uniqid();
        mkdir($this->dir . '/src', 0777, true);
        FbtTransform::$phrases = [];
        FbtTransform::$childToParent = [];
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/{,.}*', GLOB_BRACE) as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        foreach (glob($this->dir . '/src/*') as $file) {
            unlink($file);
        }
        rmdir($this->dir . '/src');
        rmdir($this->dir);
        FbtConfig::set('path', self::storagePath());

        parent::tearDown();
    }

    private function collect(array $files, array $options = []): array
    {
        foreach ($files as $name => $source) {
            file_put_contents($this->dir . '/src/' . $name, $source);
        }

        ob_start();

        try {
            $service = new CollectFbtsService();
            $service->collectFromFiles($this->dir, $this->dir . '/src', null, true, $options);
            unset($service);
        } finally {
            ob_end_clean();
        }

        return json_decode(file_get_contents($this->dir . '/.source_strings.json'), true);
    }

    public function testCollectsPhrasesFromSourceFiles()
    {
        $output = $this->collect([
            'a.php' => <<<'PHP'
<?php
echo fbt('Collected from a file', 'desc a');
echo fbt(
    'Hello ' . \fbt\fbt::param('name', $name) . '! ' . \fbt\fbt::enum($type, ['x' => 'Extra', 'y' => 'Why']),
    'desc b'
);
PHP
            ,
            'b.php' => <<<'PHP'
<?php
echo fbt('Collected <b>from <i>another</i> file</b>', 'desc c');
PHP
            ,
        ]);

        $texts = array_map(function (array $phrase) {
            return $phrase['jsfbt']['t']['text'] ?? array_keys($phrase['jsfbt']['t']);
        }, $output['phrases']);

        $this->assertSame([
            'Collected from a file',
            ['x', 'y'],
            'Collected {=from another file}',
            'from {=another} file',
            'another',
        ], $texts);
        $this->assertSame(['3' => 2, '4' => 3], $output['childParentMappings']);
        $this->assertSame('src/b.php', substr($output['phrases'][2]['filepath'], -9));
        $this->assertSame(2, $output['phrases'][2]['line_beg']);
    }
}
