<?php

namespace fbt\Transform;

use fbt\FbtConfig;
use function fbt\invariant;

class FbtHash
{
    /**
     * Takes fbt callsite data of the form:
     *
     * [{desc: '...sample description...',
     *   texts: ['string1', 'string2', ...]},
     *  ...]
     *
     * and returns the unique identifiers as calculated by the md5 of the
     * description and text:
     *
     * [["hash1", "hash2", ...]]
     */
    public static function md5($phrases): array
    {
        return array_map(function ($phrase) {
            return array_map(
                function ($text) use ($phrase) {
                    $md5 = md5($text . $phrase['desc']);

                    if (FbtConfig::get('md5_digest') === 'base64') {
                        return base64_encode(hex2bin($md5));
                    }
                    invariant(FbtConfig::get('md5_digest') === 'hex', 'invalid digest.');

                    return $md5;
                },
                $phrase['texts']
            );
        }, $phrases);
    }

    /**
     * Takes fbt callsite data where each entry in the following array
     * represents one individual fbt callsite:
     *
     * [
     *   {
     *     desc: '...sample description...',
     *     texts: ['string1', 'string2', ...]
     *   },
     *   ...
     * ]
     *
     * and returns the unique identifiers as calculated by the FB version
     * of tiger128 (old flipped-endian PHP version) of the description and
     * text:
     *
     * [
     *   ["hash1", "hash2", ...], // hashes for strings of phrase 1
     *   ...
     *   ["hash1", "hash2", ...], // hashes for strings of phrase N
     * ]
     */
    public static function tiger($phrases): array
    {
        return array_map(function ($phrase) {
            return array_map(
                function ($text) use ($phrase) {
                    return FbtTransform\fbtHash::oldTigerHash($text . ':::' . $phrase['desc'] . ':');
                },
                $phrase['texts']
            );
        }, $phrases);
    }
}
