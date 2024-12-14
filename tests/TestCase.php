<?php

namespace tests;

use fbt\FbtConfig;
use fbt\Runtime\Shared\FbtHooks;
use Spatie\Snapshots\MatchesSnapshots;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    use MatchesSnapshots;

    public function setUp(): void
    {
        FbtConfig::set('author', 'richard');
        FbtConfig::set('locale', 'sk_SK');
        FbtConfig::set('path', __DIR__ . '/');

        FbtHooks::register('onTerminating', function () {
            return false;
        });
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        FbtHooks::storePhrases();
    }
}
