<?php

/**
 * @fbt {"author": "me", "project": "awesome sauce"}
 */

declare(strict_types=1);

namespace tests\fbt;

use fbt\Lib\IntlViewerContext;
use fbt\Runtime\Shared\fbt;
use fbt\Runtime\Shared\FbtHooks;
use fbt\Transform\FbtRuntime\FbtRuntimeTransform;
use fbt\Transform\FbtTransform\FbtTransform;
use fbt\Transform\FbtTransform\Translate\IntlVariations;

class fbtTest extends \tests\TestCase
{
    /**
     * @return array<string, string> hash => text
     */
    private static function hashToText(array $phrase): array
    {
        return array_map(function (array $leaf) {
            return $leaf['text'];
        }, $phrase['hashToLeaf']);
    }

    private static function transform(string $document): string
    {
        return FbtTransform::transform($document);
    }

    public function testKeepEmptyTagsAsText()
    {
        $this->assertSame('first test <p></p> <i class="icon"> </i>', self::transform(
            <<<FBT
<fbt desc="Empty tags test">
    first <fbt:param name="text">test</fbt:param>
    <p></p>
    <i class="icon"> </i>
</fbt>
FBT
        ));
        $this->assertSame(
            ['first {text} <p></p> <i class="icon"> </i>'],
            array_values(self::hashToText(FbtTransform::$phrases[0]))
        );
        $this->assertCount(1, FbtTransform::$phrases);
    }

    public function testEmptyFbt()
    {
        $this->assertSame('', self::transform('<fbt desc="Empty fbt"></fbt>'));
        $this->assertSame('', (string)fbt('', 'Empty fbt'));
    }

    public function testPlural()
    {
        $fbt = fbt(\fbt\fbt::plural('translator', 2, ['showCount' => 'yes']), 'Plural word test');

        $this->assertSame('2 translators', (string)$fbt);
        $this->assertSame([
            "xRsUF4xlmPKYhSMQEVpnSQ==" => "{number} translators",
            "Vi8Peajtp8H/5Net17DLXQ==" => "1 translator",
        ], self::hashToText(FbtTransform::$phrases[0]));

        $fbt = <<<FBT
<fbt desc="Plural word test">
    <fbt:plural name="number" showCount="yes" count=2>
		translator
	</fbt:plural>
</fbt>
FBT;

        $this->assertSame('2 translators', self::transform($fbt));
        $this->assertSame([
            "xRsUF4xlmPKYhSMQEVpnSQ==" => "{number} translators",
            "Vi8Peajtp8H/5Net17DLXQ==" => "1 translator",
        ], self::hashToText(FbtTransform::$phrases[1]));

        $fbt = <<<FBT
<fbt desc="Plural test">
	<fbt:plural many="Days" name="number_of_days" showCount="yes" count=2>
		Day
	</fbt:plural>

	<fbt:enum enum-range='["view","click"]' value="view"/>
</fbt>
FBT;

        $this->assertSame('2 Days view', self::transform($fbt));
        $this->assertSame([
            "nfMNQ35L2X2wxm1V5JnPFw==" => '{number_of_days} Days view',
            "bViLgZFus4oj2To65Mnb0g==" => '{number_of_days} Days click',
            "T0eXUYnDnGy21Yxqud7BiQ==" => '1 Day view',
            "V2VzyeXicEsO0IpF93YzLQ==" => '1 Day click',
        ], self::hashToText(FbtTransform::$phrases[2]));
    }

    public function testMultiplePlurals()
    {
        $fbt = <<<FBT
<fbt desc="plurals">
    There
	<fbt:plural many="are" count="4">
		is
	</fbt:plural>
	<fbt:plural many="likes" showCount="ifMany" count="4">
		a like
	</fbt:plural>
</fbt>
FBT;

        $this->assertSame('There are 4 likes', self::transform($fbt));
        $this->assertSame([
            "QqOT3StVJgyD57r6Bd8qYQ==" => 'There are {number} likes',
            'iq7jt+d44qRqSzMtxcOWrw==' => 'There are a like',
            '8vHbNv8V9q19dWwDPlQK6A==' => 'There is {number} likes',
            "Tpz74pYoVAlCa5cCG5MnKg==" => 'There is a like',
        ], self::hashToText(FbtTransform::$phrases[0]));
    }

    public function testPluralHtml()
    {
        $fbt = <<<FBT
<fbt desc="Plural HTML test">
    <p>
        <fbt:plural many="Days" name="number_of_days" showCount="yes" count="2">
            Day
        </fbt:plural>
    </p>

	<fbt:enum enum-range='["view","click"]' value="view"/>
</fbt>
FBT;
        $this->assertSame('<p>2 Days</p> view', self::transform($fbt));

        $fbt = <<<FBT
<fbt desc="Plural HTML test 2">
    <p><fbt:plural many="Days" name="number_of_days" showCount="yes" count="2">Day</fbt:plural></p>

	<strong><fbt:enum enum-range='["view","click"]' value="view"/></strong>
</fbt>
FBT;
        $this->assertSame('<p>2 Days</p> <strong>view</strong>', self::transform($fbt));

        $fbt = <<<FBT
<fbt desc="Plural HTML test 3">
    <p>
        <fbt:plural many="Days" name="number_of_days" showCount="yes" count="2">
            Day
        </fbt:plural>
    </p>

	<strong>
	    <fbt:enum enum-range='["view","click"]' value="view"/>
    </strong>
</fbt>
FBT;
        $this->assertSame('<p>2 Days</p> <strong>view</strong>', self::transform($fbt));
    }

    public function testEnumHtml()
    {
        $fbt = <<<FBT
<fbt desc="Enum test">
    Hello, continue
	<strong>
    	<fbt:enum enum-range='["here","there"]' value="there"/>
	</strong>
</fbt>
FBT;

        $this->assertSame('Hello, continue <strong>there</strong>', self::transform($fbt));

        $fbt = <<<FBT
<fbt desc="Example enum">
	<fbt:param name="name">Mark</fbt:param>
	has a
	<fbt:enum enum-range='{"LINK":"link","PAGE":"page","PHOTO":"photo","POST":"post","VIDEO":"video"}' value="LINK"/>
	to share! <b class="pad"> <a href="#">View</a> </b> a link.
</fbt>
FBT;

        $this->assertSame('Mark has a link to share! <b class="pad"><a href="#">View</a></b> a link.', self::transform($fbt));

        $fbt = <<<FBT
<fbt desc="Enum test">
    Hello, continue
	<strong>
	    <a href="/home" data-component="page-link" async="1">
    	    <fbt:enum enum-range='["here","there"]' value="there"/>
    	</a>
	</strong>
</fbt>
FBT;

        $this->assertSame('Hello, continue <strong><a href="/home" data-component="page-link" async="1">there</a></strong>', self::transform($fbt));
    }

    public function testParametersWithText()
    {
        $fbt = <<<FBT
<fbt desc="Parameters test">
    First
    <fbt:param name="two">second</fbt:param>,
    <fbt:param name="three">third</fbt:param>,
    <fbt:param name="four">fourth</fbt:param>.
</fbt>
FBT;

        $this->assertSame('First second, third, fourth.', self::transform($fbt));
    }

    public function testParametersWithTextAndHtml()
    {
        $fbt = <<<FBT
<fbt desc="Parameters test">
    First
    <strong>
        <fbt:param name="two">second</fbt:param>
    </strong>,
    <span>
        <fbt:param name="three">third</fbt:param>
    </span>,
    <div>
        <fbt:param name="four">fourth</fbt:param>
    </div>.
</fbt>
FBT;

        $this->assertSame('First <strong>second</strong>, <span>third</span>, <div>fourth</div>.', self::transform($fbt));
    }

    public function testBuffer()
    {
        $this->expectOutputString('A simple string');

        fbtTransform();

        echo <<<HTML
<fbt desc="It's simple">A simple string</fbt>
HTML;

        endFbtTransform();
    }

    public function testBrokenHtmlTransform()
    {
        $fbt = <<<FBT
<div>
    <fbt desc="It's simple">A simple string</fbt>

    </p><p>
    <span>
</div>
</span>
FBT;

        $this->assertSame('<div>
    A simple string

    </p><p>
    <span>
</div>
</span>', self::transform($fbt));
    }

    public function testMixedHtmlTagsWithParams()
    {
        $fbt = <<<FBT
<fbt desc="buy prompt">
    Buy a brand
    <a class="special-class">
        new
        <strong>
            <span>
                iPhone
            </span>
        </strong>
    </a>
    with
    <strong>
        <a>
            <fbt:enum enum-range='["charger","mac"]' value="mac" />
        </a>
    </strong>!
</fbt>
FBT;

        $this->assertSame('Buy a brand <a class="special-class">new <strong><span>iPhone</span></strong></a> with <strong><a>mac</a></strong>!', self::transform($fbt));
        $this->assertSame([
            'pQKZTyowAK5XN4xLyWYszQ==' => 'Buy a brand {=new [=iPhone]} with {=charger}!',
            'P6tpQpCTjgH64zOvNI2wrw==' => 'Buy a brand {=new [=iPhone]} with {=mac}!',
            'J7FLBX6iQuohPZFtIb78+A==' => 'new {=iPhone}',
            'E0z3iFtvr9OsekekkTDKqg==' => 'new {=iPhone}',
            '5h8zfIIQzx/UfYfNuvbgDQ==' => '{=iPhone}',
            '07YBa7/RuKHWbuPO7TJebg==' => '{=iPhone}',
            'HeifMT106tBKO7ohN9hl4A==' => 'iPhone',
            'hjckygRDYszt9X7DOf08+g==' => 'iPhone',
            'DpHTNrxeDA5ZtpheHn6iSw==' => '{=charger}',
            '2cgDd+HW7aIYLdUIop9owQ==' => '{=mac}',
            'BFobYZ6Gaq+4V4YW93Lv5g==' => 'charger',
            'bmReaYa94Kfnl/XkcLGlYA==' => 'mac',
        ], array_merge(...array_map([self::class, 'hashToText'], FbtTransform::$phrases)));
        $this->assertSame([1 => 0, 2 => 1, 3 => 2, 4 => 0, 5 => 4], FbtTransform::$childToParent);
    }

    public function testMixedPluralHtmlTagsWithParams()
    {
        $fbt = <<<FBT
<fbt desc="gender with plural example">
    <fbt:param name="name" gender="1">
		<b class="padRight">Richard</b>
    </fbt:param>
	has shared
	<a class="link" href="#">
		<fbt:plural
            many="photos"
            showCount="ifMany"
            count="3">
			a photo
		</fbt:plural>
	</a>
	with you.
</fbt>
FBT;

        $this->assertSame('
		<b class="padRight">Richard</b>
     has shared <a class="link" href="#">3 photos</a> with you.', self::transform($fbt));
    }

    public function testMixedPronounHtmlTags()
    {
        $fbt = <<<FBT
<fbt desc="pronoun example">
	<fbt:param name="name">Kate</fbt:param>
	shared
	<strong>
	    <fbt:pronoun type="possessive" gender=1 />
    </strong>
	photo with you.
</fbt>
FBT;

        $this->assertSame('Kate shared <strong>her</strong> photo with you.', self::transform($fbt));
    }

    public function testCapitalizedPronoun()
    {
        $fbt = <<<FBT
<fbt desc="Capitalized possessive pronoun">
	<fbt:pronoun type="possessive" gender="2" capitalize="true" />
	birthday is today.
</fbt>
FBT;

        $this->assertSame('His birthday is today.', self::transform($fbt));
    }

    public function testUnknownUsageValue()
    {
        $this->expectExceptionMessage('fbt:pronoun attribute "type" must be one of [object,possessive,reflexive,subject]');

        self::transform(
            <<<FBT
<fbt desc="Expect error exception">
    Today is
	<fbt:pronoun type="possession" gender="2" human="false" />
	a happy birthday.
</fbt>
FBT
        );
    }

    public function testSimpleHtml()
    {
        $fbt = <<<FBT
<fbt desc="auto-wrap example">
	Go on an <a href="#"> <span>awesome</span> vacation </a>
</fbt>
FBT;

        $this->assertSame('Go on an <a href="#"><span>awesome</span> vacation</a>', self::transform($fbt));
        $this->assertSame([
            "phrases" => [
                0 => [
                    "hashToLeaf" => [
                        "V2xk3OfcDrMIA7HC/rIXIg==" => [
                            "text" => "Go on an {=awesome vacation}",
                            "desc" => "auto-wrap example",
                        ],
                    ],
                    "author" => "richard",
                    "project" => "website app",
                    "jsfbt" => [
                        "t" => [
                            "desc" => "auto-wrap example",
                            "text" => "Go on an {=awesome vacation}",
                            "tokenAliases" => [
                                "=awesome vacation" => "=m1",
                            ],
                        ],
                        "m" => [],
                    ],
                ],
                1 => [
                    "hashToLeaf" => [
                        "feX2lgKwwomWUYP5/78klg==" => [
                            "text" => "{=awesome} vacation",
                            "desc" => "In the phrase: \"Go on an {=awesome vacation}\"",
                        ],
                    ],
                    "author" => "richard",
                    "project" => "website app",
                    "jsfbt" => [
                        "t" => [
                            "desc" => "In the phrase: \"Go on an {=awesome vacation}\"",
                            "text" => "{=awesome} vacation",
                            "tokenAliases" => [
                                "=awesome" => "=m1",
                            ],
                        ],
                        "m" => [],
                    ],
                ],
                2 => [
                    "hashToLeaf" => [
                        "a7sBUhipyZur9yE8H6dk2A==" => [
                            "text" => "awesome",
                            "desc" => "In the phrase: \"Go on an {=awesome} vacation\"",
                        ],
                    ],
                    "author" => "richard",
                    "project" => "website app",
                    "jsfbt" => [
                        "t" => [
                            "desc" => "In the phrase: \"Go on an {=awesome} vacation\"",
                            "text" => "awesome",
                        ],
                        "m" => [],
                    ],
                ],
            ],
            "childParentMappings" => [
                1 => 0,
                2 => 1,
            ],
        ], FbtTransform::toArray());
    }

    public function testSimpleString()
    {
        $fbt = <<<FBT
<fbt desc="It's simple">A simple string</fbt>
FBT;

        $this->assertSame('A simple string', self::transform($fbt));
    }

    public function testStripOutNewlines()
    {
        $fbt = <<<FBT
<fbt desc="Test trailing space when not last child">
    Preamble
    <fbt:param name="parm">{blah}</fbt:param>
</fbt>
FBT;

        $this->assertSame('Preamble {blah}', self::transform($fbt));
    }

    public function testStripOutMoreNewlines()
    {
        $fbt = <<<FBT
<fbt desc="moar lines">
    A simple string...
    with some other stuff.
</fbt>
FBT;

        $this->assertSame('A simple string... with some other stuff.', self::transform($fbt));
    }

    public function testHandleParams()
    {
        $fbt = <<<FBT
<fbt desc="a message!">
    A parameterized message to:
    <fbt:param name="personName">{theName}</fbt:param>
</fbt>
FBT;

        $this->assertSame('A parameterized message to: {theName}', self::transform($fbt));
    }

    public function testHandleEmptyString()
    {
        $fbt = <<<FBT
<fbt desc="a message!">
    A parameterized message to:
    <fbt:param name="personName"> </fbt:param>
</fbt>
FBT;

        $this->assertSame('A parameterized message to:  ', self::transform($fbt));
    }

    public function testNestWithinHtmlNodes()
    {
        $fbt = <<<FBT
<div>
    <fbt desc="nested!">
      A nested string
    </fbt>
</div>
FBT;

        $this->assertSame('<div>
    A nested string
</div>', self::transform($fbt));
    }

    public function testHouseArbitraryMarkupWithinFbtParamHtmlNodes()
    {
        $fbt = <<<FBT
<div>
    <fbt desc="...">
      <fbt:param name="time">now</fbt:param>
       by
      <fbt:param name="user name">
        <a href="/link">
          me
        </a>
      </fbt:param>
    </fbt>
</div>
FBT;

        $this->assertSame('<div>
    now by ' . '
        <a href="/link">
          me
        </a>
      ' . '
</div>', self::transform($fbt));
    }

    public function testEnumsWithArrayValues()
    {
        $fbt = <<<FBT
<fbt desc="enums!">
    Click to see
    <fbt:enum enum-range='{
      "id1":"groups",
      "id2":"photos",
      "id3":"videos"}'
      value="id3"
    />
  </fbt>
FBT;

        $this->assertSame('Click to see videos', self::transform($fbt));
    }

    public function testEnumsWithMoreText()
    {
        $fbt = <<<FBT
<fbt desc="enums!">
    Click to see
    <fbt:enum enum-range='{
      "id1":"groups",
      "id2":"photos",
      "id3":"videos"}'
      value="id3"
    />
    Hey-hey!
</fbt>
FBT;

        $this->assertSame('Click to see videos Hey-hey!', self::transform($fbt));
    }

    public function testHandleVariations()
    {
        $fbt = <<<FBT
<fbt desc="variations!">
    Click to see
    <fbt:param name="count" number="true">10</fbt:param>
    links
</fbt>
FBT;

        $this->assertSame('Click to see 10 links', self::transform($fbt));
    }

    public function testInsertParamValueForSameParam()
    {
        $fbt = <<<FBT
<fbt desc="d">str
    <fbt:param name="foo">{Bar}</fbt:param> and
    <fbt:same-param name="foo"/>
</fbt>
FBT;

        $this->assertSame('str {Bar} and {Bar}', self::transform($fbt));
    }

    public function testSameParamThatDoesNotExist()
    {
        self::expectExceptionMessage('Expected fbt `sameParam` construct with name=`bar` to refer to a `name` or `param` construct using the same token name');

        self::transform(
            <<<FBT
<fbt desc="d">str
    <fbt:param name="foo">{bar}</fbt:param> and
    <fbt:same-param name="bar"/>
</fbt>
FBT
        );
    }

    public function testSameParamWithPlural()
    {
        self::expectExceptionMessage('Expected fbt `sameParam` construct with name=`tokenName` to refer to a `name` or `param` construct using the same token name');

        self::transform(
            <<<FBT
<fbt desc="d">str
    <fbt:plural name="tokenName" count="3">cat</fbt:plural> and
    <fbt:same-param name="tokenName"/>
</fbt>
FBT
        );
    }

    // TODO: t17559607 Fix space normalization
    // public function testPreservedWhitespaceInText()
    // {
    //     $fbt = <<<FBT
    //<fbt desc="two  spaces" subject="2" preserveWhitespace="true">Foo    Bar</fbt>
    //FBT;
    //
    //     $this->assertSame('Foo    Bar', self::transform($fbt));
    // }

    public function testPreservedWhitespaceInDesc()
    {
        $fbt = <<<FBT
<fbt desc="two  spaces
new line" subject="2" preserveWhitespace="true">
    Foo    Bar
</fbt>
FBT;

        $this->assertSame('Foo Bar', self::transform($fbt));
        $this->assertSame('two  spaces
new line', FbtTransform::toArray()['phrases'][0]['jsfbt']['t']['*']['desc']);
    }

    public function testTreatMultilineDescsAsASingleLine()
    {
        $fbt = <<<FBT
<fbt desc="hi  how are you today im doing well i guess
how is your mother is she well yeah why not lets go
home and never come back.">
    lol
</fbt>
FBT;
        self::transform($fbt);

        $phrase = end(FbtTransform::$phrases);

        $this->assertSame('hi how are you today im doing well i guess'
            . ' how is your mother is she well yeah why not lets go'
            . ' home and never come back.', $phrase['jsfbt']['t']['desc']);
    }

    public function testNotInsertExtraSpace()
    {
        $fbt = <<<FBT
<fbt desc="Greating in i18n demo">
    Hello, <fbt:param name="guest">
      Guest
    </fbt:param>!
</fbt>
FBT;

        $this->assertSame('Hello, ' . '
      Guest
    !', self::transform($fbt));

        $this->assertSame(self::hashToText(FbtTransform::$phrases[0]), [
            "Y+VXkqL06K2MLtOR4LgsTw==" => "Hello, {guest}!",
        ]);
    }

    public function testNumberFormatter()
    {
        $fbt = <<<FBT
<fbt desc="Stock information">
    There are <fbt:plural many="items" count="1000000" showCount="yes">item</fbt:plural> on stock.
</fbt>
FBT;

        $this->assertSame('There are 1 000 000 items on stock.', self::transform($fbt));
    }

    public function testNumberLiteralValueAsIs()
    {
        $fbt = <<<FBT
<fbt desc="Stock information">
    A total amount is <fbt:param name="count">10000</fbt:param>
</fbt>
FBT;

        $this->assertSame('A total amount is 10000', self::transform($fbt));
    }

    public function testUnicodeText()
    {
        $fbt = (string)fbt('Pick an emoji…', 'placeholder text for emoji picker');

        $hashKey = FbtRuntimeTransform::transform(FbtTransform::$phrases[0])['options']['hk'];

        $this->assertSame('ZAVir', $hashKey);
        $this->assertSame('Pick an emoji…', $fbt);
    }

    public function testHtmlBreak()
    {
        $fbt = <<<FBT
<fbt desc="Foo">
    Bar<br>
    Bar
</fbt>
FBT;

        $this->assertSame('Bar<br> Bar', self::transform($fbt));

        $fbt = <<<FBT
<fbt desc="Foo">
    Bar<fbt:param name="lineBreak"><br></fbt:param>
    Bar<fbt:same-param name="lineBreak" />
    Bar
</fbt>
FBT;

        $this->assertSame('Bar<br> Bar<br> Bar', self::transform($fbt));

        $fbt = <<<FBT
<fbt desc="Bar">
    Foo<br/><fbt:param name="lineBreakk">Bar<br/>Baz</fbt:param>
</fbt>
FBT;

        $this->assertSame('Foo<br/>Bar<br/>Baz', self::transform($fbt));
    }

    public function testCheckAlreadyStoredHashes()
    {
        $hash1 = null;
        $hash2 = null;

        for ($i = 0; $i < 10; $i++) {
            $fbt = <<<FBT
<fbt desc="Description of a top-level Page category">
    Local business or place
</fbt>
FBT;

            $this->assertSame('Local business or place', self::transform($fbt));

            if (! $hash1) {
                $hash1 = array_keys(current(FbtTransform::toArray()['phrases'])['hashToLeaf'])[0];
            }

            $fbt = <<<FBT
<fbt desc="Full legal disclaimer text for placing orders">
    By clicking "Order" you agree to the <a href="/terms">Terms of Use</a>.
</fbt>
FBT;

            $this->assertSame('By clicking "Order" you agree to the <a href="/terms">Terms of Use</a>.', self::transform($fbt));

            if (! $hash2) {
                $hash2 = array_keys(current(FbtTransform::toArray()['phrases'])['hashToLeaf'])[0];
            }
        }

        FbtHooks::storePhrases();

        $check1 = 0;
        $check2 = 0;

        foreach (FbtHooks::$sourceStrings['phrases'] as $phrase) {
            if (array_key_exists($hash1, $phrase['hashToLeaf'])) {
                $check1++;
            }

            if (array_key_exists($hash2, $phrase['hashToLeaf'])) {
                $check2++;
            }
        }

        $this->assertSame(1, $check1);
        $this->assertSame(1, $check2);
    }

    public function testUsingFbtSubject()
    {
        $fbt = <<<FBT
<fbt desc="Foo" subject="2">
    Bar
</fbt>
FBT;

        $this->assertSame('Bar', self::transform($fbt));
    }

    public function testFbtInParams()
    {
        $fbt = <<<FBT
<fbt desc="d">
  <fbt:param
    name="two
          lines">
    <b>
      <fbt desc="test">simple</fbt>
    </b>
  </fbt:param>
  test
</fbt>
FBT;

        $this->assertSame('
    <b>
      simple
    </b>
   test', self::transform($fbt));
    }

    public function testViewerContext()
    {
        $fbt = new fbt();

        FbtHooks::locale('ro_RO'); // IntlCLDRNumberType19

        $ONE = IntlVariations::INTL_NUMBER_VARIATIONS['ONE'];
        $FEW = IntlVariations::INTL_NUMBER_VARIATIONS['FEW'];
        $MALE = IntlVariations::INTL_GENDER_VARIATIONS['MALE'];
        $FEMALE = IntlVariations::INTL_GENDER_VARIATIONS['FEMALE'];

        $table = [
            '__vcg' => 1, // viewer-context gender
            '*' => [],
        ];

        $table['*']['A'] = ['*' => 'A,UNKNOWN,OTHER {name} has {num}'];
        $table['*']['A'][$ONE] = 'A,UNKNOWN,ONE {name} has {num}';
        $table['*']['A'][$FEW] = 'A,UNKNOWN,FEW {name} has {num}';
        $table['*']['B'] = ['*' => 'B,UNKNOWN,OTHER {name} has {num}'];
        $table['*']['B'][$ONE] = 'B,UNKNOWN,ONE {name} has {num}';
        $table['*']['B'][$FEW] = 'B,UNKNOWN,FEW {name} has {num}';
        $table[$MALE] = ['A' => ['*' => 'A,MALE,OTHER {name} has {num}']];
        $table[$MALE]['A'][$ONE] = 'A,MALE,ONE {name} has {num}';
        // $table['*'][$MALE]['A'][$FEW] = fallback to other ^^^
        // $table['*'][$MALE]['B'] = fallback to unknown gender ^^^
        $table[$FEMALE] = ['B' => ['*' => 'B,FEMALE,OTHER {name} has {num}']];
        $table[$FEMALE]['B'][$FEW] = 'B,FEMALE,FEW {name} has {num}';
        // $table[$FEMALE]['B'][$ONE] = fallback to other ^^^
        // $table[$FEMALE]['A'] = fallback to unknown gender ^^^

        $few = $fbt->_param('num', 10, [0] /*Variations::NUMBER*/);
        $other = $fbt->_param('num', 20, [0]);
        $one = $fbt->_param('num', 1, [0]);
        $A = $fbt->_enum('A', ['A' => 'A', 'B' => 'B']);
        $B = $fbt->_enum('B', ['A' => 'A', 'B' => 'B']);
        $name = $fbt->_param('name', 'Bob');

        // GENDER UNKNOWN
        $tests = [
            ['arg' => [$A, $few, $name], 'expected' => 'A,UNKNOWN,FEW Bob has 10'],
            ['arg' => [$A, $one, $name], 'expected' => 'A,UNKNOWN,ONE Bob has 1'],
            ['arg' => [$A, $other, $name], 'expected' => 'A,UNKNOWN,OTHER Bob has 20'],
            ['arg' => [$B, $few, $name], 'expected' => 'B,UNKNOWN,FEW Bob has 10'],
            ['arg' => [$B, $one, $name], 'expected' => 'B,UNKNOWN,ONE Bob has 1'],
            ['arg' => [$B, $other, $name], 'expected' => 'B,UNKNOWN,OTHER Bob has 20'],
        ];

        IntlViewerContext::setGender(IntlVariations::INTL_GENDER_VARIATIONS['UNKNOWN']);

        foreach ($tests as $test) {
            $this->assertEquals($test['expected'], (string)$fbt->_($table, $test['arg']));
        }

        // GENDER MALE
        $tests = [
            ['arg' => [$A, $few, $name], 'expected' => 'A,MALE,OTHER Bob has 10'],
            ['arg' => [$A, $one, $name], 'expected' => 'A,MALE,ONE Bob has 1'],
            ['arg' => [$A, $other, $name], 'expected' => 'A,MALE,OTHER Bob has 20'],
            ['arg' => [$B, $few, $name], 'expected' => 'B,UNKNOWN,FEW Bob has 10'],
            ['arg' => [$B, $one, $name], 'expected' => 'B,UNKNOWN,ONE Bob has 1'],
            ['arg' => [$B, $other, $name], 'expected' => 'B,UNKNOWN,OTHER Bob has 20'],
        ];

        IntlViewerContext::setGender(IntlVariations::INTL_GENDER_VARIATIONS['MALE']);

        foreach ($tests as $test) {
            $this->assertEquals($test['expected'], (string)$fbt->_($table, $test['arg']));
        }

        // GENDER FEMALE
        $tests = [
            ['arg' => [$A, $few, $name], 'expected' => 'A,UNKNOWN,FEW Bob has 10'],
            ['arg' => [$A, $one, $name], 'expected' => 'A,UNKNOWN,ONE Bob has 1'],
            ['arg' => [$A, $other, $name], 'expected' => 'A,UNKNOWN,OTHER Bob has 20'],
            ['arg' => [$B, $few, $name], 'expected' => 'B,FEMALE,FEW Bob has 10'],
            ['arg' => [$B, $one, $name], 'expected' => 'B,FEMALE,OTHER Bob has 1'],
            ['arg' => [$B, $other, $name], 'expected' => 'B,FEMALE,OTHER Bob has 20'],
        ];

        IntlViewerContext::setGender(IntlVariations::INTL_GENDER_VARIATIONS['FEMALE']);

        foreach ($tests as $test) {
            $this->assertEquals($test['expected'], (string)$fbt->_($table, $test['arg']));
        }

        FbtHooks::locale(null);
    }

    public function testRemovePunctuationWhenAValueEndsWithIt()
    {
        $fbt = (string)fbt('Play ' . \fbt\fbt::param('game', 'Chess!') . '!', 'test');

        $this->assertSame('Play Chess!', $fbt);

        // todo: Don't strip punctuation that isn't redundant
        // $fbt = (string)fbt("What's on your mind " . \fbt\fbt::param('name', 'T.J.') . '?', 'test');

        // $this->assertSame('What\'s on your mind T.J.?', $fbt);
    }

    public function testMultipleTagsInParameter()
    {
        $fbt = (string)fbt('By artist ' . \fbt\fbt::param('artist', 'Lou Reed & Metallica'), 'test');

        $this->assertSame('By artist Lou Reed & Metallica', $fbt);

        $fbt = (string)fbt('By artist ' . \fbt\fbt::param('artist', '<span><a>Lou Reed</a> & <a>Metallica</a></span>'), 'test');

        $this->assertSame('By artist <span><a>Lou Reed</a> & <a>Metallica</a></span>', $fbt);

        $fbt = (string)fbt('By artist ' . \fbt\fbt::param('artist', '<a>Lou Reed</a> & <a>Metallica</a>'), 'test');

        $this->assertSame('By artist <a>Lou Reed</a> & <a>Metallica</a>', $fbt);
    }

    public function testEmptyParameter()
    {
        $fbt = (string)fbt('Search results for \'' . \fbt\fbt::param('query', '') . '\'', 'page title');

        $this->assertSame('Search results for \'\'', $fbt);
    }

    public function testUnicodeParameter()
    {
        $fbt = (string)fbt('Search results for “' . \fbt\fbt::param('query', 'ß') . '”', 'page title');

        $this->assertSame('Search results for “ß”', $fbt);
    }

    public function testValuesThatLookLikeTokenPatterns()
    {
        $fbt = (string)fbt(
            'with tokens ' .
            \fbt\fbt::param('tokenA', '{tokenB}') .
            ' and ' .
            \fbt\fbt::param('tokenB', 'B'),
            'test'
        );

        $this->assertSame('with tokens {tokenB} and B', $fbt);
    }

    public function testSubject()
    {
        $fbt = fbt('You<fbt:param name="lineBreak"><br></fbt:param>see<fbt:same-param name="lineBreak"/>the world', 'expose subject', [
            'subject' => \fbt\Lib\IntlVariations::GENDER_MALE,
        ]);

        $this->assertSame('You<br>see<br>the world', (string)$fbt);
    }

    public function testJsonSerialization()
    {
        $fbt = fbt('simple text', 'desc');

        $this->assertSame('["simple text"]', json_encode([$fbt]));
    }

    public function testDocblockOptions()
    {
        $fbt = fbt('A string that moved files', 'options!', [
            'author' => 'jwatson',
            'project' => 'Super Secret',
        ]);

        $this->assertSame('A string that moved files', (string)$fbt);
        $this->assertSame('jwatson', FbtTransform::$phrases[0]['author']);
        $this->assertSame('Super Secret', FbtTransform::$phrases[0]['project']);

        (string)fbt('A parameterized message to: ' . \fbt\fbt::param('personName', 'Thomas'), 'desc');

        $this->assertSame('me', FbtTransform::$phrases[1]['author']);
        $this->assertSame('awesome sauce', FbtTransform::$phrases[1]['project']);
    }
}
