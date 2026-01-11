<?php

declare(strict_types=1);

namespace fbt\Util;

use dobron\DomForge\Configuration;
use dobron\DomForge\DomForge;

class NodeParser
{
    public static function parse(string $str)
    {
        if (empty($str)) {
            return false;
        }

        // fbt self-closing tags are registered globally in helpers.php
        $configuration = (new Configuration())
            ->setLowercase(false)
            ->setForceTagsClosed(true)
            ->setTargetCharset('UTF-8')
            ->setRemoveLineBreaks(false)
            ->setDefaultBrText("\n")
            ->setDefaultSpanText(' ');

        return DomForge::fromHtml($str, $configuration);
    }
}
