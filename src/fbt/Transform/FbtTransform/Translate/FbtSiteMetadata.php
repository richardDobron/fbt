<?php

namespace fbt\Transform\FbtTransform\Translate;

class FbtSiteMetadata
{
    /**
     * @return array<int, FbtSiteMetaEntry|null>
     * @throws \fbt\Exceptions\FbtException
     */
    public static function wrap(array $rawEntries): array
    {
        return array_map(function (?array $entry) {
            return $entry ? FbtSiteMetaEntry::wrap($entry) : null;
        }, array_values($rawEntries));
    }

    /**
     * @param array<int, FbtSiteMetaEntry|null> $metaEntries
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public static function unwrap(array $metaEntries): array
    {
        return array_map(function (?FbtSiteMetaEntry $entry) {
            return $entry === null ? null : $entry->unwrap();
        }, $metaEntries);
    }
}
