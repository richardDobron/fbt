<?php

namespace tests\transform;

use fbt\fbt;
use fbt\FbtConfig;
use fbt\Runtime\Shared\FbtHooks;
use fbt\Transform\FbtRuntime\FbtRuntimeTransform;
use fbt\Transform\FbtTransform\FbtTransform;
use fbt\Util\JsJson;

/**
 * Golden tests: checks the PHP port of the babel-plugin-fbt v1.0.0 compiler
 * against the expected outputs of the upstream test suite.
 *
 * The expected `jsfbt` payloads were extracted verbatim (key order included)
 * from upstream (facebook/fbt, babel-plugin-fbt v1.0.0):
 * - packages/babel-plugin-fbt/src/__tests__/fbtFunctional-test.js (inline `output` payloads)
 * - packages/babel-plugin-fbt/src/__tests__/__snapshots__/{fbtAutoWrap,fbtJsx,fbt}-test.js.snap
 * - packages/babel-plugin-fbt/src/__tests__/fbtStaticJSModule-test.js (inline outputs)
 * - packages/babel-plugin-fbt-runtime/__tests__/fbtRuntime-test.js (runtime tables, `hk`)
 *
 * The JSX/JS inputs were translated by hand. Deliberate PHP differences:
 * - whitespace-only text between HTML elements is kept, so the markup is written
 *   without whitespace where JSX drops it
 * - plural/enum/pronoun deduplication needs an explicit `key` option
 * - the runtime values (JS expressions upstream) are literal values
 */
class upstreamGoldenTest extends \tests\TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        FbtConfig::set('collectFbt', true);
        FbtConfig::set('fbtCommon', []);
        FbtHooks::locale('en_US');
        FbtTransform::$phrases = [];
        FbtTransform::$childToParent = [];
        fbt::_purgeCache();
    }

    protected function tearDown(): void
    {
        FbtConfig::set('fbtCommon', []);
        FbtTransform::$phrases = [];
        FbtTransform::$childToParent = [];
        FbtHooks::locale(null);

        parent::tearDown();
    }

    // ---------------------------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------------------------

    private static function transform(string $html): string
    {
        return FbtTransform::transform($html);
    }

    /**
     * JSON of a collected jsfbt, serialized like JS' JSON.stringify()
     */
    private static function jsfbtJson(array $jsfbt): string
    {
        $m = json_encode(
            $jsfbt['m'],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS
        );

        return '{"t":' . JsJson::stringify($jsfbt['t']) . ',"m":' . $m . '}';
    }

    /**
     * Pretty-prints a JSON string keeping its key order (for readable diffs)
     */
    private static function pretty(string $json): string
    {
        return json_encode(
            json_decode($json, false, 512, JSON_THROW_ON_ERROR),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS
        );
    }

    /**
     * Asserts that the collected phrases have exactly the upstream jsfbt payloads.
     *
     * @param bool $ordered - false when the upstream order (order of the payloads in
     *   the emitted code) differs from the collection order, e.g. for nested <fbt>s
     */
    private function assertPayloads(string $id, bool $ordered = true): void
    {
        $expected = array_map([self::class, 'pretty'], self::EXPECTED[$id]);
        $actual = array_map(function (array $phrase) {
            return self::pretty(self::jsfbtJson($phrase['jsfbt']));
        }, FbtTransform::$phrases);

        if (! $ordered) {
            sort($expected);
            sort($actual);
        }

        $this->assertSame(implode("\n", $expected), implode("\n", $actual), "Upstream scenario '$id'");
        // byte-exact (the order of keys included)
        foreach (array_values($ordered ? self::EXPECTED[$id] : []) as $i => $json) {
            $this->assertSame($json, self::jsfbtJson(FbtTransform::$phrases[$i]['jsfbt']));
        }
    }

    private function assertRuntime(int $phraseIndex, $expectedTable, string $expectedHk): void
    {
        $runtime = FbtRuntimeTransform::transform(FbtTransform::$phrases[$phraseIndex]);

        $this->assertSame(JsJson::stringify(['t' => $expectedTable]), JsJson::stringify(['t' => $runtime['table']]));
        $this->assertSame(['hk' => $expectedHk], $runtime['options']);
    }

    // ---------------------------------------------------------------------------------------------
    // fbtFunctional-test.js
    // ---------------------------------------------------------------------------------------------

    public function testSimpleString()
    {
        $this->assertSame('A simple string', self::transform('<fbt desc="It\'s simple">A simple string</fbt>'));
        $this->assertPayloads('simple');

        // The functional form collects the same phrase
        FbtTransform::$phrases = [];
        $this->assertSame('A simple string', (string)fbt('A simple string', "It's simple"));
        $this->assertSame(
            ['desc' => "It's simple", 'text' => 'A simple string'],
            FbtTransform::$phrases[0]['jsfbt']['t']
        );
    }

    public function testNewlinesWithinArguments()
    {
        $this->assertSame('a b val1 c d val2 e', (string)fbt(
            'a' . ' b ' . fbt::param('name1', 'val1') . ' c ' . ' d ' . fbt::param('name2', 'val2') . ' e ',
            'a'
        ));
        $this->assertPayloads('newlinesWithinArguments');
    }

    public function testParams()
    {
        $this->assertSame('A parameterized message to Alice', (string)fbt(
            'A parameterized message to ' . fbt::param('personName', 'Alice'),
            'Moar params'
        ));
        $this->assertPayloads('params');
    }

    public function testWellFormedOptions()
    {
        (string)fbt('A string that moved files', 'options!', [
            'author' => 'jwatson',
            'project' => 'Super Secret',
        ]);
        $this->assertPayloads('options');
    }

    public function testEnumWithArrayValues()
    {
        $this->assertSame('Click to see photos', (string)fbt(
            'Click to see ' . fbt::enum('photos', ['groups', 'photos', 'videos']),
            'enum as an array'
        ));
        $this->assertPayloads('enumArray');
    }

    public function testEnumWithValueMap()
    {
        $this->assertSame('Click to see groups', (string)fbt(
            'Click to see ' . fbt::enum('id1', ['id1' => 'groups', 'id2' => 'photos', 'id3' => 'videos']),
            'enum as an object'
        ));
        $this->assertPayloads('enumObject');
    }

    public function testEnumWithMoreTextAfter()
    {
        $this->assertSame('Hello, videos!', (string)fbt(
            'Hello, ' . fbt::enum('videos', ['groups', 'photos', 'videos']) . '!',
            'enums!'
        ));
        $this->assertPayloads('enumMoreText');
    }

    public function testDuplicateEnums()
    {
        // js~php diff: the enums share the same value only with an explicit `key`
        $this->assertSame('Look! Photos and photos!', (string)fbt(
            'Look! ' .
            fbt::enum('photos', ['groups' => 'Groups', 'photos' => 'Photos', 'videos' => 'Videos'], ['key' => 'e']) .
            ' and ' .
            fbt::enum('photos', ['groups' => 'groups', 'photos' => 'photos', 'videos' => 'videos'], ['key' => 'e']) .
            '!',
            'enums!'
        ));
        $this->assertPayloads('enumDuplicate');
    }

    public function testPluralsWithDifferentCounts()
    {
        $this->assertSame('1 cat and 2 dogs', (string)fbt(
            fbt::plural('cat', 1, ['name' => 'cat_token', 'showCount' => 'yes']) .
            ' and ' .
            fbt::plural('dog', 2, ['name' => 'dog_token', 'showCount' => 'yes']),
            'plurals'
        ));
        $this->assertPayloads('pluralDifferentCounts');
    }

    public function testPluralsSharingTheSameCount()
    {
        // js~php diff: the plurals share the same count only with an explicit `key`.
        // Equivalent markup (the trailing space of the singular text moved out of the plural)
        $this->assertSame('There was a like', self::transform(
            '<fbt desc="plurals">There ' .
            '<fbt:plural count="1" showCount="no" many="were " key="c">was</fbt:plural> ' .
            '<fbt:plural count="1" showCount="ifMany" many="likes" key="c">a like</fbt:plural></fbt>'
        ));
        $this->assertPayloads('pluralSameCount');

        FbtTransform::$phrases = [];
        $this->assertSame('There was a like', (string)fbt(
            'There ' .
            fbt::plural('was ', 1, ['showCount' => 'no', 'many' => 'were ', 'key' => 'c']) .
            fbt::plural('a like', 1, ['showCount' => 'ifMany', 'many' => 'likes', 'key' => 'c']),
            'plurals'
        ));
        $this->assertPayloads('pluralSameCount');
    }

    public function testMultiplePluralsWithNoShowCount()
    {
        $this->assertSame('There are 2 likes', self::transform(
            '<fbt desc="plurals">There ' .
            '<fbt:plural count="2" many="are " key="c">is</fbt:plural> ' .
            '<fbt:plural count="2" showCount="ifMany" many="likes" key="c">a like</fbt:plural></fbt>'
        ));
        $this->assertPayloads('pluralNoShowCount');

        FbtTransform::$phrases = [];
        $this->assertSame('There is a like', (string)fbt(
            'There ' .
            fbt::plural('is ', 1, ['many' => 'are ', 'key' => 'c']) .
            fbt::plural('a like', 1, ['showCount' => 'ifMany', 'many' => 'likes', 'key' => 'c']),
            'plurals'
        ));
        $this->assertPayloads('pluralNoShowCount');
    }

    public function testNames()
    {
        $this->assertSame('You just friended Sarah', (string)fbt(
            'You just friended ' . fbt::name('name', 'Sarah', 1),
            'names'
        ));
        $this->assertPayloads('names');
    }

    public function testNumberParam()
    {
        $this->assertSame('Click to see 5 links', (string)fbt(
            'Click to see ' . fbt::param('count', 5, ['number' => true]) . ' links',
            'variations!'
        ));
        $this->assertPayloads('numberTrue');
    }

    public function testSameParam()
    {
        $this->assertSame('val1 and val1', (string)fbt(
            fbt::param('name1', 'val1') . ' and ' . fbt::sameParam('name1'),
            'd'
        ));
        $this->assertPayloads('sameParam');
    }

    public function testNumberParamAndSameParam()
    {
        $this->assertSame('You have 42 likes. Comment on it to get more than 42 likes', (string)fbt(
            'You have ' .
            fbt::param('count', 42, ['number' => true]) .
            ' likes. Comment on it to get more than ' .
            fbt::sameParam('count') .
            ' likes',
            'test variations + sameParam'
        ));
        $this->assertPayloads('numberSameParam');
    }

    public function testObjectPronoun()
    {
        $this->assertSame('I know her.', (string)fbt('I know ' . fbt::pronoun('object', 1) . '.', 'object pronoun'));
        $this->assertPayloads('pronounObject');
    }

    public function testSubjectAndReflexivePronouns()
    {
        // js~php diff: the pronouns share the same gender only with an explicit `key`
        $this->assertSame('He wished himself a happy birthday.', (string)fbt(
            fbt::pronoun('subject', 2, ['capitalize' => 'true', 'human' => 'true', 'key' => 'g']) .
            ' wished ' .
            fbt::pronoun('reflexive', 2, ['human' => 'true', 'key' => 'g']) .
            ' a happy birthday.',
            'subject+reflexive pronouns'
        ));
        $this->assertPayloads('pronounSubjectReflexive');
    }

    public function testPossessivePronoun()
    {
        $this->assertSame('It is her birthday.', (string)fbt('It is ' . fbt::pronoun('possessive', 1) . ' birthday.', 'possessive pronoun'));
        $this->assertPayloads('pronounPossessive');
    }

    public function testFbtNestedInsideName()
    {
        // The inner fbt() is evaluated (and collected) first in PHP
        $this->assertSame('a v b', (string)fbt(
            'a ' . fbt::name('name', (string)fbt(fbt::param('paramName', 'v'), 'desc inner'), 1) . ' b',
            'desc'
        ));
        $this->assertPayloads('fbtInsideName', false);
    }

    public function testJsxFragmentNestedWithParam()
    {
        $this->assertSame('A1 <a>B1 <b>C1 val C2</b> B2</a> A2', (string)fbt(
            [
                'A1 ',
                '<a> B1 <b> C1 ' . fbt::param('paramName', 'val') . ' C2 </b> B2 </a>',
                ' A2',
            ],
            'string with nested JSX fragments',
            ['subject' => 1]
        ));
        $this->assertPayloads('jsxFragmentWithParam');
        $this->assertSame([1 => 0, 2 => 1], FbtTransform::$childToParent);
    }

    public function testTwoNestedElements()
    {
        $this->assertSame(
            '<b class="padRight">Alice</b> has shared <a class="neatoLink" href="#" tabindex="123" id="uniq"><strong>3 photos</strong></a> with you',
            self::transform(
                '<fbt desc="example 1">' .
                '<fbt:param name="name" gender="1"><b class="padRight">Alice</b></fbt:param> has shared ' .
                // JSX keeps the whitespace around <strong> (first/last child of <a>)
                '<a class="neatoLink" href="#" tabindex="123" id="uniq"> <strong>' .
                '<fbt:plural many="photos" showCount="ifMany" count="3">a photo</fbt:plural>' .
                '</strong> </a> with you</fbt>'
            )
        );
        $this->assertPayloads('twoNestedElements');
        $this->assertSame([1 => 0, 2 => 1], FbtTransform::$childToParent);
    }

    public function testMultipleLevelsOfNestedStrings()
    {
        $html = self::transform(
            '<fbt desc="example 1">' .
            '<b class="padRight"><fbt:enum enum-range=\'["today","yesterday"]\' value="today"/></b>, ' .
            '<fbt:param name="name" gender="1"><b class="padRight">Alice</b></fbt:param> has shared ' .
            // <a> children as in JSX: whitespace, plural, {' '}, ' with ', <strong>, whitespace
            // (the comment splits the text nodes like the JSX expression container does)
            '<a class="neatoLink" href="#"> ' .
            '<fbt:plural many="photos" showCount="ifMany" count="2">a photo</fbt:plural> <!----> with ' .
            '<strong><fbt:pronoun type="object" gender="1" human="true"/></strong> ' .
            '</a></fbt>'
        );
        $this->assertSame(
            '<b class="padRight">today</b>, <b class="padRight">Alice</b> has shared <a class="neatoLink" href="#">2 photos with <strong>her</strong></a>',
            $html
        );
        $this->assertPayloads('multipleLevels');
        $this->assertSame([1 => 0, 2 => 0, 3 => 2], FbtTransform::$childToParent);
    }

    public function testNestedFragmentWithStringVariationArguments()
    {
        $html = (string)fbt(
            [
                'A1 ',
                '<a> B1 <b> C1 ' .
                fbt::param('count', 5, ['number' => true]) .
                ' C2 ' .
                fbt::plural('cat', 2, ['value' => 'two', 'name' => 'cat_token', 'showCount' => 'ifMany', 'many' => 'cats']) .
                ' </b> B2 </a>',
                ' A2',
            ],
            'string with nested JSX fragments'
        );
        $this->assertSame('A1 <a>B1 <b>C1 5 C2 two cats</b> B2</a> A2', $html);
        $this->assertPayloads('nestedFragmentSvArgs');
    }

    // ---------------------------------------------------------------------------------------------
    // fbtAutoWrap-test.js
    // ---------------------------------------------------------------------------------------------

    public function testAutoWrapOneLevel()
    {
        // <link> (a void element in HTML) is replaced by <a>
        $this->assertSame(
            '<a href="#">Your friends</a> liked your video',
            self::transform('<fbt desc="d"><a href="#">Your friends</a> liked your video</fbt>')
        );
        $this->assertPayloads('autoOneLevel');
        $this->assertSame([1 => 0], FbtTransform::$childToParent);
    }

    public function testAutoWrapNestedLevel()
    {
        $this->assertSame(
            '<a href="#">Your friends <b>liked</b></a> your video',
            self::transform('<fbt desc="d"><a href="#">Your friends <b>liked</b></a> your video</fbt>')
        );
        $this->assertPayloads('autoNestedLevel');
        $this->assertSame([1 => 0, 2 => 1], FbtTransform::$childToParent);
    }

    public function testAutoWrapTwoChildren()
    {
        self::transform('<fbt desc="d"><div>wrap once</div><div>wrap twice</div></fbt>');
        $this->assertPayloads('autoTwoChildren');
        $this->assertSame([1 => 0, 2 => 0], FbtTransform::$childToParent);
    }

    public function testAutoWrapTwoChildrenAndOneNested()
    {
        self::transform('<fbt desc="d"><div>wrap once <div>and also</div></div><div>wrap twice</div> complicated</fbt>');
        $this->assertPayloads('autoTwoChildrenOneNested');
        $this->assertSame([1 => 0, 2 => 1, 3 => 0], FbtTransform::$childToParent);
    }

    public function testAutoWrapTwoChildrenWithOneNestedLevel()
    {
        self::transform(
            // JSX keeps the whitespace before the inner <div> (first child)
            '<fbt desc="d"><div href="#"> <div href="#">this is</div> a doubly</div> nested test ' .
            '<div href="#">with an additional level</div></fbt>'
        );
        $this->assertPayloads('autoTwoChildrenOneNestedLevel');
    }

    public function testAutoWrapTwoNestedWithAnExtraLevel()
    {
        self::transform(
            '<fbt desc="d">' .
            '<div href="#">one <div href="#">two <div href="#">test</div></div></div>' .
            '<div href="#">three <div href="#">four</div></div>' .
            '</fbt>'
        );
        $this->assertPayloads('autoExtraLevel');
        $this->assertSame([1 => 0, 2 => 1, 3 => 2, 4 => 0, 5 => 4], FbtTransform::$childToParent);
    }

    public function testAutoWrapFbtNestedInExplicitParam()
    {
        $html = self::transform(
            '<fbt desc="d"><fbt:param name="explicit fbt param"><div>' .
            '<fbt desc="d2">explicit fbt param <div>with a nested implicit param</div></fbt>' .
            '</div></fbt:param></fbt>'
        );
        $this->assertSame('<div>explicit fbt param <div>with a nested implicit param</div></div>', $html);
        $this->assertPayloads('autoFbtInParam', false);
    }

    public function testAutoWrapFbtNextToExplicitParam()
    {
        self::transform(
            '<fbt desc="d"><fbt:param name="explicit param next to"><div>' .
            '<fbt desc="d2">explicit param next to</fbt>' .
            '</div></fbt:param><div>an implicit param</div></fbt>'
        );
        $this->assertPayloads('autoFbtNextToParam', false);
    }

    public function testAutoWrapExplicitParamsNestedInImplicitParams()
    {
        self::transform(
            '<fbt desc="d"><div>this is a test ' .
            '<fbt:param name="to make sure that explicit params under an implicit node"><a>' .
            '<fbt desc="d2">to make sure that explicit tags <b>under an implicit node</b></fbt>' .
            '</a></fbt:param>' .
            '<fbt:param name="and ones that are next to each other"><a>' .
            '<fbt desc="d3">and ones that are next <b>to each other</b></fbt>' .
            '</a></fbt:param>' .
            ' under an implicit tag are wrapped with [ ]</div>' .
            '<fbt:param name="but free standing ones are not"><a>' .
            '<fbt desc="d3">but free standing ones <b>are not</b></fbt>' .
            '</a></fbt:param></fbt>'
        );
        $this->assertPayloads('autoExplicitInImplicit', false);
    }

    public function testAutoWrapMultipleFbtCalls()
    {
        self::transform(
            '<div><fbt desc="one"><div href="#">first</div> fbt call</fbt>' .
            '<fbt desc="two"><div href="#">second</div> test</fbt></div>'
        );
        $this->assertPayloads('autoMultipleFbts');
        $this->assertSame([1 => 0, 3 => 2], FbtTransform::$childToParent);
    }

    public function testAutoWrapMultipleVariationsInNestedStrings()
    {
        $html = self::transform(
            '<fbt desc="some-desc">Level 1 <fbt:param name="foo">foo</fbt:param>' .
            '<a>Level 2 <fbt:name name="bar" gender="1">bar</fbt:name>' .
            '<b>Level 3 <fbt:plural name="baz" count="2" showCount="yes">cat</fbt:plural></b>' .
            '</a></fbt>'
        );
        $this->assertSame('Level 1 foo<a>Level 2 bar<b>Level 3 2 cats</b></a>', $html);
        $this->assertPayloads('autoVariations');
    }

    public function testAutoWrapMultipleVariationsAndSubject()
    {
        self::transform(
            '<fbt desc="description" subject="1">Level 1 ' .
            '<a href="#new"><fbt:pronoun type="possessive" gender="1"/> some_pronoun</a>' .
            '<b>Level 2 <fbt:plural name="foo" count="2" showCount="yes">cat</fbt:plural></b>' .
            '</fbt>'
        );
        $this->assertPayloads('autoVariationsSubject');
    }

    public function testAutoWrapSubstringsThatLookIdentical()
    {
        $html = self::transform(
            '<fbt desc="description" subject="1">Level 1 ' .
            '<a href="#new"><fbt:pronoun type="possessive" gender="1"/> some_pronoun</a>' .
            '<b>Level 2 <a href="#new"><fbt:pronoun type="possessive" gender="2"/> some_pronoun</a></b>' .
            '</fbt>'
        );
        $this->assertSame('Level 1 <a href="#new">her some_pronoun</a><b>Level 2 <a href="#new">his some_pronoun</a></b>', $html);
        $this->assertPayloads('autoIdentical');
        $this->assertSame([1 => 0, 2 => 0, 3 => 2], FbtTransform::$childToParent);
    }

    // ---------------------------------------------------------------------------------------------
    // fbtJsx-test.js, fbt-test.js, fbtStaticJSModule-test.js
    // ---------------------------------------------------------------------------------------------

    public function testJsxExplicitWhitespace()
    {
        $this->assertSame('1 2 3', self::transform(
            '<fbt desc="squelched"><fbt:param name="one">1</fbt:param> ' .
            '<fbt:param name="two">2</fbt:param> <fbt:param name="three">3</fbt:param></fbt>'
        ));
        $this->assertPayloads('jsxExplicitWhitespace');
    }

    public function testJsxNumberWithExplicitValue()
    {
        $this->assertSame('str 5', self::transform(
            '<fbt desc="d">str <fbt:param name="count" number="5">5</fbt:param></fbt>'
        ));
        $this->assertPayloads('jsxNumberExpression');
    }

    public function testJsxSameParam()
    {
        $this->assertSame('str Bar and Bar', self::transform(
            '<fbt desc="d">str <fbt:param name="foo">Bar</fbt:param> and <fbt:same-param name="foo"/></fbt>'
        ));
        $this->assertPayloads('jsxSameParam');
    }

    public function testJsxOrderOfParamsAndEnums()
    {
        $this->assertSame('Hello, fooybar', self::transform(
            '<fbt desc="some-desc">Hello, <fbt:param name="foo">foo</fbt:param>' .
            '<fbt:enum enum-range=\'["x","y"]\' value="y"/>' .
            '<fbt:param name="bar" number="3">bar</fbt:param></fbt>'
        ));
        $this->assertPayloads('jsxParamsAndEnums');
    }

    public function testJsxObjectPronoun()
    {
        self::transform('<fbt desc="d" project="p">I know <fbt:pronoun type="object" gender="2"/>.</fbt>');
        $this->assertPayloads('jsxPronounObject');
    }

    public function testJsxSubjectAndReflexivePronouns()
    {
        $html = self::transform(
            '<fbt desc="d" project="p">' .
            '<fbt:pronoun type="subject" gender="1" capitalize="true" human="true" key="g"/> wished ' .
            '<fbt:pronoun type="reflexive" gender="1" human="true" key="g"/> a happy birthday.' .
            '</fbt>'
        );
        $this->assertSame('She wished herself a happy birthday.', $html);
        $this->assertPayloads('jsxSubjectReflexive');
    }

    public function testJsxCommonString()
    {
        FbtConfig::set('fbtCommon', ['Done' => 'The description for the common string "Done"']);

        $this->assertSame('Done', self::transform('<fbt common="true">Done</fbt>'));
        $this->assertPayloads('jsxCommon');

        // "should handle fbt common attribute without value"
        FbtTransform::$phrases = [];
        $this->assertSame('Done', self::transform('<fbt common>Done</fbt>'));
        $this->assertPayloads('jsxCommon');
    }

    public function testJsxNoExtraSpace()
    {
        $this->assertSame('Hello, Alice!', self::transform(
            '<fbt desc="Greating in i18n demo">Hello, <fbt:param name="guest">Alice</fbt:param>!</fbt>'
        ));
        $this->assertPayloads('jsxNotInsertExtraSpace');
    }

    public function testJsxDedupePlurals()
    {
        $this->assertSame('There are 2 photos.', self::transform(
            '<fbt desc="desc...">There <fbt:plural count="2" many="are" key="n">is</fbt:plural> ' .
            '<fbt:plural count="2" showCount="yes" key="n">photo</fbt:plural>.</fbt>'
        ));
        $this->assertPayloads('jsxDedupePlurals');
    }

    public function testJsxLegacyPluralSpacing()
    {
        // Upstream's JSX drops the whitespace between the two plurals; the leading
        // spaces of the singular texts are kept (only right-trimmed).
        self::transform(
            '<fbt desc="">You can add <fbt:plural count="2" many="these" key="c"> this</fbt:plural>' .
            '<fbt:plural count="2" many="tags" key="c"> tag</fbt:plural> to anything.</fbt>'
        );
        $this->assertPayloads('jsxLegacyPluralSpace');
    }

    public function testJsxPreserveWhitespaceKnownBug()
    {
        // The whitespace of a <fbt> markup text is not preserved (same as upstream JSX)
        self::transform('<fbt desc="desc with 3   spaces" preserveWhitespace="true">Some text with 3   spaces in between.</fbt>');
        $this->assertPayloads('jsxPreserveWhitespaceBug');
    }

    /**
     * fbtStaticJSModule-test.js:169-260
     */
    public function testPreserveWhitespaceFunctional()
    {
        $cases = [
            [["two\nlines", 'one line', true], '{"t":{"desc":"one line","text":"two\nlines"},"m":[]}'],
            [['two  spaces', 'one space', true], '{"t":{"desc":"one space","text":"two  spaces"},"m":[]}'],
            [['one line', "two\nlines", true], '{"t":{"desc":"two\nlines","text":"one line"},"m":[]}'],
            [['one space', 'two  spaces', true], '{"t":{"desc":"two  spaces","text":"one space"},"m":[]}'],
            [['two  spaces', 'one space', false], '{"t":{"desc":"one space","text":"two spaces"},"m":[]}'],
        ];

        foreach ($cases as [[$text, $desc, $preserveWhitespace], $expected]) {
            FbtTransform::$phrases = [];
            (string)fbt($text, $desc, ['preserveWhitespace' => $preserveWhitespace]);
            $this->assertCount(1, FbtTransform::$phrases);
            $this->assertSame(
                json_encode(json_decode($expected)),
                json_encode(json_decode(self::jsfbtJson(FbtTransform::$phrases[0]['jsfbt']))),
                json_encode([$text, $desc, $preserveWhitespace])
            );
        }
    }

    public function testSubject()
    {
        $this->assertSame('Foo', (string)fbt('Foo', 'Bar', ['subject' => 1]));
        $this->assertPayloads('subject');
    }

    /**
     * fbtInnerOuter-test.js (childToParent relationships; <link> replaced by <a>)
     */
    public function testInnerOuterRelationships()
    {
        $cases = [
            'simple level' => [
                '<fbt desc="d"><a href="#">Your friends</a> liked your video</fbt>',
                [1 => 0],
            ],
            'nested level' => [
                '<fbt desc="d"><a href="#">Your friends <b>liked</b></a> your video</fbt>',
                [1 => 0, 2 => 1],
            ],
            'multi-nested level' => [
                '<fbt desc="phrase 0"><div>phrase 1<div>phrase 2</div></div><div>phrase 3<div>phrase 4</div></div></fbt>',
                [1 => 0, 2 => 1, 3 => 0, 4 => 3],
            ],
            'multiple fbt calls' => [
                '<div><fbt desc="phrase 0"><div href="#">phrase 1</div></fbt><fbt desc="phrase 2"><div href="#">phrase 3</div></fbt></div>',
                [1 => 0, 3 => 2],
            ],
        ];

        foreach ($cases as $name => [$html, $expected]) {
            FbtTransform::$phrases = [];
            FbtTransform::$childToParent = [];
            self::transform($html);
            $this->assertSame($expected, FbtTransform::$childToParent, $name);
        }

        FbtTransform::$phrases = [];
        FbtTransform::$childToParent = [];
        self::transform(
            '<fbt desc="phrase 0">' .
            '<fbt:param name="should not be a child"><div href="#">' .
            '<fbt desc="phrase 1">should not be a child</fbt>' .
            '</div></fbt:param>' .
            '<fbt:param name="also should not be a child"><div href="#">' .
            '<fbt desc="phrase 2">also should not be a child <div href="#">a child!</div></fbt>' .
            '</div></fbt:param>' .
            '<div href="#">another child!</div>' .
            '</fbt>'
        );
        $this->assertSame(
            ['phrase 0', 'In the phrase: "{should not be a child}{also should not be a child}{=another child!}"',
                'phrase 1', 'phrase 2', 'In the phrase: "also should not be a child {=a child!}"'],
            array_column(array_column(array_column(FbtTransform::$phrases, 'jsfbt'), 't'), 'desc')
        );
        $this->assertSame([1 => 0, 4 => 3], FbtTransform::$childToParent);
    }

    /**
     * fbtFunctional-test.js:52 (phrases with doNotExtract are transformed, but not collected)
     */
    public function testDoNotExtract()
    {
        $this->assertSame('A doNotExtract string', (string)fbt(
            'A doNotExtract string',
            'should not be extracted',
            ['doNotExtract' => true]
        ));
        $this->assertSame([], FbtTransform::$phrases);
    }

    /**
     * bin/__tests__/__snapshots__/collectFBT-test.js.snap:1137 "should expose the outer token names if needed"
     * (pretty-format sorts the keys, so the key order is not compared)
     */
    public function testOuterTokenName()
    {
        FbtConfig::set('generateOuterTokenName', true);

        try {
            self::transform('<fbt desc="Expose outer token name when script option is given">Hello <i>World</i></fbt>');
        } finally {
            FbtConfig::set('generateOuterTokenName', false);
        }

        $this->assertEquals([
            [
                'desc' => 'Expose outer token name when script option is given',
                'text' => 'Hello {=World}',
                'tokenAliases' => ['=World' => '=m1'],
            ],
            [
                'desc' => 'In the phrase: "Hello {=World}"',
                'outerTokenName' => '=World',
                'text' => 'World',
            ],
        ], array_column(array_column(FbtTransform::$phrases, 'jsfbt'), 't'));
        $this->assertSame([[], []], array_column(array_column(FbtTransform::$phrases, 'jsfbt'), 'm'));
        $this->assertSame([1 => 0], FbtTransform::$childToParent);
    }

    /**
     * fbtFunctional-test.js:1800
     */
    public function testBadShowCountValue()
    {
        $message = null;

        try {
            (string)fbt('There were ' . fbt::plural('a like', 2, ['showCount' => 'badkey']), 'plurals');
        } catch (\Throwable $e) {
            $message = $e->getMessage();
        }

        $this->assertNotNull($message);
        $this->assertStringContainsString(
            'Option "showCount" has an invalid value: "badkey". Only allowed: yes, no, ifMany',
            $message
        );
    }

    // ---------------------------------------------------------------------------------------------
    // babel-plugin-fbt-runtime/__tests__/fbtRuntime-test.js (asserted runtime tables and hk)
    // ---------------------------------------------------------------------------------------------

    public function testRuntimeSimpleString()
    {
        (string)fbt('Foo', 'Bar');
        $this->assertRuntime(0, 'Foo', '3ktBJ2');
    }

    public function testRuntimeNestedFbtsAndTwoLineParamName()
    {
        $this->assertSame('<b>simple</b> test', self::transform(
            "<fbt desc=\"d\"><fbt:param name=\"two\nlines\"><b><fbt desc=\"test\">simple</fbt></b></fbt:param> test</fbt>"
        ));
        $this->assertPayloads('twoLineParamName', false);

        $hks = [];
        foreach (FbtTransform::$phrases as $phrase) {
            $runtime = FbtRuntimeTransform::transform($phrase);
            $hks[$runtime['table']] = $runtime['options']['hk'];
        }
        ksort($hks);
        $this->assertSame(['simple' => '2pjKFw', '{two lines} test' => '2xRGl8'], $hks);
    }

    public function testRuntimeEnum()
    {
        (string)fbt('Foo ' . fbt::enum('a', ['a' => 'A', 'b' => 'B', 'c' => 'C']), 'Bar');
        $this->assertRuntime(0, ['a' => 'Foo A', 'b' => 'Foo B', 'c' => 'Foo C'], 'NT3sR');
    }

    public function testRuntimeClearTokensReplacedWithMangledTokens()
    {
        $this->assertSame('<b>Your</b> friends <b>shared</b> a photo', self::transform(
            '<fbt desc="d"><b>Your</b> friends <b>shared</b>' .
            '<fbt:plural many="photos" showCount="ifMany" count="1"> a photo</fbt:plural></fbt>'
        ));
        $this->assertCount(3, FbtTransform::$phrases);
        $this->assertRuntime(0, ['*' => '{=m0} friends {=m2}{number} photos', '_1' => '{=m0} friends {=m2} a photo'], '2mDoBt');
        $this->assertRuntime(1, ['*' => 'Your', '_1' => 'Your'], '3AIVHA');
        $this->assertRuntime(2, ['*' => 'shared', '_1' => 'shared'], '3CHy8o');
    }

    public function testRuntimeNestedStrings()
    {
        $this->assertSame('I wrote <b>2 inner strings</b>', self::transform(
            '<fbt desc="d">I wrote <b>' .
            '<fbt:plural many="inner strings" count="2" showCount="ifMany">an inner string</fbt:plural>' .
            '</b></fbt>'
        ));
        $this->assertCount(2, FbtTransform::$phrases);
        $this->assertRuntime(0, ['*' => 'I wrote {=m1}', '_1' => 'I wrote {=m1}'], 'fglLv');
        $this->assertRuntime(1, ['*' => '{number} inner strings', '_1' => 'an inner string'], '2HM8na');
    }

    // ---------------------------------------------------------------------------------------------
    // Errors
    // ---------------------------------------------------------------------------------------------

    /**
     * @dataProvider errorProvider
     */
    public function testErrors(callable $input, string $expectedMessage)
    {
        $this->expectExceptionMessage($expectedMessage);

        $input();
    }

    public function errorProvider(): array
    {
        $collision = 'There\'s already a token called "=world" in this fbt call';
        $sameParam = 'Expected fbt `sameParam` construct with name=`%s` to refer to a `name` or `param` construct using the same token name';

        return [
            // fbtFunctional-test.js:179
            'two arguments with the same names' => [function () {
                return (string)fbt('a ' . fbt::param('name', 'v1') . fbt::param('name', 'v2') . ' b', 'desc');
            }, 'There\'s already a token called "name" in this fbt call'],
            // fbtFunctional-test.js:213
            'param nested inside param' => [function () {
                return self::transform('<fbt desc="desc">a <fbt:param name="name"><fbt:param name="name2">v</fbt:param></fbt:param> b</fbt>');
            }, 'Expected fbt constructs to not nest inside fbt constructs, but found fbt.param nest inside fbt.param'],
            // fbtFunctional-test.js:238
            'param nested inside name' => [function () {
                return self::transform('<fbt desc="desc">a <fbt:name name="name" gender="1"><fbt:param name="paramName">v</fbt:param></fbt:name> b</fbt>');
            }, 'Expected fbt constructs to not nest inside fbt constructs, but found fbt.param nest inside fbt.name'],
            // fbtFunctional-test.js "should throw when multiple tokens have the same names due to implicit params"
            'implicit params with the same token names' => [function () {
                return (string)fbt(['Hello ', '<a>world</a>', ' ', '<a>world</a>'], 'token name collision due to autoparam');
            }, $collision],
            'implicit param and enum with the same token names' => [function () {
                return (string)fbt(
                    ['Hello ', '<a>world</a>', ' ', '<a>' . fbt::enum('world', ['world']) . '</a>'],
                    'token name collision due to autoparam'
                );
            }, $collision],
            'implicit param and param with the same token names' => [function () {
                return (string)fbt(['Hello ', '<a>world</a>', ' ', fbt::param('=world', 'v')], 'token name collision due to autoparam');
            }, $collision],
            'implicit param and plural with the same token names' => [function () {
                return (string)fbt(
                    ['Hello ', '<a>world</a>', ' ', '<b>' . fbt::plural('world', 1) . '</b>'],
                    'token name collision due to autoparam'
                );
            }, $collision],
            // fbtFunctional-test.js:1820 (the PHP list of allowed options also has `key`)
            'unknown plural option' => [function () {
                return (string)fbt('There were ' . fbt::plural('a like', 2, ['whatisthis' => 'huh?']), 'plurals');
            }, 'Invalid option "whatisthis". Only allowed: value, showCount, name, many'],
            // fbtFunctional-test.js:1921
            'sameParam with an undefined token name' => [function () {
                return (string)fbt(fbt::param('name1', 'v') . ' and ' . fbt::sameParam('name2'), 'd');
            }, sprintf($sameParam, 'name2')],
            // fbtFunctional-test.js "should throw if the token name of a sameParam construct in a nested string is not defined"
            'sameParam with an undefined token name in a nested string' => [function () {
                return (string)fbt([fbt::param('name', 'v'), ' and ', '<b>inner string ' . fbt::sameParam('name1') . '</b>'], 'd');
            }, sprintf($sameParam, 'name1')],
            // fbtFunctional-test.js:1964
            'sameParam referring to a plural' => [function () {
                return (string)fbt(
                    fbt::plural('cat', 2, ['value' => 'x', 'name' => 'tokenName', 'showCount' => 'yes']) . ' and ' . fbt::sameParam('tokenName'),
                    'd'
                );
            }, sprintf($sameParam, 'tokenName')],
            // fbtAutoWrap-test.js "prevent token name collisions among fbt constructs across all nesting levels (v1)"
            'token name collision across nesting levels (v1)' => [function () {
                return self::transform(
                    '<fbt desc="some-desc">Level 1 <fbt:param name="foo">foo</fbt:param>' .
                    '<a>Level 2 <fbt:name name="foo" gender="1">foo</fbt:name></a></fbt>'
                );
            }, 'There\'s already a token called `foo` in this fbt call.'],
            // fbtAutoWrap-test.js "... (v2)"
            'token name collision across nesting levels (v2)' => [function () {
                return self::transform(
                    '<fbt desc="some-desc">Level 1 <fbt:param name="bar">bar</fbt:param>' .
                    '<a>Level 2 <fbt:name name="foo" gender="1">foo</fbt:name>' .
                    '<b>Level 3 <fbt:plural name="foo" count="2" showCount="yes">cat</fbt:plural></b></a></fbt>'
                );
            }, 'There\'s already a token called `foo` in this fbt call.'],
        ];
    }

    /**
     * Expected jsfbt payloads, extracted from the upstream tests (JSON.stringify output).
     */
    private const EXPECTED = [
        // fbtFunctional-test.js:23
        'simple' => [
            '{"t":{"desc":"It\'s simple","text":"A simple string"},"m":[]}',
        ],
        // fbtFunctional-test.js:133
        'newlinesWithinArguments' => [
            '{"t":{"desc":"a","text":"a b {name1} c d {name2} e"},"m":[]}',
        ],
        // fbtFunctional-test.js:1407
        'params' => [
            '{"t":{"desc":"Moar params","text":"A parameterized message to {personName}"},"m":[]}',
        ],
        // fbtFunctional-test.js:1442
        'options' => [
            '{"t":{"desc":"options!","text":"A string that moved files"},"m":[]}',
        ],
        // fbtFunctional-test.js:1473
        'enumArray' => [
            '{"t":{"groups":{"desc":"enum as an array","text":"Click to see groups"},"photos":{"desc":"enum as an array","text":"Click to see photos"},"videos":{"desc":"enum as an array","text":"Click to see videos"}},"m":[null]}',
        ],
        // fbtFunctional-test.js:1523
        'enumObject' => [
            '{"t":{"id1":{"desc":"enum as an object","text":"Click to see groups"},"id2":{"desc":"enum as an object","text":"Click to see photos"},"id3":{"desc":"enum as an object","text":"Click to see videos"}},"m":[null]}',
        ],
        // fbtFunctional-test.js:2170
        'enumMoreText' => [
            '{"t":{"groups":{"desc":"enums!","text":"Hello, groups!"},"photos":{"desc":"enums!","text":"Hello, photos!"},"videos":{"desc":"enums!","text":"Hello, videos!"}},"m":[null]}',
        ],
        // fbtFunctional-test.js:2220
        'enumDuplicate' => [
            '{"t":{"groups":{"desc":"enums!","text":"Look! Groups and groups!"},"photos":{"desc":"enums!","text":"Look! Photos and photos!"},"videos":{"desc":"enums!","text":"Look! Videos and videos!"}},"m":[null]}',
        ],
        // fbtFunctional-test.js:1623
        'pluralDifferentCounts' => [
            '{"t":{"*":{"*":{"desc":"plurals","text":"{cat_token} cats and {dog_token} dogs"},"_1":{"desc":"plurals","text":"{cat_token} cats and 1 dog"}},"_1":{"*":{"desc":"plurals","text":"1 cat and {dog_token} dogs"},"_1":{"desc":"plurals","text":"1 cat and 1 dog"}}},"m":[{"token":"cat_token","type":2,"singular":true},{"token":"dog_token","type":2,"singular":true}]}',
        ],
        // fbtFunctional-test.js:1693
        'pluralSameCount' => [
            '{"t":{"*":{"*":{"desc":"plurals","text":"There were {number} likes"}},"_1":{"_1":{"desc":"plurals","text":"There was a like"}}},"m":[null,{"token":"number","type":2,"singular":true}]}',
        ],
        // fbtFunctional-test.js:1747
        'pluralNoShowCount' => [
            '{"t":{"*":{"*":{"desc":"plurals","text":"There are {number} likes"}},"_1":{"_1":{"desc":"plurals","text":"There is a like"}}},"m":[null,{"token":"number","type":2,"singular":true}]}',
        ],
        // fbtFunctional-test.js:1840
        'names' => [
            '{"t":{"*":{"desc":"names","text":"You just friended {name}"}},"m":[{"token":"name","type":1}]}',
        ],
        // fbtFunctional-test.js:1880
        'numberTrue' => [
            '{"t":{"*":{"desc":"variations!","text":"Click to see {count} links"}},"m":[{"token":"count","type":2}]}',
        ],
        // fbtFunctional-test.js:1987
        'sameParam' => [
            '{"t":{"desc":"d","text":"{name1} and {name1}"},"m":[]}',
        ],
        // fbtFunctional-test.js:2021
        'numberSameParam' => [
            '{"t":{"*":{"desc":"test variations + sameParam","text":"You have {count} likes. Comment on it to get more than {count} likes"}},"m":[{"token":"count","type":2}]}',
        ],
        // fbtFunctional-test.js:2293
        'pronounObject' => [
            '{"t":{"0":{"desc":"object pronoun","text":"I know this."},"1":{"desc":"object pronoun","text":"I know her."},"2":{"desc":"object pronoun","text":"I know him."},"*":{"desc":"object pronoun","text":"I know them."}},"m":[null]}',
        ],
        // fbtFunctional-test.js:2344
        'pronounSubjectReflexive' => [
            '{"t":{"1":{"1":{"desc":"subject+reflexive pronouns","text":"She wished herself a happy birthday."}},"2":{"2":{"desc":"subject+reflexive pronouns","text":"He wished himself a happy birthday."}},"*":{"*":{"desc":"subject+reflexive pronouns","text":"They wished themselves a happy birthday."}}},"m":[null,null]}',
        ],
        // fbtFunctional-test.js:2405
        'pronounPossessive' => [
            '{"t":{"1":{"desc":"possessive pronoun","text":"It is her birthday."},"2":{"desc":"possessive pronoun","text":"It is his birthday."},"*":{"desc":"possessive pronoun","text":"It is their birthday."}},"m":[null]}',
        ],
        // fbtFunctional-test.js:263
        'fbtInsideName' => [
            '{"t":{"*":{"desc":"desc","text":"a {name} b"}},"m":[{"token":"name","type":1}]}',
            '{"t":{"desc":"desc inner","text":"{paramName}"},"m":[]}',
        ],
        // fbtFunctional-test.js:438
        'jsxFragmentWithParam' => [
            '{"t":{"*":{"desc":"string with nested JSX fragments","text":"A1 {=B1 C1 [paramName] C2 B2} A2","tokenAliases":{"=B1 C1 [paramName] C2 B2":"=m1"}}},"m":[{"token":"__subject__","type":1}]}',
            '{"t":{"*":{"desc":"In the phrase: \\"A1 {=B1 C1 [paramName] C2 B2} A2\\"","text":"B1 {=C1 [paramName] C2} B2","tokenAliases":{"=C1 [paramName] C2":"=m1"}}},"m":[{"token":"__subject__","type":1}]}',
            '{"t":{"*":{"desc":"In the phrase: \\"A1 B1 {=C1 [paramName] C2} B2 A2\\"","text":"C1 {paramName} C2"}},"m":[{"token":"__subject__","type":1}]}',
        ],
        // fbtFunctional-test.js:711
        'twoNestedElements' => [
            '{"t":{"*":{"*":{"desc":"example 1","text":"{name} has shared {=[number] photos} with you","tokenAliases":{"=[number] photos":"=m2"}},"_1":{"desc":"example 1","text":"{name} has shared {=a photo} with you","tokenAliases":{"=a photo":"=m2"}}}},"m":[{"token":"name","type":1},{"token":"number","type":2,"singular":true}]}',
            '{"t":{"*":{"*":{"desc":"In the phrase: \\"{name} has shared {=[number] photos} with you\\"","text":"{=[number] photos}","tokenAliases":{"=[number] photos":"=m1"}},"_1":{"desc":"In the phrase: \\"{name} has shared {=a photo} with you\\"","text":"{=a photo}","tokenAliases":{"=a photo":"=m1"}}}},"m":[{"token":"name","type":1},{"token":"number","type":2,"singular":true}]}',
            '{"t":{"*":{"*":{"desc":"In the phrase: \\"{name} has shared {=[number] photos} with you\\"","text":"{number} photos"},"_1":{"desc":"In the phrase: \\"{name} has shared {=a photo} with you\\"","text":"a photo"}}},"m":[{"token":"name","type":1},{"token":"number","type":2,"singular":true}]}',
        ],
        // fbtFunctional-test.js:875
        'multipleLevels' => [
            '{"t":{"today":{"*":{"*":{"1":{"desc":"example 1","text":"{=today}, {name} has shared {=[number] photos with her}","tokenAliases":{"=today":"=m0","=[number] photos with her":"=m4"}},"2":{"desc":"example 1","text":"{=today}, {name} has shared {=[number] photos with him}","tokenAliases":{"=today":"=m0","=[number] photos with him":"=m4"}},"*":{"desc":"example 1","text":"{=today}, {name} has shared {=[number] photos with them}","tokenAliases":{"=today":"=m0","=[number] photos with them":"=m4"}}},"_1":{"1":{"desc":"example 1","text":"{=today}, {name} has shared {=a photo with her}","tokenAliases":{"=today":"=m0","=a photo with her":"=m4"}},"2":{"desc":"example 1","text":"{=today}, {name} has shared {=a photo with him}","tokenAliases":{"=today":"=m0","=a photo with him":"=m4"}},"*":{"desc":"example 1","text":"{=today}, {name} has shared {=a photo with them}","tokenAliases":{"=today":"=m0","=a photo with them":"=m4"}}}}},"yesterday":{"*":{"*":{"1":{"desc":"example 1","text":"{=yesterday}, {name} has shared {=[number] photos with her}","tokenAliases":{"=yesterday":"=m0","=[number] photos with her":"=m4"}},"2":{"desc":"example 1","text":"{=yesterday}, {name} has shared {=[number] photos with him}","tokenAliases":{"=yesterday":"=m0","=[number] photos with him":"=m4"}},"*":{"desc":"example 1","text":"{=yesterday}, {name} has shared {=[number] photos with them}","tokenAliases":{"=yesterday":"=m0","=[number] photos with them":"=m4"}}},"_1":{"1":{"desc":"example 1","text":"{=yesterday}, {name} has shared {=a photo with her}","tokenAliases":{"=yesterday":"=m0","=a photo with her":"=m4"}},"2":{"desc":"example 1","text":"{=yesterday}, {name} has shared {=a photo with him}","tokenAliases":{"=yesterday":"=m0","=a photo with him":"=m4"}},"*":{"desc":"example 1","text":"{=yesterday}, {name} has shared {=a photo with them}","tokenAliases":{"=yesterday":"=m0","=a photo with them":"=m4"}}}}}},"m":[null,{"token":"name","type":1},{"token":"number","type":2,"singular":true},null]}',
            '{"t":{"today":{"*":{"*":{"1":{"desc":"In the phrase: \\"{=today}, {name} has shared {=[number] photos with her}\\"","text":"today"},"2":{"desc":"In the phrase: \\"{=today}, {name} has shared {=[number] photos with him}\\"","text":"today"},"*":{"desc":"In the phrase: \\"{=today}, {name} has shared {=[number] photos with them}\\"","text":"today"}},"_1":{"1":{"desc":"In the phrase: \\"{=today}, {name} has shared {=a photo with her}\\"","text":"today"},"2":{"desc":"In the phrase: \\"{=today}, {name} has shared {=a photo with him}\\"","text":"today"},"*":{"desc":"In the phrase: \\"{=today}, {name} has shared {=a photo with them}\\"","text":"today"}}}},"yesterday":{"*":{"*":{"1":{"desc":"In the phrase: \\"{=yesterday}, {name} has shared {=[number] photos with her}\\"","text":"yesterday"},"2":{"desc":"In the phrase: \\"{=yesterday}, {name} has shared {=[number] photos with him}\\"","text":"yesterday"},"*":{"desc":"In the phrase: \\"{=yesterday}, {name} has shared {=[number] photos with them}\\"","text":"yesterday"}},"_1":{"1":{"desc":"In the phrase: \\"{=yesterday}, {name} has shared {=a photo with her}\\"","text":"yesterday"},"2":{"desc":"In the phrase: \\"{=yesterday}, {name} has shared {=a photo with him}\\"","text":"yesterday"},"*":{"desc":"In the phrase: \\"{=yesterday}, {name} has shared {=a photo with them}\\"","text":"yesterday"}}}}},"m":[null,{"token":"name","type":1},{"token":"number","type":2,"singular":true},null]}',
            '{"t":{"today":{"*":{"*":{"1":{"desc":"In the phrase: \\"{=today}, {name} has shared {=[number] photos with her}\\"","text":"{number} photos with {=her}","tokenAliases":{"=her":"=m4"}},"2":{"desc":"In the phrase: \\"{=today}, {name} has shared {=[number] photos with him}\\"","text":"{number} photos with {=him}","tokenAliases":{"=him":"=m4"}},"*":{"desc":"In the phrase: \\"{=today}, {name} has shared {=[number] photos with them}\\"","text":"{number} photos with {=them}","tokenAliases":{"=them":"=m4"}}},"_1":{"1":{"desc":"In the phrase: \\"{=today}, {name} has shared {=a photo with her}\\"","text":"a photo with {=her}","tokenAliases":{"=her":"=m4"}},"2":{"desc":"In the phrase: \\"{=today}, {name} has shared {=a photo with him}\\"","text":"a photo with {=him}","tokenAliases":{"=him":"=m4"}},"*":{"desc":"In the phrase: \\"{=today}, {name} has shared {=a photo with them}\\"","text":"a photo with {=them}","tokenAliases":{"=them":"=m4"}}}}},"yesterday":{"*":{"*":{"1":{"desc":"In the phrase: \\"{=yesterday}, {name} has shared {=[number] photos with her}\\"","text":"{number} photos with {=her}","tokenAliases":{"=her":"=m4"}},"2":{"desc":"In the phrase: \\"{=yesterday}, {name} has shared {=[number] photos with him}\\"","text":"{number} photos with {=him}","tokenAliases":{"=him":"=m4"}},"*":{"desc":"In the phrase: \\"{=yesterday}, {name} has shared {=[number] photos with them}\\"","text":"{number} photos with {=them}","tokenAliases":{"=them":"=m4"}}},"_1":{"1":{"desc":"In the phrase: \\"{=yesterday}, {name} has shared {=a photo with her}\\"","text":"a photo with {=her}","tokenAliases":{"=her":"=m4"}},"2":{"desc":"In the phrase: \\"{=yesterday}, {name} has shared {=a photo with him}\\"","text":"a photo with {=him}","tokenAliases":{"=him":"=m4"}},"*":{"desc":"In the phrase: \\"{=yesterday}, {name} has shared {=a photo with them}\\"","text":"a photo with {=them}","tokenAliases":{"=them":"=m4"}}}}}},"m":[null,{"token":"name","type":1},{"token":"number","type":2,"singular":true},null]}',
            '{"t":{"today":{"*":{"*":{"1":{"desc":"In the phrase: \\"{=today}, {name} has shared {number} photos with {=her}\\"","text":"her"},"2":{"desc":"In the phrase: \\"{=today}, {name} has shared {number} photos with {=him}\\"","text":"him"},"*":{"desc":"In the phrase: \\"{=today}, {name} has shared {number} photos with {=them}\\"","text":"them"}},"_1":{"1":{"desc":"In the phrase: \\"{=today}, {name} has shared a photo with {=her}\\"","text":"her"},"2":{"desc":"In the phrase: \\"{=today}, {name} has shared a photo with {=him}\\"","text":"him"},"*":{"desc":"In the phrase: \\"{=today}, {name} has shared a photo with {=them}\\"","text":"them"}}}},"yesterday":{"*":{"*":{"1":{"desc":"In the phrase: \\"{=yesterday}, {name} has shared {number} photos with {=her}\\"","text":"her"},"2":{"desc":"In the phrase: \\"{=yesterday}, {name} has shared {number} photos with {=him}\\"","text":"him"},"*":{"desc":"In the phrase: \\"{=yesterday}, {name} has shared {number} photos with {=them}\\"","text":"them"}},"_1":{"1":{"desc":"In the phrase: \\"{=yesterday}, {name} has shared a photo with {=her}\\"","text":"her"},"2":{"desc":"In the phrase: \\"{=yesterday}, {name} has shared a photo with {=him}\\"","text":"him"},"*":{"desc":"In the phrase: \\"{=yesterday}, {name} has shared a photo with {=them}\\"","text":"them"}}}}},"m":[null,{"token":"name","type":1},{"token":"number","type":2,"singular":true},null]}',
        ],
        // fbtFunctional-test.js:2729
        'nestedFragmentSvArgs' => [
            '{"t":{"*":{"*":{"desc":"string with nested JSX fragments","text":"A1 {=B1 C1 [count] C2 [cat_token] cats B2} A2","tokenAliases":{"=B1 C1 [count] C2 [cat_token] cats B2":"=m1"}},"_1":{"desc":"string with nested JSX fragments","text":"A1 {=B1 C1 [count] C2 cat B2} A2","tokenAliases":{"=B1 C1 [count] C2 cat B2":"=m1"}}}},"m":[{"token":"count","type":2},{"token":"cat_token","type":2,"singular":true}]}',
            '{"t":{"*":{"*":{"desc":"In the phrase: \\"A1 {=B1 C1 [count] C2 [cat_token] cats B2} A2\\"","text":"B1 {=C1 [count] C2 [cat_token] cats} B2","tokenAliases":{"=C1 [count] C2 [cat_token] cats":"=m1"}},"_1":{"desc":"In the phrase: \\"A1 {=B1 C1 [count] C2 cat B2} A2\\"","text":"B1 {=C1 [count] C2 cat} B2","tokenAliases":{"=C1 [count] C2 cat":"=m1"}}}},"m":[{"token":"count","type":2},{"token":"cat_token","type":2,"singular":true}]}',
            '{"t":{"*":{"*":{"desc":"In the phrase: \\"A1 B1 {=C1 [count] C2 [cat_token] cats} B2 A2\\"","text":"C1 {count} C2 {cat_token} cats"},"_1":{"desc":"In the phrase: \\"A1 B1 {=C1 [count] C2 cat} B2 A2\\"","text":"C1 {count} C2 cat"}}},"m":[{"token":"count","type":2},{"token":"cat_token","type":2,"singular":true}]}',
        ],
        // __snapshots__/fbtAutoWrap-test.js.snap "Test jsx auto-wrapping of implicit parameters should auto wrap a simple test with one level"
        'autoOneLevel' => [
            '{"t":{"desc":"d","text":"{=Your friends} liked your video","tokenAliases":{"=Your friends":"=m0"}},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"{=Your friends} liked your video\\"","text":"Your friends"},"m":[]}',
        ],
        // __snapshots__/fbtAutoWrap-test.js.snap "Test jsx auto-wrapping of implicit parameters should auto wrap a simple test with a nested level"
        'autoNestedLevel' => [
            '{"t":{"desc":"d","text":"{=Your friends liked} your video","tokenAliases":{"=Your friends liked":"=m0"}},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"{=Your friends liked} your video\\"","text":"Your friends {=liked}","tokenAliases":{"=liked":"=m1"}},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"Your friends {=liked} your video\\"","text":"liked"},"m":[]}',
        ],
        // __snapshots__/fbtAutoWrap-test.js.snap "Test jsx auto-wrapping of implicit parameters should wrap two unwrapped <fbt> children"
        'autoTwoChildren' => [
            '{"t":{"desc":"d","text":"{=wrap once}{=wrap twice}","tokenAliases":{"=wrap once":"=m0","=wrap twice":"=m1"}},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"{=wrap once}{=wrap twice}\\"","text":"wrap once"},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"{=wrap once}{=wrap twice}\\"","text":"wrap twice"},"m":[]}',
        ],
        // __snapshots__/fbtAutoWrap-test.js.snap "Test jsx auto-wrapping of implicit parameters should wrap two unwrapped <fbt> children and 1 nested"
        'autoTwoChildrenOneNested' => [
            '{"t":{"desc":"d","text":"{=wrap once and also}{=wrap twice} complicated","tokenAliases":{"=wrap once and also":"=m0","=wrap twice":"=m1"}},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"{=wrap once and also}{=wrap twice} complicated\\"","text":"wrap once {=and also}","tokenAliases":{"=and also":"=m1"}},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"wrap once {=and also}{=wrap twice} complicated\\"","text":"and also"},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"{=wrap once and also}{=wrap twice} complicated\\"","text":"wrap twice"},"m":[]}',
        ],
        // __snapshots__/fbtAutoWrap-test.js.snap "Test jsx auto-wrapping of implicit parameters should wrap two children with one nested level"
        'autoTwoChildrenOneNestedLevel' => [
            '{"t":{"desc":"d","text":"{=this is a doubly} nested test {=with an additional level}","tokenAliases":{"=this is a doubly":"=m0","=with an additional level":"=m2"}},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"{=this is a doubly} nested test {=with an additional level}\\"","text":"{=this is} a doubly","tokenAliases":{"=this is":"=m1"}},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"{=this is} a doubly nested test {=with an additional level}\\"","text":"this is"},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"{=this is a doubly} nested test {=with an additional level}\\"","text":"with an additional level"},"m":[]}',
        ],
        // __snapshots__/fbtAutoWrap-test.js.snap "Test jsx auto-wrapping of implicit parameters should wrap two nested next to each other with an extra level"
        'autoExtraLevel' => [
            '{"t":{"desc":"d","text":"{=one two [=test]}{=three four}","tokenAliases":{"=one two [=test]":"=m0","=three four":"=m1"}},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"{=one two [=test]}{=three four}\\"","text":"one {=two test}","tokenAliases":{"=two test":"=m1"}},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"one {=two test}{=three four}\\"","text":"two {=test}","tokenAliases":{"=test":"=m1"}},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"one two {=test}{=three four}\\"","text":"test"},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"{=one two [=test]}{=three four}\\"","text":"three {=four}","tokenAliases":{"=four":"=m1"}},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"{=one two [=test]}three {=four}\\"","text":"four"},"m":[]}',
        ],
        // __snapshots__/fbtAutoWrap-test.js.snap "Test jsx auto-wrapping of implicit parameters should wrap a <fbt> child nested in an explicit <fbt:param>"
        'autoFbtInParam' => [
            '{"t":{"desc":"d","text":"{explicit fbt param}"},"m":[]}',
            '{"t":{"desc":"d2","text":"explicit fbt param {=with a nested implicit param}","tokenAliases":{"=with a nested implicit param":"=m1"}},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"explicit fbt param {=with a nested implicit param}\\"","text":"with a nested implicit param"},"m":[]}',
        ],
        // __snapshots__/fbtAutoWrap-test.js.snap "Test jsx auto-wrapping of implicit parameters should wrap a <fbt> child next to an explicit <fbt:param>"
        'autoFbtNextToParam' => [
            '{"t":{"desc":"d","text":"{explicit param next to}{=an implicit param}","tokenAliases":{"=an implicit param":"=m1"}},"m":[]}',
            '{"t":{"desc":"d2","text":"explicit param next to"},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"{explicit param next to}{=an implicit param}\\"","text":"an implicit param"},"m":[]}',
        ],
        // __snapshots__/fbtAutoWrap-test.js.snap "Test jsx auto-wrapping of implicit parameters should wrap explicit params nested in implicit params with []"
        'autoExplicitInImplicit' => [
            '{"t":{"desc":"d","text":"{=this is a test [to make sure that explicit params under an implicit node][and ones that are next to each other] under an implicit tag are wrapped with [ ]}{but free standing ones are not}","tokenAliases":{"=this is a test [to make sure that explicit params under an implicit node][and ones that are next to each other] under an implicit tag are wrapped with [ ]":"=m0"}},"m":[]}',
            '{"t":{"desc":"d3","text":"but free standing ones {=are not}","tokenAliases":{"=are not":"=m1"}},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"but free standing ones {=are not}\\"","text":"are not"},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"{=this is a test [to make sure that explicit params under an implicit node][and ones that are next to each other] under an implicit tag are wrapped with [ ]}{but free standing ones are not}\\"","text":"this is a test {to make sure that explicit params under an implicit node}{and ones that are next to each other} under an implicit tag are wrapped with [ ]"},"m":[]}',
            '{"t":{"desc":"d2","text":"to make sure that explicit tags {=under an implicit node}","tokenAliases":{"=under an implicit node":"=m1"}},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"to make sure that explicit tags {=under an implicit node}\\"","text":"under an implicit node"},"m":[]}',
            '{"t":{"desc":"d3","text":"and ones that are next {=to each other}","tokenAliases":{"=to each other":"=m1"}},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"and ones that are next {=to each other}\\"","text":"to each other"},"m":[]}',
        ],
        // __snapshots__/fbtAutoWrap-test.js.snap "Test jsx auto-wrapping of implicit parameters should work with multiple <fbt> calls in one file"
        'autoMultipleFbts' => [
            '{"t":{"desc":"one","text":"{=first} fbt call","tokenAliases":{"=first":"=m0"}},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"{=first} fbt call\\"","text":"first"},"m":[]}',
            '{"t":{"desc":"two","text":"{=second} test","tokenAliases":{"=second":"=m0"}},"m":[]}',
            '{"t":{"desc":"In the phrase: \\"{=second} test\\"","text":"second"},"m":[]}',
        ],
        // __snapshots__/fbtAutoWrap-test.js.snap "Test jsx auto-wrapping of implicit parameters can handle multiple variations in nested strings"
        'autoVariations' => [
            '{"t":{"*":{"*":{"desc":"some-desc","text":"Level 1 {foo}{=Level 2 [bar]Level 3 [baz] cats}","tokenAliases":{"=Level 2 [bar]Level 3 [baz] cats":"=m2"}},"_1":{"desc":"some-desc","text":"Level 1 {foo}{=Level 2 [bar]Level 3 1 cat}","tokenAliases":{"=Level 2 [bar]Level 3 1 cat":"=m2"}}}},"m":[{"token":"bar","type":1},{"token":"baz","type":2,"singular":true}]}',
            '{"t":{"*":{"*":{"desc":"In the phrase: \\"Level 1 {foo}{=Level 2 [bar]Level 3 [baz] cats}\\"","text":"Level 2 {bar}{=Level 3 [baz] cats}","tokenAliases":{"=Level 3 [baz] cats":"=m2"}},"_1":{"desc":"In the phrase: \\"Level 1 {foo}{=Level 2 [bar]Level 3 1 cat}\\"","text":"Level 2 {bar}{=Level 3 1 cat}","tokenAliases":{"=Level 3 1 cat":"=m2"}}}},"m":[{"token":"bar","type":1},{"token":"baz","type":2,"singular":true}]}',
            '{"t":{"*":{"*":{"desc":"In the phrase: \\"Level 1 {foo}Level 2 {bar}{=Level 3 [baz] cats}\\"","text":"Level 3 {baz} cats"},"_1":{"desc":"In the phrase: \\"Level 1 {foo}Level 2 {bar}{=Level 3 1 cat}\\"","text":"Level 3 1 cat"}}},"m":[{"token":"bar","type":1},{"token":"baz","type":2,"singular":true}]}',
        ],
        // __snapshots__/fbtAutoWrap-test.js.snap "Test jsx auto-wrapping of implicit parameters can handle multiple variations in nested strings and a subject gender"
        'autoVariationsSubject' => [
            '{"t":{"*":{"1":{"*":{"desc":"description","text":"Level 1 {=her some_pronoun}{=Level 2 [foo] cats}","tokenAliases":{"=her some_pronoun":"=m1","=Level 2 [foo] cats":"=m2"}},"_1":{"desc":"description","text":"Level 1 {=her some_pronoun}{=Level 2 1 cat}","tokenAliases":{"=her some_pronoun":"=m1","=Level 2 1 cat":"=m2"}}},"2":{"*":{"desc":"description","text":"Level 1 {=his some_pronoun}{=Level 2 [foo] cats}","tokenAliases":{"=his some_pronoun":"=m1","=Level 2 [foo] cats":"=m2"}},"_1":{"desc":"description","text":"Level 1 {=his some_pronoun}{=Level 2 1 cat}","tokenAliases":{"=his some_pronoun":"=m1","=Level 2 1 cat":"=m2"}}},"*":{"*":{"desc":"description","text":"Level 1 {=their some_pronoun}{=Level 2 [foo] cats}","tokenAliases":{"=their some_pronoun":"=m1","=Level 2 [foo] cats":"=m2"}},"_1":{"desc":"description","text":"Level 1 {=their some_pronoun}{=Level 2 1 cat}","tokenAliases":{"=their some_pronoun":"=m1","=Level 2 1 cat":"=m2"}}}}},"m":[{"token":"__subject__","type":1},null,{"token":"foo","type":2,"singular":true}]}',
            '{"t":{"*":{"1":{"*":{"desc":"In the phrase: \\"Level 1 {=her some_pronoun}{=Level 2 [foo] cats}\\"","text":"her some_pronoun"},"_1":{"desc":"In the phrase: \\"Level 1 {=her some_pronoun}{=Level 2 1 cat}\\"","text":"her some_pronoun"}},"2":{"*":{"desc":"In the phrase: \\"Level 1 {=his some_pronoun}{=Level 2 [foo] cats}\\"","text":"his some_pronoun"},"_1":{"desc":"In the phrase: \\"Level 1 {=his some_pronoun}{=Level 2 1 cat}\\"","text":"his some_pronoun"}},"*":{"*":{"desc":"In the phrase: \\"Level 1 {=their some_pronoun}{=Level 2 [foo] cats}\\"","text":"their some_pronoun"},"_1":{"desc":"In the phrase: \\"Level 1 {=their some_pronoun}{=Level 2 1 cat}\\"","text":"their some_pronoun"}}}},"m":[{"token":"__subject__","type":1},null,{"token":"foo","type":2,"singular":true}]}',
            '{"t":{"*":{"1":{"*":{"desc":"In the phrase: \\"Level 1 {=her some_pronoun}{=Level 2 [foo] cats}\\"","text":"Level 2 {foo} cats"},"_1":{"desc":"In the phrase: \\"Level 1 {=her some_pronoun}{=Level 2 1 cat}\\"","text":"Level 2 1 cat"}},"2":{"*":{"desc":"In the phrase: \\"Level 1 {=his some_pronoun}{=Level 2 [foo] cats}\\"","text":"Level 2 {foo} cats"},"_1":{"desc":"In the phrase: \\"Level 1 {=his some_pronoun}{=Level 2 1 cat}\\"","text":"Level 2 1 cat"}},"*":{"*":{"desc":"In the phrase: \\"Level 1 {=their some_pronoun}{=Level 2 [foo] cats}\\"","text":"Level 2 {foo} cats"},"_1":{"desc":"In the phrase: \\"Level 1 {=their some_pronoun}{=Level 2 1 cat}\\"","text":"Level 2 1 cat"}}}},"m":[{"token":"__subject__","type":1},null,{"token":"foo","type":2,"singular":true}]}',
        ],
        // __snapshots__/fbtAutoWrap-test.js.snap "Test jsx auto-wrapping of implicit parameters can handle multiple variations in nested strings with substrings that look identical"
        'autoIdentical' => [
            '{"t":{"*":{"1":{"1":{"desc":"description","text":"Level 1 {=her some_pronoun}{=Level 2 her some_pronoun}","tokenAliases":{"=her some_pronoun":"=m1","=Level 2 her some_pronoun":"=m2"}},"2":{"desc":"description","text":"Level 1 {=her some_pronoun}{=Level 2 his some_pronoun}","tokenAliases":{"=her some_pronoun":"=m1","=Level 2 his some_pronoun":"=m2"}},"*":{"desc":"description","text":"Level 1 {=her some_pronoun}{=Level 2 their some_pronoun}","tokenAliases":{"=her some_pronoun":"=m1","=Level 2 their some_pronoun":"=m2"}}},"2":{"1":{"desc":"description","text":"Level 1 {=his some_pronoun}{=Level 2 her some_pronoun}","tokenAliases":{"=his some_pronoun":"=m1","=Level 2 her some_pronoun":"=m2"}},"2":{"desc":"description","text":"Level 1 {=his some_pronoun}{=Level 2 his some_pronoun}","tokenAliases":{"=his some_pronoun":"=m1","=Level 2 his some_pronoun":"=m2"}},"*":{"desc":"description","text":"Level 1 {=his some_pronoun}{=Level 2 their some_pronoun}","tokenAliases":{"=his some_pronoun":"=m1","=Level 2 their some_pronoun":"=m2"}}},"*":{"1":{"desc":"description","text":"Level 1 {=their some_pronoun}{=Level 2 her some_pronoun}","tokenAliases":{"=their some_pronoun":"=m1","=Level 2 her some_pronoun":"=m2"}},"2":{"desc":"description","text":"Level 1 {=their some_pronoun}{=Level 2 his some_pronoun}","tokenAliases":{"=their some_pronoun":"=m1","=Level 2 his some_pronoun":"=m2"}},"*":{"desc":"description","text":"Level 1 {=their some_pronoun}{=Level 2 their some_pronoun}","tokenAliases":{"=their some_pronoun":"=m1","=Level 2 their some_pronoun":"=m2"}}}}},"m":[{"token":"__subject__","type":1},null,null]}',
            '{"t":{"*":{"1":{"1":{"desc":"In the phrase: \\"Level 1 {=her some_pronoun}{=Level 2 her some_pronoun}\\"","text":"her some_pronoun"},"2":{"desc":"In the phrase: \\"Level 1 {=her some_pronoun}{=Level 2 his some_pronoun}\\"","text":"her some_pronoun"},"*":{"desc":"In the phrase: \\"Level 1 {=her some_pronoun}{=Level 2 their some_pronoun}\\"","text":"her some_pronoun"}},"2":{"1":{"desc":"In the phrase: \\"Level 1 {=his some_pronoun}{=Level 2 her some_pronoun}\\"","text":"his some_pronoun"},"2":{"desc":"In the phrase: \\"Level 1 {=his some_pronoun}{=Level 2 his some_pronoun}\\"","text":"his some_pronoun"},"*":{"desc":"In the phrase: \\"Level 1 {=his some_pronoun}{=Level 2 their some_pronoun}\\"","text":"his some_pronoun"}},"*":{"1":{"desc":"In the phrase: \\"Level 1 {=their some_pronoun}{=Level 2 her some_pronoun}\\"","text":"their some_pronoun"},"2":{"desc":"In the phrase: \\"Level 1 {=their some_pronoun}{=Level 2 his some_pronoun}\\"","text":"their some_pronoun"},"*":{"desc":"In the phrase: \\"Level 1 {=their some_pronoun}{=Level 2 their some_pronoun}\\"","text":"their some_pronoun"}}}},"m":[{"token":"__subject__","type":1},null,null]}',
            '{"t":{"*":{"1":{"1":{"desc":"In the phrase: \\"Level 1 {=her some_pronoun}{=Level 2 her some_pronoun}\\"","text":"Level 2 {=her some_pronoun}","tokenAliases":{"=her some_pronoun":"=m1"}},"2":{"desc":"In the phrase: \\"Level 1 {=her some_pronoun}{=Level 2 his some_pronoun}\\"","text":"Level 2 {=his some_pronoun}","tokenAliases":{"=his some_pronoun":"=m1"}},"*":{"desc":"In the phrase: \\"Level 1 {=her some_pronoun}{=Level 2 their some_pronoun}\\"","text":"Level 2 {=their some_pronoun}","tokenAliases":{"=their some_pronoun":"=m1"}}},"2":{"1":{"desc":"In the phrase: \\"Level 1 {=his some_pronoun}{=Level 2 her some_pronoun}\\"","text":"Level 2 {=her some_pronoun}","tokenAliases":{"=her some_pronoun":"=m1"}},"2":{"desc":"In the phrase: \\"Level 1 {=his some_pronoun}{=Level 2 his some_pronoun}\\"","text":"Level 2 {=his some_pronoun}","tokenAliases":{"=his some_pronoun":"=m1"}},"*":{"desc":"In the phrase: \\"Level 1 {=his some_pronoun}{=Level 2 their some_pronoun}\\"","text":"Level 2 {=their some_pronoun}","tokenAliases":{"=their some_pronoun":"=m1"}}},"*":{"1":{"desc":"In the phrase: \\"Level 1 {=their some_pronoun}{=Level 2 her some_pronoun}\\"","text":"Level 2 {=her some_pronoun}","tokenAliases":{"=her some_pronoun":"=m1"}},"2":{"desc":"In the phrase: \\"Level 1 {=their some_pronoun}{=Level 2 his some_pronoun}\\"","text":"Level 2 {=his some_pronoun}","tokenAliases":{"=his some_pronoun":"=m1"}},"*":{"desc":"In the phrase: \\"Level 1 {=their some_pronoun}{=Level 2 their some_pronoun}\\"","text":"Level 2 {=their some_pronoun}","tokenAliases":{"=their some_pronoun":"=m1"}}}}},"m":[{"token":"__subject__","type":1},null,null]}',
            '{"t":{"*":{"1":{"1":{"desc":"In the phrase: \\"Level 1 {=her some_pronoun}Level 2 {=her some_pronoun}\\"","text":"her some_pronoun"},"2":{"desc":"In the phrase: \\"Level 1 {=her some_pronoun}Level 2 {=his some_pronoun}\\"","text":"his some_pronoun"},"*":{"desc":"In the phrase: \\"Level 1 {=her some_pronoun}Level 2 {=their some_pronoun}\\"","text":"their some_pronoun"}},"2":{"1":{"desc":"In the phrase: \\"Level 1 {=his some_pronoun}Level 2 {=her some_pronoun}\\"","text":"her some_pronoun"},"2":{"desc":"In the phrase: \\"Level 1 {=his some_pronoun}Level 2 {=his some_pronoun}\\"","text":"his some_pronoun"},"*":{"desc":"In the phrase: \\"Level 1 {=his some_pronoun}Level 2 {=their some_pronoun}\\"","text":"their some_pronoun"}},"*":{"1":{"desc":"In the phrase: \\"Level 1 {=their some_pronoun}Level 2 {=her some_pronoun}\\"","text":"her some_pronoun"},"2":{"desc":"In the phrase: \\"Level 1 {=their some_pronoun}Level 2 {=his some_pronoun}\\"","text":"his some_pronoun"},"*":{"desc":"In the phrase: \\"Level 1 {=their some_pronoun}Level 2 {=their some_pronoun}\\"","text":"their some_pronoun"}}}},"m":[{"token":"__subject__","type":1},null,null]}',
        ],
        // __snapshots__/fbtJsx-test.js.snap "Test declarative (jsx) fbt syntax translation Enable explicit whitespace"
        'jsxExplicitWhitespace' => [
            '{"t":{"desc":"squelched","text":"{one} {two} {three}"},"m":[]}',
        ],
        // __snapshots__/fbtJsx-test.js.snap "Test declarative (jsx) fbt syntax translation should correctly destruct expression values in options"
        'jsxNumberExpression' => [
            '{"t":{"*":{"desc":"d","text":"str {count}"}},"m":[{"token":"count","type":2}]}',
        ],
        // __snapshots__/fbtJsx-test.js.snap "Test declarative (jsx) fbt syntax translation should insert param value for same-param"
        'jsxSameParam' => [
            '{"t":{"desc":"d","text":"str {foo} and {foo}"},"m":[]}',
        ],
        // __snapshots__/fbtJsx-test.js.snap "Test declarative (jsx) fbt syntax translation should maintain order of params and enums"
        'jsxParamsAndEnums' => [
            '{"t":{"x":{"*":{"desc":"some-desc","text":"Hello, {foo}x{bar}"}},"y":{"*":{"desc":"some-desc","text":"Hello, {foo}y{bar}"}}},"m":[null,{"token":"bar","type":2}]}',
        ],
        // __snapshots__/fbtJsx-test.js.snap "Test declarative (jsx) fbt syntax translation should handle object pronoun"
        'jsxPronounObject' => [
            '{"t":{"0":{"desc":"d","text":"I know this."},"1":{"desc":"d","text":"I know her."},"2":{"desc":"d","text":"I know him."},"*":{"desc":"d","text":"I know them."}},"m":[null]}',
        ],
        // __snapshots__/fbtJsx-test.js.snap "Test declarative (jsx) fbt syntax translation should handle subject+reflexive pronouns"
        'jsxSubjectReflexive' => [
            '{"t":{"1":{"1":{"desc":"d","text":"She wished herself a happy birthday."}},"2":{"2":{"desc":"d","text":"He wished himself a happy birthday."}},"*":{"*":{"desc":"d","text":"They wished themselves a happy birthday."}}},"m":[null,null]}',
        ],
        // __snapshots__/fbtJsx-test.js.snap "Test declarative (jsx) fbt syntax translation should handle common string"
        'jsxCommon' => [
            '{"t":{"desc":"The description for the common string \\"Done\\"","text":"Done"},"m":[]}',
        ],
        // __snapshots__/fbtJsx-test.js.snap "Test declarative (jsx) fbt syntax translation should not insert extra space"
        'jsxNotInsertExtraSpace' => [
            '{"t":{"desc":"Greating in i18n demo","text":"Hello, {guest}!"},"m":[]}',
        ],
        // __snapshots__/fbtJsx-test.js.snap "Test fbt transforms without the jsx transform when using within template literals should dedupe plurals"
        'jsxDedupePlurals' => [
            '{"t":{"*":{"*":{"desc":"desc...","text":"There are {number} photos."}},"_1":{"_1":{"desc":"desc...","text":"There is 1 photo."}}},"m":[null,{"token":"number","type":2,"singular":true}]}',
        ],
        // __snapshots__/fbtJsx-test.js.snap "Test fbt transforms without the jsx transform [legacy buggy behavior] <fbt:pronoun> should insert a space character between two fbt constructs that don't neighbor raw text"
        'jsxLegacyPluralSpace' => [
            '{"t":{"*":{"*":{"desc":"","text":"You can add thesetags to anything."}},"_1":{"_1":{"desc":"","text":"You can add this tag to anything."}}},"m":[null,null]}',
        ],
        // __snapshots__/fbtJsx-test.js.snap "Test fbt transforms without the jsx transform should fail to preserve whitespace in text when preserveWhitespace=true (known bug)"
        'jsxPreserveWhitespaceBug' => [
            '{"t":{"desc":"desc with 3   spaces","text":"Some text with 3 spaces in between."},"m":[]}',
        ],
        // __snapshots__/fbt-test.js.snap "fbt() API:  using FBT subject should accept "subject" as a parameter"
        'subject' => [
            '{"t":{"*":{"desc":"Bar","text":"Foo"}},"m":[{"token":"__subject__","type":1}]}',
        ],
        // __snapshots__/fbt-test.js.snap "Test double-lined params should remove the new line for param names that are two lines"
        'twoLineParamName' => [
            '{"t":{"desc":"d","text":"{two lines} test"},"m":[]}',
            '{"t":{"desc":"test","text":"simple"},"m":[]}',
        ],
    ];
}
