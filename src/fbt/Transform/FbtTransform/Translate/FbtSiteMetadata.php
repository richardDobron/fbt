<?php

namespace fbt\Transform\FbtTransform\Translate;

class FbtSiteMetadata
{
    /**
     * @throws \fbt\Exceptions\FbtException
     */
    public static function wrap(array $rawEntries): array
    {
        return array_map(function ($entry) {
            if (! $entry) {
                return null;
            }

            return FbtSiteMetaEntry::wrap($entry);
        }, $rawEntries);
    }

    public static function unwrap(array $metaEntries): array
    {
        return array_map(function (?FbtSiteMetaEntry $entry) {
            return $entry ? $entry->unwrap() : null;
        }, $metaEntries);
    }
}
