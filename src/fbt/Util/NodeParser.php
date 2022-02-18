<?php

declare(strict_types=1);

namespace fbt\Util;

use fbt\Util\SimpleHtmlDom\DOM;

class NodeParser
{
    public static function file_get_html()
    {
        return call_user_func_array('\fbt\Util\SimpleHtmlDom\file_get_html', func_get_args());
    }

    public static function parse(): DOM
    {
        return call_user_func_array('\fbt\Util\SimpleHtmlDom\str_get_html', func_get_args());
    }
}
