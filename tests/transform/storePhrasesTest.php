<?php

namespace tests\transform;

use fbt\FbtConfig;
use fbt\Runtime\Shared\FbtHooks;
use fbt\Transform\FbtTransform\FbtTransform;

class storePhrasesTest extends \tests\TestCase
{
    /** @var string */
    private $path;

    public function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/fbt-store-' . uniqid();
        mkdir($this->path);
        FbtConfig::set('path', $this->path);
        FbtHooks::locale('en_US');
        FbtTransform::$phrases = [];
        FbtTransform::$childToParent = [];
    }

    protected function tearDown(): void
    {
        FbtHooks::storePhrases();
        FbtHooks::locale(null);
        @unlink($this->path . '/.source_strings.json');
        @rmdir($this->path);
        FbtConfig::set('path', self::storagePath());

        parent::tearDown();
    }

    private function readSourceStrings(): array
    {
        return json_decode(file_get_contents($this->path . '/.source_strings.json'), true);
    }

    public function testStoresPhrasesWithTheirParentMappings()
    {
        FbtTransform::transform('<fbt desc="d">Go on an <a href="#"><span>awesome</span> vacation</a></fbt>');
        FbtHooks::storePhrases();

        // Rendering the same fbt again doesn't store it twice
        FbtTransform::transform('<fbt desc="d">Go on an <a href="#"><span>awesome</span> vacation</a></fbt>');
        FbtTransform::transform('<fbt desc="d">Hello world</fbt>');
        FbtHooks::storePhrases();

        $sourceStrings = $this->readSourceStrings();
        $this->assertSame(
            ['Go on an {=awesome vacation}', '{=awesome} vacation', 'awesome', 'Hello world'],
            array_map(function (array $phrase) {
                return $phrase['jsfbt']['t']['text'];
            }, $sourceStrings['phrases'])
        );
        $this->assertSame(['1' => 0, '2' => 1], $sourceStrings['childParentMappings']);
    }

    public function testDropsPhrasesCollectedByFbt4()
    {
        file_put_contents($this->path . '/.source_strings.json', json_encode([
            'phrases' => [
                ['hashToText' => ['a' => 'Old'], 'desc' => 'd', 'type' => 'text', 'jsfbt' => 'Old'],
                ['hashToText' => ['b' => 'Old inner'], 'desc' => 'd', 'type' => 'text', 'jsfbt' => 'Old inner'],
            ],
            'childParentMappings' => [1 => 0],
        ]));

        FbtTransform::transform('<fbt desc="d">New</fbt>');
        FbtHooks::storePhrases();

        $sourceStrings = $this->readSourceStrings();
        $this->assertCount(1, $sourceStrings['phrases']);
        $this->assertSame('New', $sourceStrings['phrases'][0]['jsfbt']['t']['text']);
        $this->assertSame([], $sourceStrings['childParentMappings']);
    }
}
