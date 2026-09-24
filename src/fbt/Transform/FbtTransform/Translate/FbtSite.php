<?php

namespace fbt\Transform\FbtTransform\Translate;

use function fbt\invariant;

use fbt\Transform\FbtTransform\JSFbtUtil;

/**
 * Represents an <fbt>'s data source in the format of `SourceDataJSON`.
 *
 * E.g
 * [
 *  'hashToLeaf' => [
 *    hash => ['text' => '', 'desc' => ''],
 *    ...
 *  ],
 *  'jsfbt' => [
 *    't' => [
 *      '*' => [
 *        'text' => '',
 *        'desc' => '',
 *        'tokenAliases' => [...]
 *      ],
 *      ....
 *    ],
 *    'm' => [levelMetadata,...],
 *  ]
 * ]
 */
class FbtSite extends FbtSiteBase
{
    /** @var array<string, array|null> */
    private $_hashToTokenAliases;

    /**
     * @param array $hashToTextAndDesc
     * @param array{m: array, t: string|array} $tableData
     * @param string $project
     * @param array $hashToTokenAliases
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public function __construct(array $hashToTextAndDesc, array $tableData, string $project, array $hashToTokenAliases)
    {
        parent::__construct(
            $hashToTextAndDesc,
            $tableData['t'],
            FbtSiteMetadata::wrap($tableData['m']),
            $project
        );
        $this->_hashToTokenAliases = $hashToTokenAliases;
    }

    public function getHashToTokenAliases(): array
    {
        return $this->_hashToTokenAliases;
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    public static function fromScan(array $json): FbtSite
    {
        $textAndDescToHash = [];
        $hashToLeaf = $json['hashToLeaf'] ?? null;
        $jsfbt = $json['jsfbt'] ?? null;
        invariant($hashToLeaf !== null, 'Expected hashToLeaf to be defined');
        invariant($jsfbt !== null, 'Expect a non-void jsfbt table');

        foreach ($hashToLeaf as $hash => $leaf) {
            $textAndDesc = self::_serializeTextAndDesc($leaf['text'], $leaf['desc']);
            invariant(
                ! isset($textAndDescToHash[$textAndDesc]),
                "Duplicate text+desc pairs pointing to different hashes shouldn't be possible"
            );
            $textAndDescToHash[$textAndDesc] = (string)$hash;
        }

        $tableData = [
            't' => self::_hashifyLeaves($jsfbt['t'], $textAndDescToHash),
            'm' => $jsfbt['m'],
        ];

        $hashToTokenAliases = [];
        JSFbtUtil::onEachLeaf(['jsfbt' => $jsfbt], function (array $leaf) use ($textAndDescToHash, &$hashToTokenAliases) {
            $hash = $textAndDescToHash[self::_serializeTextAndDesc($leaf['text'], $leaf['desc'])];
            if (isset($leaf['tokenAliases'])) {
                $hashToTokenAliases[$hash] = $leaf['tokenAliases'];
            }
        });

        return new FbtSite($hashToLeaf, $tableData, $json['project'] ?? '', $hashToTokenAliases);
    }

    /**
     * Replaces leaves in our table with corresponding hashes
     *
     * @param array $entry Represents a recursive descent into the table
     * @param array $textAndDescToHash Reverse mapping of hashToLeaf for leaf lookups
     *
     * @return string|array
     */
    public static function _hashifyLeaves(array $entry, array $textAndDescToHash)
    {
        $leaf = JSFbtUtil::coerceToTableJSFBTTreeLeaf($entry);
        if ($leaf !== null) {
            return $textAndDescToHash[self::_serializeTextAndDesc($leaf['text'], $leaf['desc'])];
        }

        $table = [];
        foreach ($entry as $key => $branch) {
            $table[$key] = self::_hashifyLeaves($branch, $textAndDescToHash);
        }

        return $table;
    }

    /**
     * Strings with different hashes might have the same text, so we need to use
     * description to uniquely identify a string.
     * For example, in
     *  <fbt>
     *   <fbt:pronoun gender="..." type="subject" human="true" />
     *   has shared <a href="...">a photo</a>.
     *  </fbt>
     * `<a href="...">a photo</a>` generates multiple strings with the same text:
     * {text: 'a photo', desc: 'In the phrase: She has shared {a photo}.'}
     * {text: 'a photo', desc: 'In the phrase: He has shared {a photo}.'}
     * ....
     */
    public static function _serializeTextAndDesc(string $text, string $desc): string
    {
        return json_encode(['text' => $text, 'desc' => $desc], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
