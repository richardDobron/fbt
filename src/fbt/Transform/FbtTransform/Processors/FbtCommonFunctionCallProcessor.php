<?php

namespace fbt\Transform\FbtTransform\Processors;

use fbt\Exceptions\FbtParserException;
use fbt\Transform\FbtTransform\FbtCallExpression;
use fbt\Transform\FbtTransform\FbtCommon;
use fbt\Transform\FbtTransform\FbtConstants;
use fbt\Transform\FbtTransform\FbtUtils;

/**
 * This class provides utility methods to process the fbt common function call.
 * I.e. `fbt::c(...)`
 *
 * js~php diff: a callsite with the `common` option and no description, e.g.
 * `fbs('Accept', ['common' => true])`, is a common function call too.
 */
class FbtCommonFunctionCallProcessor
{
    /** @var string */
    private $moduleName;
    /** @var FbtCallExpression */
    private $node;

    public function __construct(FbtCallExpression $node)
    {
        $this->moduleName = $node->moduleName;
        $this->node = $node;
    }

    public static function create(FbtCallExpression $node): ?self
    {
        return $node->name === 'c' ? new self($node) : null;
    }

    /**
     * Converts an Fbt common call of the form `fbt::c(text)` to the basic form `fbt(text, desc)`
     *
     * @throws FbtParserException
     */
    public function convertToNormalCall(): FbtCallExpression
    {
        $moduleName = $this->moduleName;
        [$contents, , $options] = $this->node->arguments;

        $text = implode('', array_map(function ($part) {
            if (! is_string($part)) {
                throw new FbtParserException(
                    'Expected a StringLiteral but found `' . $part->moduleName . '::' . $part->name . '()` instead'
                );
            }

            return $part;
        }, $contents));
        $text = FbtUtils::jsTrim(FbtUtils::normalizeSpaces($text));

        $desc = FbtCommon::getDesc($text);
        if ($desc === null || $desc === '') {
            throw new FbtParserException(FbtCommon::getUnknownCommonStringErrorMessage($moduleName, $text));
        }

        return new FbtCallExpression($moduleName, null, [
            [$text],
            $desc,
            [FbtConstants::COMMON_OPTION => true] + $options,
        ]);
    }
}
