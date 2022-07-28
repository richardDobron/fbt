<?php

namespace fbt\Transform\FbtTransform;

use function fbt\checkParentTags;

use fbt\Util\SimpleHtmlDom\Node;

class FbtAutoWrap
{
    public const FBT_PARAM_TYPE = [
        'IMPLICIT' => 'implicit',
        'EXPLICIT' => 'explicit',
        'NULL' => 'null',
    ];

    /**
     * Given a node that is a child of an <fbt> node and the phrase that the node
     * is within, the implicit node becomes the child of a new <fbt> node.
     *
     * WARNING: this method has side-effects because it alters the given `node` object
     * You shouldn't try to run this multiple times on the same `node`.
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function wrapImplicitFBTParam(string $moduleName, Node $node): Node
    {
        // js~php diff:
        $node->implicitDesc = $node->getAttribute('implicitDesc');
        $node->implicitFbt = 'true';
        $node->paramName = trim(FbtUtils::normalizeSpaces(self::collectRawString($moduleName, $node)));
        self::createDescAttribute($node);

        return $node;
        /*
         * $fbtNode = clone $node;
         * $fbtNode->attr = [
         *     'implicitDesc' => $node->getAttribute('implicitDesc'),
         *     'implicitFbt' => 'true',
         *     'paramName' => trim(FbtUtils::normalizeSpaces(self::collectRawString($moduleName, $node))),
         * ];
         * self::createDescAttribute($fbtNode);
         * $fbtNode->tag = $moduleName;
         * $fbtNode->children = [$node];
         * return $fbtNode;
         */
    }

    /**
     * Given a node, this function creates a JSXIdentifier with the node's
     * implicit description as the description.
     * @return void
     */
    public static function createDescAttribute(Node $node)
    {
        $descString = 'In the phrase: "' . $node->getAttribute('implicitDesc') . '"';

        $node->setAttribute('desc', $descString);
    }

    /**
     * Returns either the string contained with a JSXText node.
     */
    public static function getLeafNodeString(Node $node): string
    {
        $excludedTags = ['fbt:name', 'fbt:param'];

        return $node->isText()
            // js~php diff:
            && ! in_array($node->tag, $excludedTags)
            && ! checkParentTags($node, $excludedTags)
            ? FbtUtils::normalizeSpaces($node->innertext()) : '';
    }

    /**
     * Collects the raw strings below a given node. Explicit fbt param nodes
     * amend their 'name' attribute wrapped with [ ] only if they are the
     * child of the base node.
     * @param $child - False initially, true when the function
     * recursively calls itself with children nodes so only explicit <fbt:param>
     * children are wrapped and not the base node.
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function collectRawString($moduleName, Node $node, bool $child = false): string
    {
        if (! $node->nodes) {
            return self::getLeafNodeString($node);
        } elseif (self::getParamType($moduleName, $node) === self::FBT_PARAM_TYPE['EXPLICIT'] && $child) {
            return '[' . self::getExplicitParamName($node) . ']';
        } else {
            $filteredChildren = FbtUtils::filterEmptyNodes($node->nodes);
            $string = implode('', array_map(function ($_child) use ($moduleName) {
                return self::collectRawString($moduleName, $_child, true);
            }, $filteredChildren));

            return FbtUtils::normalizeSpaces(trim($string));
        }
    }

    /**
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function getExplicitParamName(Node $node): string
    {
        return FbtUtils::getAttributeByNameOrThrow($node, 'name');
    }

    /**
     * Given a parent <fbt> node, calls createDescriptionsWithStack with an
     * empty stack to be filled
     *
     * @return void
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function createImplicitDescriptions(string $moduleName, Node $node)
    {
        self::createDescriptionsWithStack($moduleName, $node, []);
    }

    /**
     * Creates the description for all children nodes that are implicitly
     * <fbt:param> nodes by creating the queue that is the path from the parent
     * fbt node to each node.
     *
     * @return void
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function createDescriptionsWithStack(string $moduleName, Node $node, array $stack)
    {
        $stack[] = $node;

        if ($node->children()) {
            $filteredChildren = FbtUtils::filterEmptyNodes($node->nodes);
            foreach ($filteredChildren as $child) {
                if ($child->isElement() && FbtUtils::validateNamespacedFbtElement($moduleName, $node) === 'implicitParamMarker') {
                    $child->setAttribute('implicitDesc', self::collectTokenStringFromStack($moduleName, $stack, 0));
                }

                self::createDescriptionsWithStack($moduleName, $child, $stack);
            }
        }

        array_pop($stack);
    }

    /**
     * Collects the token string from the stack by tokenizing the children of the
     * target implicit param, as well as other implicit or explicit <fbt:param>
     * nodes that do not contain the current implicit node.
     * The stack looks like:
     * [topLevelNode, ancestor1, ..., immediateParent, targetNode]
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function collectTokenStringFromStack(string $moduleName, array $nodeStack, int $index): string
    {
        if ($index >= count($nodeStack)) {
            return '';
        }

        $tokenString = '';
        $currentNode = $nodeStack[$index];
        $nextNode = $nodeStack[$index + 1] ?? null;
        $filteredChildren = FbtUtils::filterEmptyNodes($currentNode->nodes);

        foreach ($filteredChildren as $child) {
            if ($child === $nextNode) {
                // If node is on our ancestor path, descend recursively to
                // construct the string
                $tokenString .= self::collectTokenStringFromStack($moduleName, $nodeStack, $index + 1);
            } else {
                $suffix = self::collectRawString($moduleName, $child);

                if ($child === $currentNode || self::isImplicitOrExplicitParam($moduleName, $child)) {
                    $suffix = self::tokenizeString($suffix);
                }

                $tokenString .= $suffix;
            }
        }

        return trim($tokenString);
    }

    /**
     * Given a string, returns the same string wrapped with a token marker.
     */
    public static function tokenizeString(string $s): string
    {
        return '{=' . $s . '}';
    }

    public static function isImplicitOrExplicitParam(string $moduleName, Node $node): bool
    {
        return self::getParamType($moduleName, $node) !== self::FBT_PARAM_TYPE['NULL'];
    }

    /**
     * Returns if the node is implicitly or explicitly a <fbt:param>
     */
    public static function getParamType(string $moduleName, Node $node): string
    {
        if (! $node->isElement()) {
            return self::FBT_PARAM_TYPE['NULL'];
        }

        $nodeFBTElementType = FbtUtils::validateNamespacedFbtElement($moduleName, $node);

        switch ($nodeFBTElementType) {
            case 'implicitParamMarker':
                return self::FBT_PARAM_TYPE['IMPLICIT'];
            case 'param':
            case 'FbtParam':
                return self::FBT_PARAM_TYPE['EXPLICIT'];
            default:
                return self::FBT_PARAM_TYPE['NULL'];
        }
    }
}
