<?php

namespace fbt\Transform\FbtTransform\Translate;

/**
 * Represents a fbt() or <fbt /> source data from a callsite and all
 * the information necessary to produce the translated payload.  It is
 * used primarily by TranslationBuilder for this process.
 *
 * FbtSiteBase defines the necessary methods required by TranslationBuilder to build
 * translated payload. Implementation of these methods could vary between different
 * types of FbtSiteBase depending on the structure of source data they represent.
 */
abstract class FbtSiteBase
{
    /** @var array<string, array{text: string, desc: string}> */
    protected $hashToLeaf;
    /** @var string */
    protected $project;
    /**
     * Jsfbt table with leaves hashified.
     * @var string|array
     */
    protected $table;
    /** @var array<int, FbtSiteMetaEntryBase|null> */
    protected $metadata;

    /**
     * @param array $hashToLeaf
     * @param string|array $table
     * @param array $metadata
     * @param string $project
     */
    public function __construct(array $hashToLeaf, $table, array $metadata, string $project)
    {
        $this->hashToLeaf = $hashToLeaf;
        $this->table = $table;
        $this->metadata = $metadata;
        $this->project = $project;
    }

    public function getProject(): string
    {
        return $this->project;
    }

    public function getHashToLeaf(): array
    {
        return $this->hashToLeaf;
    }

    /**
     * For a string with variations, this looks something like:
     *
     * [
     *   "*" => [
     *     ... [ "*" => <HASH> ]
     *   ]
     * ]
     * For a string without variation, this is simply the HASH
     *
     * @return string|array
     */
    public function getTableOrHash()
    {
        return $this->table;
    }

    /**
     * @return array<int, FbtSiteMetaEntryBase|null>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
