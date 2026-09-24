<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

use dobron\DomForge\Node;
use fbt\Exceptions\FbtParserException;

use function fbt\invariant;

use fbt\Transform\FbtTransform\FbtUtils;

class FbtNodeUtil
{
    /**
     * js~php diff: equivalent of `errorAt(node, error)`, supporting fbt nodes without DOM node
     *
     * @param Node|null $node
     * @param \Throwable|string $error
     */
    public static function errorAt(?Node $node, $error): FbtParserException
    {
        if ($error instanceof FbtParserException) {
            return $error;
        }

        $message = $error instanceof \Throwable ? $error->getMessage() : $error;

        return $node !== null
            ? FbtUtils::errorAt($node, $message)
            : new FbtParserException($message);
    }

    /**
     * Returns the closest ancestor node of type: FbtElementNode | FbtImplicitParamNode
     *
     * @return FbtElementNode|FbtImplicitParamNode
     * @throws \fbt\Exceptions\FbtException
     */
    public static function getClosestElementOrImplicitParamNodeAncestor(FbtNode $startNode): FbtNode
    {
        $ret = $startNode->getFirstAncestorOfType(FbtImplicitParamNode::class)
            ?? $startNode->getFirstAncestorOfType(FbtElementNode::class);
        invariant(
            $ret !== null,
            'Unable to find closest ancestor of type FbtElementNode or FbtImplicitParamNode for node: %s',
            get_class($startNode)
        );

        return $ret;
    }

    public static function runOnNestedChildren(FbtNode $node, callable $callback): void
    {
        foreach ($node->children as $child) {
            $callback($child);
            if (count($child->children)) {
                self::runOnNestedChildren($child, $callback);
            }
        }
    }

    /**
     * @param FbtNode $fbtNode
     * @param \SplObjectStorage $phraseToIndexMap - FbtNode => phrase index
     */
    public static function toPlainFbtNodeTree(FbtNode $fbtNode, \SplObjectStorage $phraseToIndexMap): array
    {
        $ret = [
            'phraseIndex' => $phraseToIndexMap->contains($fbtNode) ? $phraseToIndexMap[$fbtNode] : null,
            'children' => array_values(array_map(function (FbtNode $child) use ($phraseToIndexMap) {
                return self::toPlainFbtNodeTree($child, $phraseToIndexMap);
            }, $fbtNode->children)),
        ] + $fbtNode->toPlainFbtNode();

        if ($ret['phraseIndex'] === null) {
            unset($ret['phraseIndex']);
        }
        if (count($ret['children']) === 0) {
            unset($ret['children']);
        }

        return $ret;
    }

    /**
     * Convert input text to a token name.
     *
     * It's using a naive way to replace curly brackets present inside the text to square brackets.
     *
     * It's good enough for now because we currently:
     *   - don't protect/encode curly brackets provided in the source text
     *   - don't prevent token names to contain curly brackets from userland
     *
     * @example `convertToTokenName('Hello {name}') === '=Hello [name]'`
     */
    public static function convertToTokenName(string $text): string
    {
        return '=' . strtr($text, ['{' => '[', '}' => ']']);
    }

    public static function tokenNameToTextPattern(string $tokenName): string
    {
        return '{' . $tokenName . '}';
    }

    /**
     * We define an FbtImplicitParamNode's outer token alias to be
     * string concatenation of '=m' + the FbtImplicitParamNode's index in its siblings array.
     *
     * @example For string <fbt> hello <a>world</a></fbt>,
     *          the outer token alias of <a>world</a> will be '=m1'.
     */
    public static function convertIndexInSiblingsArrayToOuterTokenAlias(int $index): string
    {
        return self::convertToTokenName('m' . $index);
    }

    /**
     * Collect and normalize text output from a tree of fbt nodes.
     *
     * @param FbtElementNode|FbtImplicitParamNode $instance
     * @param StringVariationArgsMap $argsMap
     * @param mixed $subject Value of the subject's gender of the sentence
     * @param bool $preserveWhitespace
     * @param callable $getChildNodeText Callback responsible for returning the text of an FbtChildNode
     *
     * @throws FbtParserException
     */
    public static function getTextFromFbtNodeTree(
        FbtNode $instance,
        StringVariationArgsMap $argsMap,
        $subject,
        bool $preserveWhitespace,
        callable $getChildNodeText
    ): string {
        try {
            if ($subject !== null) {
                $argsMap->mustHave($instance);
            }
            $texts = array_map(function (FbtNode $child) use ($getChildNodeText, $argsMap) {
                return $getChildNodeText($argsMap, $child);
            }, $instance->children);

            return FbtUtils::jsTrim(FbtUtils::normalizeSpaces(implode('', $texts), [
                'preserveWhitespace' => $preserveWhitespace,
            ]));
        } catch (\Throwable $error) {
            throw self::errorAt($instance->node, $error);
        }
    }

    public static function getChildNodeText(StringVariationArgsMap $argsMap, FbtNode $child): string
    {
        return $child instanceof FbtImplicitParamNode
            ? self::tokenNameToTextPattern($child->getTokenName($argsMap))
            : $child->getText($argsMap);
    }

    /**
     * @param FbtElementNode|FbtImplicitParamNode $instance
     */
    public static function getTokenAliasesFromFbtNodeTree(FbtNode $instance, StringVariationArgsMap $argsMap): ?array
    {
        $tokenAliases = [];
        foreach ($instance->children as $tokenIndex => $node) {
            $tokenAliases = array_merge($tokenAliases, self::getChildNodeTokenAliases($argsMap, $node, $tokenIndex));
        }

        return count($tokenAliases) ? $tokenAliases : null;
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    public static function getChildNodeTokenAliases(
        StringVariationArgsMap $argsMap,
        FbtNode $child,
        int $tokenIndex
    ): array {
        if ($child instanceof FbtImplicitParamNode) {
            $childToken = $child->getTokenName($argsMap);
            invariant(
                $childToken !== null,
                'The token of FbtImplicitParamNode %s is expected to be non-null',
                get_class($child)
            );

            return [
                $childToken => self::convertIndexInSiblingsArrayToOuterTokenAlias($tokenIndex),
            ];
        }

        return [];
    }

    public static function getChildNodeTextForDescription(
        FbtImplicitParamNode $targetFbtNode,
        StringVariationArgsMap $argsMap,
        FbtNode $child
    ): string {
        if ($child instanceof FbtImplicitParamNode) {
            return $child === $targetFbtNode || ! $child->isAncestorOf($targetFbtNode)
                ? self::tokenNameToTextPattern($child->getTokenName($argsMap))
                : $child->getTextForDescription($argsMap, $targetFbtNode);
        }

        return $child->getText($argsMap);
    }

    /**
     * This function gets the mapping from a `FbtSameParamNode`'s token name to
     * the `FbtNode` that shares the same token name.
     * This function also performs token name validation and throws if:
     *     some fbt nodes in the tree have duplicate token names
     *        OR
     *     some `FbtSameParamNode` nodes refer to non-existent token names
     *
     * For example, this function will return
     *    [
     *      "paramToken" => FbtParamNode // which represents <fbt:param name="paramToken">{a}</fbt:param>
     *      "nameToken" => FbtNameNode // which represents <fbt:name name="nameToken">{name}</fbt:name>
     *    ]
     * for the following fbt callsite
     *    <fbt>
     *      <fbt:param name="paramToken">{a}</fbt:param>
     *      ,
     *      <fbt:same-param name="paramToken" />
     *      ,
     *      <fbt:name name="nameToken">{name}</fbt:name>
     *      and finally
     *      <fbt:same-param name="nameToken" />
     *    </fbt>
     *
     * @return array<string, FbtNode>
     * @throws \fbt\Exceptions\FbtException
     */
    public static function buildFbtNodeMapForSameParam(FbtElementNode $fbtNode, StringVariationArgsMap $argsMap): array
    {
        $tokenNameToFbtNode = [];
        $tokenNameToSameParamNode = [];
        self::runOnNestedChildren($fbtNode, function (FbtNode $child) use ($fbtNode, $argsMap, &$tokenNameToFbtNode, &$tokenNameToSameParamNode) {
            if ($child instanceof FbtSameParamNode) {
                $tokenNameToSameParamNode[$child->getTokenName($argsMap)] = $child;

                return;
            } elseif (
                // FbtImplicitParamNode token names appear redundant but
                // they'll be deduplicated via the token name mangling logic
                $child instanceof FbtImplicitParamNode
            ) {
                return;
            }

            $tokenName = $child->getTokenName($argsMap);
            if ($tokenName !== null) {
                $existingFbtNode = $tokenNameToFbtNode[$tokenName] ?? null;
                invariant(
                    $existingFbtNode === null || $existingFbtNode === $child,
                    "There's already a token called `%s` in this %s call. " .
                    'Use %s::sameParam if you want to reuse the same token name or ' .
                    'give this token a different name.',
                    $tokenName,
                    $fbtNode->moduleName,
                    $fbtNode->moduleName
                );
                $tokenNameToFbtNode[$tokenName] = $child;
            }
        });

        $sameParamTokenNameToRealFbtNode = [];
        foreach (array_keys($tokenNameToSameParamNode) as $sameParamTokenName) {
            $realFbtNode = $tokenNameToFbtNode[$sameParamTokenName] ?? null;
            invariant(
                $realFbtNode !== null,
                'Expected fbt `sameParam` construct with name=`%s` to refer to a ' .
                '`name` or `param` construct using the same token name',
                $sameParamTokenName
            );
            $sameParamTokenNameToRealFbtNode[$sameParamTokenName] = $realFbtNode;
        }

        return $sameParamTokenNameToRealFbtNode;
    }
}
