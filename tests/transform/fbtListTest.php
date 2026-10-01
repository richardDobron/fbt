<?php

namespace tests\transform;

use fbt\Exceptions\FbtParserException;
use fbt\fbs;
use fbt\fbt;
use fbt\FbtConfig;
use fbt\Runtime\Shared\FbtHooks;
use fbt\Runtime\Shared\IntlList;
use fbt\Transform\FbtTransform\FbtTransform;

/**
 * <fbt:list> and fbt::list() (from fbtee)
 */
class fbtListTest extends \tests\TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        FbtConfig::set('collectFbt', false);
        FbtHooks::locale('en_US');
        fbt::_purgeCache();
    }

    protected function tearDown(): void
    {
        FbtHooks::locale(null);
        FbtConfig::set('collectFbt', true);
        fbt::_purgeCache();

        parent::tearDown();
    }

    private static function compiledCount(): int
    {
        $compiled = new \ReflectionProperty(FbtTransform::class, 'compiled');
        if (PHP_VERSION_ID < 80100) {
            $compiled->setAccessible(true);
        }

        return count($compiled->getValue());
    }

    public function testFunctionalList()
    {
        $this->assertSame(
            'Available Locations: Tokyo, London and Vienna.',
            (string)fbt(['Available Locations: ', fbt::list('locations', ['Tokyo', 'London', 'Vienna']), '.'], 'Lists')
        );
        $this->assertSame(
            'Available Locations: Tokyo, London or Vienna.',
            (string)fbt('Available Locations: ' . fbt::list('locations', ['Tokyo', 'London', 'Vienna'], IntlList::CONJUNCTIONS['OR']) . '.', 'Lists')
        );
        $this->assertSame(
            "Available Locations: Tokyo \u{2022} London \u{2022} Vienna.",
            (string)fbt(['Available Locations: ', fbt::list('locations', ['Tokyo', 'London', 'Vienna'], 'none', 'bullet'), '.'], 'Lists')
        );
        $this->assertSame(
            'Available Locations: Tokyo; London and Vienna.',
            (string)fbt(['Available Locations: ', fbt::list('locations', ['Tokyo', 'London', 'Vienna'], null, 'semicolon'), '.'], 'Lists')
        );
    }

    // fbt-list-test.tsx of fbtee: fbt.list('locations', ['Tokyo', 'London', 'Vienna'], null, 'or')
    public function testFunctionalListWithDelimiterOnly()
    {
        $this->assertSame(
            'Available Locations: Tokyo, London and Vienna',
            (string)fbt('Available Locations: ' . fbt::list('locations', ['Tokyo', 'London', 'Vienna'], null, 'or'), 'Lists')
        );
    }

    public function testHtmlList()
    {
        $items = htmlspecialchars(json_encode(['Tokyo', 'London', 'Vienna']));

        $this->assertSame(
            'Available Locations: Tokyo, London and Vienna.',
            (string)fbt('Available Locations: <fbt:list name="locations" items="' . $items . '" />.', 'Lists')
        );
        $this->assertSame(
            'Available Locations: Tokyo, London and Vienna.',
            (string)fbt('Available Locations: <fbt:list name="locations" conjunction="and" items="' . $items . '" />.', 'Lists')
        );
        $this->assertSame(
            "Available Locations: Tokyo \u{2022} London and Vienna.",
            (string)fbt('Available Locations: <fbt:list name="locations" delimiter="bullet" items="' . $items . '" />.', 'Lists')
        );
        $this->assertSame(
            "Available Locations: Tokyo \u{2022} London or Vienna.",
            (string)fbt('Available Locations: <fbt:list name="locations" conjunction="or" delimiter="bullet" items="' . $items . '" />.', 'Lists')
        );
    }

    public function testHtmlListErrors()
    {
        try {
            fbt('<fbt:list name="locations" items="Tokyo" />', 'Lists')->__toString();
            $this->fail('Expected an exception');
        } catch (FbtParserException $e) {
            $this->assertStringContainsString("<fbt:list> attribute 'items' must be a JSON array.", $e->getMessage());
        }

        try {
            fbt('<fbt:list items="[]" />', 'Lists')->__toString();
            $this->fail('Expected an exception');
        } catch (FbtParserException $e) {
            $this->assertStringContainsString('name', $e->getMessage());
        }
    }

    public function testCallsiteIsCompiledOnceForAllItems()
    {
        $render = function (): array {
            $results = [];
            foreach ([[['Tokyo'], null], [['Tokyo', 'London'], null], [['Tokyo', 'London', 'Vienna'], 'or'], [['Ann', 'Bob', 'Cid'], 'or']] as [$items, $conjunction]) {
                $results[] = (string)fbt(['Locations: ', fbt::list('locations', $items, $conjunction)], 'Lists');
            }

            return $results;
        };

        $this->assertSame([
            'Locations: Tokyo',
            'Locations: Tokyo and London',
            'Locations: Tokyo, London or Vienna',
            'Locations: Ann, Bob or Cid',
        ], $render());
        $compiledCount = self::compiledCount();
        $render();
        $this->assertSame($compiledCount, self::compiledCount());
    }

    public function testRichItems()
    {
        $this->assertSame(
            'Friends: Ann and <b>Bob</b>',
            (string)fbt(['Friends: ', fbt::list('friends', [fbt('Ann', 'name'), '<b>Bob</b>'])], 'Lists')
        );
    }

    public function testFbsList()
    {
        $this->assertSame('Tags: a, b and c', (string)fbs(['Tags: ', fbs::list('tags', ['a', 'b', 'c'])], 'Tags'));
    }

    public function testCollectedPhrase()
    {
        FbtConfig::set('collectFbt', true);
        FbtTransform::$phrases = [];

        fbt(['Available Locations: ', fbt::list('locations', ['Tokyo', 'London', 'Vienna']), '.'], 'Lists')->__toString();

        $phrase = FbtTransform::$phrases[0];
        $this->assertSame('Available Locations: {locations}.', $phrase['jsfbt']['t']['text']);
        $this->assertSame([], $phrase['jsfbt']['m']);
    }

    public function testMarkup()
    {
        $this->assertSame(
            '<fbt desc="Lists">Locations: <fbt:list name="locations" items="[&quot;Tokyo&quot;,&quot;London&quot;]" conjunction="or"></fbt:list></fbt>',
            (string)fbt(['Locations: ', fbt::list('locations', ['Tokyo', 'London'], 'or')], 'Lists', ['transform' => false])
        );
    }

    public function testNestedListError()
    {
        $this->expectException(FbtParserException::class);
        $this->expectExceptionMessage(
            'Expected fbt constructs to not nest inside fbt constructs, but found fbt.list nest inside fbt.plural'
        );

        fbt(['a ', fbt::plural('cat ' . fbt::list('y', ['a', 'b']), 2)], 'd')->__toString();
    }
}
