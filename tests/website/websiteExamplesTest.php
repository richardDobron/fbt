<?php

declare(strict_types=1);

namespace tests\website;

use fbt\fbt;
use fbt\FbtConfig;
use fbt\Runtime\FbtTranslations;
use fbt\Runtime\Shared\FbtHooks;
use fbt\Services\TranslationsGeneratorService;

class websiteExamplesTest extends \tests\TestCase
{
    private const EXAMPLES = __DIR__ . '/../../website/src/examples.json';

    private static function render(string $example, array $values): string
    {
        switch ($example) {
            case 'plural':
                $fbt = fbt(
                    'You have ' . fbt::plural('unread message', $values['count'], [
                        'many' => 'unread messages',
                        'showCount' => 'yes',
                        'name' => 'count',
                    ]) . '.',
                    'Unread messages in the inbox'
                );

                break;
            case 'name':
                $fbt = fbt(
                    fbt::name('name', $values['name'], $values['gender']) . ' shared a photo with you.',
                    'Notification about a shared photo'
                );

                break;
            case 'enum':
                $fbt = fbt(
                    'Your order has been ' . fbt::enum($values['status'], [
                        'shipped' => 'shipped',
                        'delivered' => 'delivered',
                        'cancelled' => 'cancelled',
                    ]) . '.',
                    'Status of an order'
                );

                break;
            case 'pronoun':
                $fbt = fbt(
                    fbt::param('name', $values['name']) . ' updated ' .
                    fbt::pronoun('possessive', $values['gender'], ['human' => true]) . ' profile.',
                    'Notification about an updated profile'
                );

                break;
            default:
                throw new \InvalidArgumentException("Unknown example $example");
        }

        return (string)$fbt;
    }

    public function testExamples()
    {
        $dir = sys_get_temp_dir() . '/fbt-website-' . uniqid();
        mkdir($dir);
        FbtConfig::set('path', $dir);

        $data = json_decode(file_get_contents(self::EXAMPLES), true);
        $update = (bool)getenv('FBT_UPDATE_EXAMPLES');
        $inlineMode = FbtHooks::inlineMode();

        try {
            FbtHooks::inlineMode('NO_INLINE');
            FbtHooks::locale('en_US');
            foreach ($data['examples'] as $example => $cases) {
                foreach ($cases as $case) {
                    self::render($example, $case['values']);
                }
            }
            FbtHooks::storePhrases();

            (new TranslationsGeneratorService())->exportTranslations($dir, __DIR__ . '/translations/*.json', null, false);
            FbtTranslations::registerTranslations(json_decode(file_get_contents($dir . '/translatedFbts.json'), true));

            foreach ($data['examples'] as $example => $cases) {
                foreach ($cases as $index => $case) {
                    foreach (array_keys($data['locales']) as $locale) {
                        FbtHooks::locale($locale);
                        $output = self::render($example, $case['values']);

                        if ($update) {
                            $data['examples'][$example][$index]['outputs'][$locale] = $output;
                        } else {
                            $this->assertSame($case['outputs'][$locale] ?? null, $output, "$example #$index ($locale)");
                        }
                    }
                }
            }
        } finally {
            FbtHooks::inlineMode($inlineMode);
            FbtHooks::locale(null);
            FbtConfig::set('path', self::storagePath());
            array_map('unlink', glob($dir . '/{,.}*.json', GLOB_BRACE));
            rmdir($dir);
        }

        if ($update) {
            file_put_contents(self::EXAMPLES, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            $this->markTestIncomplete('Outputs of the examples were updated.');
        }
    }
}
