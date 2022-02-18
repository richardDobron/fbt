<?php

declare(strict_types=1);

namespace tests\html;

use function fbt\createElement;

class createElementTest extends \tests\TestCase
{
    public function testElementWithNoContent()
    {
        $this->assertEquals('<span></span>', createElement('span'));
        $this->assertEquals('<span></span>', createElement('span', false));
    }

    public function testSelfClosedElement()
    {
        $this->assertEquals('<input type="number" id="quantity"/>', createElement('input', null, ['type' => 'number', 'id' => 'quantity']));
    }

    public function testSpecialChars()
    {
        $this->assertEquals('<input placeholder="🛒"/>', createElement('input', null, ['placeholder' => '🛒']));

        $this->assertEquals('<div title="👍: like">👍</div>', createElement('div', '👍', ['title' => '👍: like']));

        $this->assertEquals('<div title="Rock&amp;Roll">Rock&Roll</div>', createElement('div', 'Rock&Roll', ['title' => 'Rock&Roll']));

        $this->assertEquals('<div title="Categories &raquo; 💻 &gt; Acer">Categories &raquo; 💻 > Acer</div>', createElement('div', 'Categories &raquo; 💻 > Acer', ['title' => 'Categories &raquo; 💻 > Acer']));
    }
}
