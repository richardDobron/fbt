<?php

declare(strict_types=1);

namespace tests\translations;

use fbt\FbtConfig;
use fbt\Lib\IntlViewerContext;
use fbt\Runtime\FbtTranslations;
use fbt\Runtime\Shared\FbtHooks;
use fbt\Services\TranslationsGeneratorService;
use fbt\Transform\FbtTransform\FbtTransform;

class fbtTranslationsTest extends \tests\TestCase
{
    private function registerTranslations()
    {
        FbtHooks::storePhrases();

        $generateTranslationsService = new TranslationsGeneratorService();
        $generateTranslationsService->exportTranslations(
            FbtConfig::get('path'),
            __DIR__ . '/data/*.json',
            null,
            true
        );

        $translations = FbtConfig::get('path') . '/translatedFbts.json';
        FbtTranslations::registerTranslations(json_decode(file_get_contents($translations), true));
    }

    private function registerStdinTranslations()
    {
        FbtHooks::storePhrases();

        $translationsGeneratorService = new TranslationsGeneratorService();
        $translationsGeneratorService->exportTranslations(
            FbtConfig::get('path'),
            null,
            file_get_contents(__DIR__ . '/stdin-data/translation_input.json'),
            true
        );

        $translations = FbtConfig::get('path') . '/translatedFbts.json';
        FbtTranslations::registerTranslations(json_decode(file_get_contents($translations), true));
    }

    private static function transform($document): string
    {
        return FbtTransform::transform($document);
    }

    public function testViewingUserTokenWithUserGender()
    {
        $translateFbt = function ($gender, $viewer) {
            IntlViewerContext::setGender($viewer);

            return <<<FBT
<fbt desc="Notification about commenting on a photo.">
    <fbt:name name="name1" gender="$gender">Alex</fbt:name> commented on a photo that you're tagged in
</fbt>
FBT;
        };

        FbtHooks::locale('en_US');

        $this->assertEquals('Alex commented on a photo that you\'re tagged in', self::transform($translateFbt(1, 1)));

        $this->registerTranslations();

        $tests = [
            ['args' => [1, 1], 'locale' => null, 'expected' => 'Alex komentoval fotku, kde ste ozna??en??'],
            ['args' => [2, 1], 'locale' => null, 'expected' => 'Alex komentovala fotku, kde ste ozna??en??'],
            ['args' => [3, 1], 'locale' => null, 'expected' => 'Pou????vate?? Alex komentoval fotku, kde ste ozna??en??'],
            ['args' => [1, 2], 'locale' => null, 'expected' => 'Alex komentoval fotku, kde ste ozna??en??'],
            ['args' => [2, 2], 'locale' => null, 'expected' => 'Alex komentovala fotku, kde ste ozna??en??'],
            ['args' => [3, 2], 'locale' => null, 'expected' => 'Pou????vate?? Alex komentoval fotku, kde ste ozna??en??'],
            ['args' => [1, 1], 'locale' => 'cs_CZ', 'expected' => 'Alex okomentoval fotku, ve kter?? jste ozna??eni'],
            ['args' => [2, 1], 'locale' => 'cs_CZ', 'expected' => 'Alex okomentovala fotku, ve kter?? jste ozna??eni'],
            ['args' => [3, 1], 'locale' => 'cs_CZ', 'expected' => 'Alex okomentoval(a) fotku, ve kter?? jste ozna??eni'],
        ];

        foreach ($tests as $test) {
            FbtHooks::locale($test['locale']);

            $this->assertEquals($test['expected'], self::transform($translateFbt(...$test['args'])));
        }
    }

    public function testFbtNameWithUserGender()
    {
        $translateFbt = function ($gender, $number) {
            return <<<FBT
<fbt desc="Notification about adding a photos.">
    <fbt:name name="name1" gender="$gender">Alex</fbt:name> added <fbt:param name="number" number="true">$number</fbt:param> new photos.
</fbt>
FBT;
        };

        FbtHooks::locale('en_US');

        $this->assertEquals('Alex added 6 new photos.', self::transform($translateFbt(1, 6)));
        FbtHooks::storePhrases();

        $this->registerTranslations();

        FbtHooks::locale(null);

        $tests = [
            ['args' => [1, 1], 'expected' => 'Alex pridal 1 nov?? fotku.'],
            ['args' => [1, 2], 'expected' => 'Alex pridal 2 nov?? fotky.'],
            ['args' => [1, 5], 'expected' => 'Alex pridal 5 nov??ch fotiek.'],
            ['args' => [2, 1], 'expected' => 'Alex pridala 1 nov?? fotku.'],
            ['args' => [2, 2], 'expected' => 'Alex pridala 2 nov?? fotky.'],
            ['args' => [2, 5], 'expected' => 'Alex pridala 5 nov??ch fotiek.'],
            ['args' => [3, 1], 'expected' => 'Pou????vate?? Alex pridal 1 nov?? fotku.'],
            ['args' => [3, 2], 'expected' => 'Pou????vate?? Alex pridal 2 nov?? fotky.'],
            ['args' => [3, 5], 'expected' => 'Pou????vate?? Alex pridal 5 nov??ch fotiek.'],
        ];

        foreach ($tests as $test) {
            $this->assertEquals($test['expected'], self::transform($translateFbt(...$test['args'])));
        }
    }

    public function testSubjectWithNumber()
    {
        $translateFbt = function ($subject, $number) {
            return <<<FBT
<fbt desc="Text indicating the number of followers of a user" subject="$subject">
    Followed by <strong><fbt:param name="number of followers" number="true">$number</fbt:param> person</strong>
</fbt>
FBT;
        };

        FbtHooks::locale('en_US');

        $this->assertEquals('Followed by <strong>66 person</strong>', self::transform($translateFbt(1, 66)));
        $this->assertEquals([
            [
                "t" => [
                    "*" => [
                        "*" => "{number of followers} person",
                    ],
                ],
                "m" => [
                    [
                        "token" => "__subject__",
                        "type" => 1,
                    ],
                    [
                        "token" => "number of followers",
                        "type" => 2,
                    ],
                ],
            ],
            [
                "t" => [
                    "*" => "Followed by {=[number of followers] person}",
                ],
                "m" => [
                    [
                        "token" => "__subject__",
                        "type" => 1,
                    ],
                ],
            ],
        ], array_column(FbtTransform::$phrases, 'jsfbt'));

        $this->registerTranslations();

        FbtHooks::locale(null);

        $this->assertEquals('Sledovan?? <strong>1 osobou</strong>', self::transform($translateFbt(1, 1)));
        $this->assertEquals('Sledovan?? <strong>2 ??u??mi</strong>', self::transform($translateFbt(2, 2)));
        $this->assertEquals('Sledovan??/?? <strong>5 ??u??mi</strong>', self::transform($translateFbt(3, 5)));
    }

    public function testSubject()
    {
        $translateFbt = function ($subject) {
            return <<<FBT
<fbt desc="User(s) have poked the viewer" subject="$subject">
    <fbt:param name="name1">John</fbt:param> <span>poked you</span>.
</fbt>
FBT;
        };

        FbtHooks::locale('en_US');

        $this->assertEquals('John <span>poked you</span>.', self::transform($translateFbt(1)));
        $this->assertEquals([
            [
                "t" => [
                    "*" => "poked you",
                ],
                "m" => [
                    [
                        "token" => "__subject__",
                        "type" => 1,
                    ],
                ],
            ],
            [
                "t" => [
                    "*" => "{name1} {=poked you}.",
                ],
                "m" => [
                    [
                        "token" => "__subject__",
                        "type" => 1,
                    ],
                ],
            ],
        ], array_column(FbtTransform::$phrases, 'jsfbt'));

        $this->registerTranslations();

        FbtHooks::locale(null);

        $this->assertEquals('John <span>v??s ??tuchol</span>.', self::transform($translateFbt(1)));
        $this->assertEquals('John <span>v??s ??tuchla</span>.', self::transform($translateFbt(2)));
        $this->assertEquals('John <span>v??s ??tuchol/a</span>.', self::transform($translateFbt(3)));
    }

    public function testStdinTranslations()
    {
        $translateFbt = function ($subject) {
            return <<<FBT
<fbt desc="User(s) have poked the viewer" subject="$subject">
    <fbt:param name="name1">John</fbt:param> <span>poked you</span>.
</fbt>
FBT;
        };

        $this->registerStdinTranslations();

        FbtHooks::locale('cs_CZ');

        $this->assertEquals('John <span>v??s ????ouchl(a)</span>.', self::transform($translateFbt(1)));
        $this->assertEquals('John <span>v??s ????ouchl(a)</span>.', self::transform($translateFbt(2)));
        $this->assertEquals('John <span>v??s ????ouchl(a)</span>.', self::transform($translateFbt(3)));

        FbtHooks::locale('de_DE');

        $this->assertEquals('John <span>hat dich angestupst</span>.', self::transform($translateFbt(1)));
        $this->assertEquals('John <span>hat dich angestupst</span>.', self::transform($translateFbt(2)));
        $this->assertEquals('John <span>hat dich angestupst</span>.', self::transform($translateFbt(3)));
    }

    public function testTranslations()
    {
        $phrase = function () {
            return self::transform(fbt('We have insufficient data to show for the selected time period.', 'Text for null state of insights data'));
        };

        $this->assertEquals('We have insufficient data to show for the selected time period.', $phrase());

        FbtHooks::locale('sk_SK');

        FbtTranslations::registerTranslations([
            'sk_SK' => [
                "3ppzgb" => "Pre vybran?? ??asov?? ??sek nem??me dostatok ??dajov na zobrazenie.",
            ],
        ]);

        $this->assertEquals('Pre vybran?? ??asov?? ??sek nem??me dostatok ??dajov na zobrazenie.', $phrase());

        FbtHooks::locale('de_DE');

        FbtTranslations::mergeTranslations([
            'de_DE' => [
                "3ppzgb" => "Nicht gen??gend Daten f??r den ausgew??hlten Zeitraum.",
            ],
        ]);

        $this->assertEquals('Nicht gen??gend Daten f??r den ausgew??hlten Zeitraum.', $phrase());
    }

    public function testInlineReporting()
    {
        FbtHooks::inlineMode('TRANSLATE');

        $fbt = <<<FBT
<fbt desc="Text next to name change field showing the name policy.">
    <strong>Please note:</strong> If you change your name on Facebook, you can't change it again for 60 days. Don't add any unusual capitalization, punctuation, characters or random words. <a href="/help/" target="_blank">Learn more</a>.
</fbt>
FBT;

        FbtHooks::locale('sk_SK');
        FbtHooks::register('canInline', function () {
            return true;
        });

        FbtTranslations::registerTranslations([
            'sk_SK' => [
                "414lhL" => [
                    "{=Please note:} Ak si zmen??te meno na Facebooku, najbli??????ch 60 dn?? si ho nebudete m??c?? znova zmeni??. V mene nepou??ite ??iadne ne??tandardn?? ve??k?? p??smen??, interpunk??n?? znamienka, znaky ani nezvy??ajn?? slov??. {=Learn more}.",
                    "c119116e3a5d3f69b55d8aa5545c036e",
                ],
                "4r61hf" => [
                    "Pre????tajte si viac",
                    "9cbb0c31a8f765e110243d61e870f56b",
                ],
                "3dwYKr" => [
                    "Upozornenie:",
                    "36bf03959a0b3b8a4303657c703c7aba",
                ],
            ],
        ]);

        $this->assertSame(<<<FBT
<em class="intlInlineMode_normal" data-intl-hash="c119116e3a5d3f69b55d8aa5545c036e" data-intl-locale="sk_SK"><strong><em class="intlInlineMode_normal" data-intl-hash="36bf03959a0b3b8a4303657c703c7aba" data-intl-locale="sk_SK">Upozornenie:</em></strong> Ak si zmen??te meno na Facebooku, najbli??????ch 60 dn?? si ho nebudete m??c?? znova zmeni??. V mene nepou??ite ??iadne ne??tandardn?? ve??k?? p??smen??, interpunk??n?? znamienka, znaky ani nezvy??ajn?? slov??. <a href="/help/" target="_blank"><em class="intlInlineMode_normal" data-intl-hash="9cbb0c31a8f765e110243d61e870f56b" data-intl-locale="sk_SK">Pre????tajte si viac</em></a>.</em>
FBT
            , self::transform($fbt));


        FbtTranslations::mergeTranslations([
            'sk_SK' => [
                "4EcADd" => [
                    "N??zov produktu",
                    "f32feba0d988a057d9fbe729a6192ed7",
                ],
            ],
        ]);
        $fbt = <<<FBT
<title><fbt desc="page title" reporting="false">Product name</fbt></title>
FBT;

        $this->assertSame(<<<FBT
<title>N??zov produktu</title>
FBT
            , self::transform($fbt));

        FbtHooks::unregister('canInline');
    }
}
