<?php

namespace fbt\Transform\FbtTransform;

use dobron\DomForge\Node;

use function fbt\checkParentTags;
use function fbt\invariant;

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
        invariant(! isset($node->context->paramName), 'You can only wrap an implicit fbt param once');

        $parent = $node;
        while ($parent = $parent->parent) {
            if ($parent->tag === $moduleName) {
                break;
            }
        }

        $node->context->paramName = trim(
            FbtUtils::normalizeSpaces(
                self::collectRawString($moduleName, $node)
            )
        );

        $fbtNode = $node->dom()->createElement(
            $moduleName,
            $node->innerHtml,
            $parent->getAttributes()
        );

        $fbtNode->context->implicitFbt = true;
        $fbtNode->context->implicitDesc = $node->context->implicitDesc;
        $fbtNode->context->parentIndex = $node->context->parentIndex;

        foreach ($node->nodes as $child) {
            $fbtNode->appendChild($child);
        }
        self::createDescAttribute($fbtNode);

        $fbtNode->parent = $node;
        $node->children = [$fbtNode];
        $node->nodes = [$fbtNode];

        return $node;
    }

    /**
     * Given a node, this function creates a JSXIdentifier with the node's
     * implicit description as the description.
     * @return void
     */
    public static function createDescAttribute(Node $node): void
    {
        $descString = 'In the phrase: "' . $node->context->implicitDesc . '"';

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
            ? FbtUtils::normalizeSpaces($node->innerHtml) : '';
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
            $string = implode('', array_map(function (Node $_child) use ($moduleName) {
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
    public static function createImplicitDescriptions(string $moduleName, Node $node): void
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
    public static function createDescriptionsWithStack(string $moduleName, Node $node, array $stack): void
    {
        $stack[] = $node;

        if ($node->nodes) {
            $filteredChildren = FbtUtils::filterEmptyNodes($node->nodes);
            foreach ($filteredChildren as $child) {
                if ($child->isElement() && FbtUtils::validateNamespacedFbtElement($moduleName, $child) === 'implicitParamMarker') {
                    $child->context->implicitDesc = self::collectTokenStringFromStack($moduleName, $stack, 0);
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
                return self::FBT_PARAM_TYPE['EXPLICIT'];
            default:
                return self::FBT_PARAM_TYPE['NULL'];
        }
    }
}
