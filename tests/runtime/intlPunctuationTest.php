<?php

namespace tests\runtime;

use fbt\Runtime\Shared\FbtHooks;
use fbt\Runtime\Shared\IntlPunctuation;
use fbt\Runtime\Shared\substituteTokens;

/**
 * Port of runtime/shared/__tests__/IntlPunctuation-test.js from facebook/fbt v1.0.0
 */
class intlPunctuationTest extends \tests\TestCase
{
    private const FW_Q_MARK = "\u{ff1f}";
    private const FW_BANG = "\u{ff01}";
    private const HINDI_FS = "\u{0964}";
    private const MYANMAR_FS = "\u{104B}";
    private const CJK_FS = "\u{3002}";
    private const ELLIP = "\u{2026}";
    private const THAI_ELLIP = "\u{0e2f}";
    private const LAOTIAN_ELLIP = "\u{0eaf}";
    private const MONGOLIAN_ELLIP = "\u{1801}";

    protected function tearDown(): void
    {
        FbtHooks::locale(null);

        parent::tearDown();
    }

    public function testStripsRedundantStops()
    {
        $questionMarks = [
            '?', self::FW_Q_MARK, '.', self::HINDI_FS, self::MYANMAR_FS, self::CJK_FS, '!', self::FW_BANG,
            self::ELLIP, self::THAI_ELLIP, self::LAOTIAN_ELLIP, self::MONGOLIAN_ELLIP,
        ];
        $bangs = ['!', self::FW_BANG, '?', self::FW_Q_MARK, '.', self::HINDI_FS, self::MYANMAR_FS, self::CJK_FS];
        $stops = ['.', self::HINDI_FS, self::MYANMAR_FS, self::CJK_FS, '!', self::FW_BANG];
        $ellipses = [
            self::ELLIP, self::THAI_ELLIP, self::LAOTIAN_ELLIP, self::MONGOLIAN_ELLIP,
            '.', self::HINDI_FS, self::MYANMAR_FS, self::CJK_FS, '!', self::FW_BANG,
        ];

        $expected = [
            '?' => $questionMarks,
            self::FW_Q_MARK => $questionMarks,
            '!' => $bangs,
            self::FW_BANG => $bangs,
            '.' => $stops,
            self::HINDI_FS => $stops,
            self::MYANMAR_FS => $stops,
            self::CJK_FS => $stops,
            self::ELLIP => $ellipses,
            self::THAI_ELLIP => $ellipses,
            self::LAOTIAN_ELLIP => $ellipses,
            self::MONGOLIAN_ELLIP => $ellipses,
        ];

        foreach ($expected as $prefix => $suffixes) {
            foreach ($suffixes as $suffix) {
                $this->assertSame(
                    ['prefix' => (string)$prefix, 'suffix' => $suffix, 'result' => ''],
                    ['prefix' => (string)$prefix, 'suffix' => $suffix, 'result' => IntlPunctuation::dedupeStops((string)$prefix, $suffix)]
                );
            }
        }
    }

    public function testDoesNotStripStopsItShouldNot()
    {
        $this->assertSame('?', IntlPunctuation::dedupeStops('.', '?'));
        $this->assertSame('?', IntlPunctuation::dedupeStops('T.J.', '?'));
        $this->assertSame('!', IntlPunctuation::dedupeStops('', '!'));
        $this->assertSame('...', IntlPunctuation::dedupeStops('Wait.', '...'));
    }

    /**
     * Expected values were produced by the upstream JavaScript implementation.
     *
     * @dataProvider phonologicalRulesProvider
     */
    public function testAppliesPhonologicalRules(string $locale, string $text, string $expected)
    {
        FbtHooks::locale($locale);

        $this->assertSame($expected, IntlPunctuation::applyPhonologicalRules($text));
    }

    public function phonologicalRulesProvider(): array
    {
        return [
            ['en_US', "it's", "it's"],
            ['en_US', "\u{0001}James's\u{0001}'s car", "James's car"],
            ['tr_TR', "\u{0001}Ozgur\u{0001}'(y)i gördüm", "Ozgur'u gördüm"],
            ['tr_TR', "\u{0001}Ali\u{0001}'(n)in evi", "Ali'nin evi"],
            ['tr_TR', "\u{0001}Kitap\u{0001}'Da", "Kitap'ta"],
            ['tr_TR', "\u{0001}Ev\u{0001} de/da", 'Ev de'],
            // js~php diff: apostrophes stay HTML-encoded (upstream: "Türk'çe 'q'")
            ['tr_TR', "Türk&#039;çe ‘q’", 'Türk&#039;çe &#039;q&#039;'],
            ['de_DE', "\u{0001}Hans\u{0001}s Auto", 'Hans Auto'],
            ['nb_NO', "\u{0001}Lars\u{0001}s bil", "Lars' bil"],
            ['da_DK', "\u{0001}ABC\u{0001}s", "ABC's"],
            ['es_ES', "y \u{0001}Isabel\u{0001}", 'e Isabel'],
            ['sk_SK', "z \u{0001}sestrou\u{0001}", 'zo sestrou'],
            ['sk_SK', "v \u{0001}Viedni\u{0001}", 'vo Viedni'],
            ['bg_BG', "в \u{0001}Враца\u{0001}", 'във Враца'],
            ['ar_AR', "\u{0001}x\u{0001}\u{200f} ש", "x\u{200f} ש"],
            ['xx_XX', "a\u{0001}b", 'ab'],
            // References to non-existent groups are kept as literals, like in JS
            ['fb_HX', "y \u{0001}Emma\u{0001}", 'e $2mma'],
        ];
    }

    public function testSubstituteTokensDedupesStops()
    {
        $this->assertSame('Play Chess!', substituteTokens::substitute('Play {game}!', ['game' => 'Chess!']));
        $this->assertSame('Hi T.J.?', substituteTokens::substitute('Hi {name}?', ['name' => 'T.J.']));
        $this->assertSame('Is it true?', substituteTokens::substitute('Is it {value}?', ['value' => true]));
    }

    public function testSubstituteTokensAppliesPhonologicalRules()
    {
        FbtHooks::locale('tr_TR');

        $this->assertSame('Türk&#039;çe Ali', substituteTokens::substitute('Türk’çe {name}', ['name' => 'Ali']));
        // Encoded values must not be decoded (e.g. inside HTML attributes)
        $this->assertSame("<a title='x&#039; onmouseover=alert(1) &#039;'>", substituteTokens::substitute('{link}', ['link' => "<a title='x&#039; onmouseover=alert(1) ’'>"]));
    }
}
