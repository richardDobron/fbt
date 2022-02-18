<?php

namespace fbt\Transform\FbtTransform\Translate;

use function fbt\invariant;
use fbt\Transform\FbtTransform\FbtConstants;
use fbt\Transform\FbtTransform\FbtUtils;

/**
 * Represents a fbt() or <fbt /> source data from a callsite and all
 * the information necessary to produce the translated payload.  It is
 * used primarily by TranslationBuilder for this process.
 */
class FbtSite
{
    /* @var mixed */
    private $_type;
    private $_hashToText;
    /* @var mixed */
    private $_tableOrHash;
    /* @var null|array */
    private $_metadata = null;
    /* @var string */
    private $_project;

    public function __construct(
        $type,
        $hashToText,
        $tableData, // source table & metadata
        $project
    ) {
        $hasTableData = is_array($tableData);
        invariant(
            $type === FbtConstants::FBT_TYPE['TEXT'] || $hasTableData,
            'TEXT types should have no table data and TABLE require it'
        );
        if ($type === FbtConstants::FBT_TYPE['TEXT']) {
            invariant(
                count(array_keys($hashToText)) === 1,
                'TEXT types should be a singleton entry'
            );
            $this->_tableOrHash = array_keys($hashToText)[0];
        }
        $this->_type = $type;
        $this->_hashToText = $hashToText;
        if ($hasTableData) {
            $this->_tableOrHash = $tableData['t'];
            $this->_metadata = FbtSiteMetadata::wrap($tableData['m']);
        }
        $this->_project = $project;
    }

    public function getHashToText()
    {
        return $this->_hashToText;
    }

    public function getMetadata()
    {
        return $this->_metadata ?? [];
    }

    public function getProject()
    {
        return $this->_project;
    }

    public function getType()
    {
        return $this->_type;
    }

    // In a type of TABLE, this looks something like:
    //
    // ["*" =>
    //   [... [ "*" => <HASH>] ] ]
    //
    // In a type of TEXT, this is simply the HASH
    public function getTableOrHash()
    {
        return $this->_tableOrHash;
    }

    // Replaces leaves in our table with corresponding hashes
    public static function _hashifyLeaves(
        $entry, // Represents a recursive descent into the table
        $textToHash // Reverse mapping of hashToText for leaf lookups
    ) {
        return is_string($entry)
            ? $textToHash[$entry]
            : FbtUtils::objMap($entry, function ($branch, $key) use ($textToHash) {
                return self::_hashifyLeaves($branch, $textToHash);
            });
    }

    /**
     * From a run of collectFbt using TextPackager.  NOTE: this is NOT
     * the output of serialize
     *
     * Relevant keys processed:
     * {
     *  hashToText: {hash: text},
     *  type: TABLE|TEXT
     *  jsfbt: {
     *    m: [levelMetadata,...]
     *    t: {...}
     *  } | text
     * }
     */
    public static function fromScan($json)
    {
        $tableData = $json['jsfbt'];
        if ($json['type'] === FbtConstants::FBT_TYPE['TABLE']) {
            $textToHash = [];
            foreach ($json['hashToText'] as $k => $text) {
                invariant(
                    empty($textToHash[$text]), // undefined
                    "Duplicate texts pointing to different hashes shouldn't be possible"
                );
                $textToHash[$text] = $k;
            }
            $tableData = [
                't' => FbtSite::_hashifyLeaves($json['jsfbt']['t'], $textToHash),
                'm' => $json['jsfbt']['m'],
            ];
        }

        $fbtSite = new FbtSite(
            $json['type'],
            $json['hashToText'],
            $tableData,
            $json['project']
        );

        return $fbtSite;
    }

    public function serialize()
    {
        $json = [
            '_t' => $this->getType(),
            'h2t' => $this->getHashToText(),
            'p' => $this->getProject(),
        ];
        if ($this->_type === FbtConstants::FBT_TYPE['TABLE']) {
            $json['_d'] = [
                't' => $this->_tableOrHash,
                'm' => FbtSiteMetadata::unwrap($this->_metadata),
            ];
        }

        return $json;
    }

    public static function deserialize($json)
    {
        return new FbtSite($json['_t'], $json['h2t'], $json['_d'], $json['p']);
    }
}
