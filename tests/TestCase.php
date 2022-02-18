<?php

namespace tests;

use fbt\FbtConfig;
use fbt\Runtime\Shared\FbtHooks;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
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
    }
}
