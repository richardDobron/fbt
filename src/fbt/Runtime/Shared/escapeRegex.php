<?php

namespace fbt\Runtime\Shared;

class escapeRegex
{
    /**
     * Escapes regex special characters from a string, so it can be
     * used as a raw search term inside an actual regex.
     */
    public static function escapeRegex(string $str): string
    {
        // From http://stackoverflow.com/questions/14076210/
        return preg_replace('/([.?*+\^$\[\]\\\\(){}|\-])/', '\\\\$1', $str);
    }
}
