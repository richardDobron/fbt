<?php

namespace tests;

use fbt\FbtConfig;
use fbt\Runtime\Shared\FbtHooks;
use fbt\Runtime\Shared\intlNumUtils;
use Spatie\Snapshots\MatchesSnapshots;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    use MatchesSnapshots;

    /**
     * Storage path of the collected strings and translations, separate for each
     * test process (so that parallel test runs don't share their files)
     */
    public static function storagePath(): string
    {
        static $path = null;

        if ($path === null) {
            $path = sys_get_temp_dir() . '/fbt-tests-' . getmypid() . '/';
            if (! is_dir($path)) {
                mkdir($path, 0777, true);
            }
            // Start from a clean state
            @unlink($path . '.source_strings.json');
            @unlink($path . 'translatedFbts.json');
        }

        return $path;
    }

    public function setUp(): void
    {
        FbtConfig::set('author', 'richard');
        FbtConfig::set('locale', 'sk_SK');
        FbtConfig::set('path', self::storagePath());

        FbtHooks::register('onTerminating', function () {
            return false;
        });
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        FbtHooks::storePhrases();
        IntlNumUtils::config([]);
    }
}
