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
            ->setForceTagsClosed(false)
            ->setTargetCharset('UTF-8')
            ->setRemoveLineBreaks(false)
            ->setDefaultBrText("\n")
            ->setDefaultSpanText(' ');

        // DomForge doesn't recognize a valueless attribute followed directly by "/>"
        // (e.g. <fbt:pronoun ... human/>) as a self-closing element
        $str = preg_replace('~(<[^<>]*\s[A-Za-z_:][\w:.-]*)/>~', '$1 />', $str);

        return DomForge::fromHtml($str, $configuration);
    }
}
