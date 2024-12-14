<?php

declare(strict_types=1);

namespace tests\latte;

use Latte\Engine;

class fbtLatteTest extends \tests\TestCase
{
    public function testFbt()
    {
        $this->assertMatchesSnapshot(
            $this->renderLatte('fbt')
        );
    }

    public function testMultilineFbt()
    {
        $this->assertMatchesSnapshot(
            $this->renderLatte('fbt-multiline')
        );
    }

    private function renderLatte(string $view, array $params = []): string
    {
        $latte = new Engine();

        return $latte->renderToString(__DIR__ . '/views/' . $view . '.latte', $params);
    }
}
