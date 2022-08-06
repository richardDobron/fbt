<?php

namespace fbt\Transform\FbtTransform\Utils;

use fbt\Exceptions\FbtException;
use fbt\Transform\FbtHash;
use fbt\Transform\FbtTransform\FbtConstants;

/**
 * TextPackager massages the data to handle multiple texts in fbt payloads (like
 * enum branches) and hashes each individual text.  It stores this mapping in a
 * stripped down phrase
 */
class TextPackager
{
    /** @var string */
    private $hash;

    public function __construct(string $hash)
    {
        $this->hash = $hash;
    }

    /**
     * The hash function signature should look like:
     * [{desc: '...', texts: ['t1',...,'tN']},...]) =>
     *   [[hash1,...,hashN],...]
     *
     * @throws FbtException
     */
    public function pack(array $phrases): array
    {
        $flatTexts = array_map(function ($phrase) {
            return [
                "desc" => $phrase['desc'],
                "texts" => $this->_flattenTexts(
                    ($phrase['type'] === FbtConstants::FBT_TYPE['TABLE'])
                    ? $phrase['jsfbt']['t']
                    : $phrase['jsfbt']
                ),
            ];
        }, $phrases);


        $hashes = call_user_func_array([FbtHash::class, $this->hash], [$flatTexts]);

        foreach ($flatTexts as $phraseIdx => $flatText) {
            $hashToText = [];
            foreach ($flatText['texts'] as $textIdx => $text) {
                $hash = $hashes[$phraseIdx][$textIdx];
                if ($hash == null) {
                    throw new FbtException('Missing hash for text: ' . $text);
                }
                $hashToText[$hash] = $text;
            }

            $phrases[$phraseIdx] = array_merge(
                [
                    'hashToText' => $hashToText,
                ],
                $phrases[$phraseIdx]
            );
        }

        return $phrases;
    }

    /**
     * @param array|string $texts
     *
     * @return array|string
     */
    private function _flattenTexts($texts): array
    {
        if (is_string($texts)) {
            // return all tree leaves of a jsfbt TABLE or singleton array in the case of
            // a TEXT type
            return [$texts];
        }

        $aggregate = [];
        foreach ($texts as $text) {
            $aggregate = array_merge($aggregate, $this->_flattenTexts($text));
        }

        return $aggregate;
    }
}
