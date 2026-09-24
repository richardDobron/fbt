<?php

namespace fbt\Transform\FbtTransform\Utils;

use fbt\Transform\FbtHash;
use fbt\Transform\FbtTransform\JSFbtUtil;

/**
 * TextPackager massages the data to handle multiple texts in fbt payloads (like
 * enum branches) and hashes each individual text.  It stores this mapping in a
 * stripped down phrase
 */
class TextPackager
{
    /** @var callable(string, string): string */
    private $_hash;

    /**
     * @param callable|string $hash - hash function `(text, description) => hash`,
     *   or the name of a hash module (`md5` or `tiger`)
     */
    public function __construct($hash)
    {
        $this->_hash = is_string($hash) ? [FbtHash::class, $hash . 'Text'] : $hash;
    }

    public function pack(array $phrases): array
    {
        return array_map(function (array $phrase) {
            $hashToLeaf = [];
            JSFbtUtil::onEachLeaf($phrase, function (array $leaf) use (&$hashToLeaf) {
                $hashToLeaf[call_user_func($this->_hash, $leaf['text'], $leaf['desc'])] = [
                    'text' => $leaf['text'],
                    'desc' => $leaf['desc'],
                ];
            });

            return ['hashToLeaf' => $hashToLeaf] + $phrase;
        }, $phrases);
    }
}
