<?php

namespace tests;

use fbt\FbtConfig;
use fbt\Runtime\Shared\FbtHooks;
use fbt\Runtime\Shared\intlNumUtils;
use Spatie\Snapshots\MatchesSnapshots;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    use MatchesSnapshots;

    public function setUp()
    {
        FbtConfig::set('author', 'richard');
        FbtConfig::set('locale', 'sk_SK');
        FbtConfig::set('path', __DIR__ . '/');

        FbtHooks::register('onTerminating', function () {
            return false;
        });
    }

    protected function tearDown()
    {
        parent::tearDown();

        FbtHooks::storePhrases();
        IntlNumUtils::config([]);
    }
}
