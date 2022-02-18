<?php

namespace fbt\Transform\FbtTransform;

use fbt\fbt;
use fbt\FbtConfig;
use fbt\Runtime\Shared\FbtHooks;
use fbt\Transform\FbtTransform\Processors\HTMLFbtProcessor;
use fbt\Util\NodeParser;
use fbt\Util\SimpleHtmlDom\Node;

class FbtTransform
{
    /** @var array */
    private static $defaultOptions = [];
    /** @var bool */
    private static $init = false;

    /**
     * An array containing all collected phrases.
     * @var array
     */
    public static $phrases = [];
    /**
     * An array containing the child to parent relationships for implicit nodes.
     * @var array
     */
    public static $childToParent = [];

    /**
     * @param fbt|string $html
     *
     * @return string
     * @throws \Throwable
     * @throws \fbt\Exceptions\FbtException
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function transform($html): string
    {
        if (! self::$init) {
            self::initDefaultOptions();
            FbtCommon::init([
                'fbtCommon' => FbtConfig::get('fbtCommon'),
                'fbtCommonPath' => FbtConfig::get('fbtCommonPath'),
            ]);

            FbtHooks::onTerminating();
        }

        $dom = NodeParser::parse($html, false, true, DEFAULT_TARGET_CHARSET, false);
        $dom->set_callback([self::class, '_fbtTraverse']);

        return $dom->save();
    }

    /**
     * Transform <fbt> to fbt() calls.
     * @return void
     */
    public static function _fbtTraverse(Node $node)
    {
        $root = HTMLFbtProcessor::create($node);

        if (! $root) {
            return;
        }

        if (! $node->getAttribute('project')) {
            $node->setAttribute('project', self::$defaultOptions['project']);
        }

        if (! $node->getAttribute('author')) {
            $node->setAttribute('author', self::$defaultOptions['author']);
        }

        $node->outertext = (string)$root->convertToFbtFunctionCallNode();
    }

    /**
     * @throws \fbt\Exceptions\FbtParserException
     * @throws \Exception
     */
    private static function initDefaultOptions()
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $entrypoint = end($backtrace);

        if ($entrypoint['file'] && file_exists($entrypoint['file'])) {
            $comments = array_filter(
                token_get_all(file_get_contents($entrypoint['file'])),
                function ($entry) {
                    return $entry[0] === T_DOC_COMMENT;
                }
            );

            if ($comments) {
                $comment = array_shift($comments);
                preg_match('/@fbt ({.+?})/', $comment[1], $fbtDocblockOptions);

                if (isset($fbtDocblockOptions[1])) {
                    self::$defaultOptions = json_decode($fbtDocblockOptions[1], true);
                    foreach (self::$defaultOptions as $key => $value) {
                        FbtUtils::checkOption($key, FbtConstants::VALID_FBT_OPTIONS, $value);
                    }
                }
            }
        }

        if (empty(self::$defaultOptions['project'])) {
            self::$defaultOptions['project'] = FbtConfig::get('project');
        }

        if (empty(self::$defaultOptions['author'])) {
            self::$defaultOptions['author'] = FbtConfig::get('author');
        }
    }

    public static function addEnclosingString($childIdx, $parentIdx)
    {
        self::$childToParent[$childIdx] = $parentIdx;
    }

    public static function toArray(): array
    {
        return [
            'phrases' => array_reverse(self::$phrases, true),
            'childParentMappings' => self::$childToParent,
        ];
    }
}
