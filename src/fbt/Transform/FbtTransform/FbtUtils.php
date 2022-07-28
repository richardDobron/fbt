<?php

namespace fbt\Transform\FbtTransform;

use fbt\Exceptions\FbtParserException;

use function fbt\invariant;

use fbt\Runtime\fbtElement;
use fbt\Runtime\Shared\IntlPunctuation;
use fbt\Util\SimpleHtmlDom\Node;

class FbtUtils
{
    public const FBT_CORE_ATTRIBUTES = [ // js~php diff
        'implicitDesc' => true,
        'implicitFbt' => true,
        'paramName' => true,
        'desc' => true,
    ];

    public static function normalizeSpaces(string $value, array $options = []): string
    {
        if (! empty($options['preserveWhitespace'])) {
            return $value;
        }

        return preg_replace("/\s+/m", " ", $value);
    }

    /**
     * Validates allowed children inside <fbt>.
     * Currently allowed:
     *   <fbt:param>, <FbtParam>
     *   <fbt:enum>,  <FbtEnum>
     *   <fbt:name>,  <FbtName>
     * And returns a name of a corresponding handler.
     * If a child is not valid, it is flagged as an Implicit Parameter and is
     * automatically wrapped with <fbt:param>
     *
     * @param $node - The node that contains the name of any parent node. For
     * example, for a JSXElement, the containing name is the openingElement's name.
     */
    public static function validateNamespacedFbtElement($moduleName, Node $node): string
    {
        $valid = false;
        $handlerName = null;

        // Actual namespaced version, e.g. <fbt:param>
        if ($node->isNamespacedElement()) {
            list($namespace, $handlerName) = explode(":", $node->tag);
            if ($namespace === $moduleName) {
                $valid =
                    $handlerName === 'enum' ||
                    $handlerName === 'param' ||
                    $handlerName === 'plural' ||
                    $handlerName === 'pronoun' ||
                    $handlerName === 'name' ||
                    $handlerName === 'same-param';
            }
        }

        if (! $valid) {
            $handlerName = 'implicitParamMarker';
        }

        if ($handlerName === 'same-param' || $handlerName === 'sameparam') {
            $handlerName = 'sameParam';
        }

        return $handlerName;
    }

    public const SHORT_BOOL_CANDIDATES = [
        'common' => 'common',
        'doNotExtract' => 'doNotExtract',
        'number' => 'number',
        'preserveWhitespace' => 'preserveWhitespace',
        'reporting' => 'reporting', // fbt diff
    ];

    private static function canBeShortBoolAttr($name): bool
    {
        return in_array($name, self::SHORT_BOOL_CANDIDATES);
    }

    /**
     * @return void
     * @throws FbtParserException
     */
    public static function setUniqueToken(Node $node, string $moduleName, string $name, array &$paramSet)
    {
        if (isset($paramSet[$name])) {
            throw self::errorAt(
                $node,
                "There's already a token called \"$name\" in this $moduleName call. " .
                "Use $moduleName.sameParam if you want to reuse the same token name or " .
                "give this token a different name"
            );
        }

        $paramSet[$name] = true;
    }

    /**
     * @param string $option
     * @param array $validOptions
     * @param string|bool|null $value
     *
     * @return string
     * @throws FbtParserException
     */
    public static function checkOption(
        string $option,
        array $validOptions,
        $value
    ): string {
        $validOptions = array_merge($validOptions, self::FBT_CORE_ATTRIBUTES);
        $validValues = $validOptions[$option] ?? null;

        if ($value === true) { // js~php diff
            $value = 'true';
        } elseif ($value === false) {
            $value = 'false';
        }

        if (! array_key_exists($option, $validOptions) || empty($validValues)) {
            throw new FbtParserException(
                "Invalid option \"$option\". " .
                "Only allowed: " . implode(', ', array_keys($validOptions)) . " "
            );
        } elseif ($validValues !== true) {
            $valueStr = $value;
            if (! isset($validValues[$valueStr])) {
                throw new FbtParserException(
                    "Invalid value, \"$valueStr\" for \"$option\". " .
                    "Only allowed: " . implode(', ', array_keys($validValues))
                );
            }
        }

        return $option;
    }

    public static function checkOptions(array $options, array $validOptions): array
    {
        foreach ($options as $name => $value) {
            self::checkOption($name, $validOptions, $value);
        }

        return $options;
    }

    public static function collectOptions($moduleName, $options, $validOptions): array
    {
        $key2value = [];
        if ($options == null) {
            return $key2value;
        }

        $options = self::checkOptions($options, $validOptions);

        foreach ($options as $name => $value) {
            // Append only default valid options excluding "extraOptions",
            // which are used only by specific runtimes.
            if (isset($validOptions[$name])) {
                $key2value[$name] = $value;
            }
        }

        return $key2value;
    }

    /**
     * Build options list form corresponding attributes.
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function getOptionsFromAttributes(Node $attributesNode, array $validOptions = [], array $ignoredAttrs = []): array
    {
        $options = [];

        foreach ($attributesNode->getAllAttributes() as $name => $value) {
            // Required attributes are passed as a separate argument in the fbt(...)
            // call, because they're required. They're not passed as options.
            // Ignored attributes are simply stripped from the function call entirely
            // and ignored.  By default, we ignore all "private" attributes with a
            // leading '__' like '__source' and '__self' as added by certain
            // babel/react plugins
            if (isset($ignoredAttrs[$name]) || strpos($name, '__') === 0) {
                continue;
            }

            if (self::canBeShortBoolAttr($name) && $value === null) {
                $value = true;
            } elseif (in_array($value, ['true', 'false'])) {
                $value = $value === 'true';
            }

            $options[self::checkOption($name, $validOptions, $value)] = $value;
        }

        return $options;
    }

    public static function errorAt(Node $node, string $msg): FbtParserException
    {
        $_node = clone $node;
        $_node->getDOM()->remove_callback();

        $errorMsg = "$msg\n---\n" . $_node->outertext() . "\n---";

        return new FbtParserException($errorMsg);
    }

    /**
     * Check that the value of the given option name is a boolean literal
     * and return its value
     *
     * @param array $options
     * @param string $name
     * @param Node|null $node
     *
     * @return bool
     * @throws FbtParserException
     */
    public static function getOptionBooleanValue(array $options, string $name, ?Node $node = null): bool
    {
        if (! isset($options[$name])) {
            return false;
        }

        $value = $options[$name];
        if (is_bool($value)) {
            return $value;
        }

        if ($node) {
            throw self::errorAt(
                $node,
                "Value for option \"$name\" must be Boolean literal 'true' or 'false'."
            );
        }

        throw new FbtParserException(
            "Value for option \"$name\" must be Boolean literal 'true' or 'false'."
        );
    }

    /**
     * @param $moduleName
     * @param $variationName
     * @param $variationInfo
     * @param Node $node
     *
     * @return int|null
     * @throws FbtParserException
     */
    public static function getVariationValue($moduleName, $variationName, $variationInfo, Node $node): ?int
    {
        // Numbers allow only `true` or expression.
        if (
            $variationName === 'number' &&
            is_bool($variationInfo)
        ) {
            if ($variationInfo !== true) {
                throw self::errorAt(
                    $node,
                    "$moduleName.param's number option should be an expression or 'true'"
                );
            }
            // For number="true" we don't pass additional value.
            return null;
        }

        return $variationInfo;
    }

    /**
     * Utility for getting the first attribute by name from a list of attributes.
     *
     * @param Node $node
     * @param string $name
     *
     * @return string|null
     * @throws FbtParserException
     */
    public static function getAttributeByNameOrThrow(Node $node, string $name): ?string
    {
        if (! isset($node->{$name})) {
            throw new FbtParserException("Unable to find attribute \"$name\".");
        }

        return $node->getAttribute($name);
    }

    /**
     * @param Node $node
     * @param string $name
     *
     * @return string|null
     */
    public static function getAttributeByName(Node $node, string $name): ?string
    {
        return $node->{$name};
    }

    /**
     * @param mixed $range
     * @return array
     *
     * @throws FbtParserException
     */
    public static function extractEnumRange($range): array
    {
        if (! is_string($range)) {
            throw new FbtParserException("fbt enum range values must be string, got " . getType($range));
        }

        $rangeArg = json_decode(html_entity_decode(html_entity_decode($range)));

        $rangeProps = [];
        if (is_array($rangeArg)) {
            foreach ($rangeArg as $value) {
                $rangeProps[$value] = $value;
            }
        } elseif (is_object($rangeArg)) {
            $rangeProps = $rangeArg;
        } else {
            throw new Exception("fbt enum range value must be array or object, got " . getType($rangeArg));
        }


        return (array)$rangeProps;
    }

    public static function objMap(array $object, callable $fn): array
    {
        $toMap = [];

        foreach ($object as $k => $value) {
            $toMap[$k] = $fn($value, $k);
        }

        return $toMap;
    }

    /**
     * Does this object have keys?
     *
     * Note: this breaks on any actual "class" object with prototype
     * members
     *
     * The micro-optimized equivalent of `count(array_keys($o)) > 0` but
     * without the throw-away array
     */
    public static function hasKeys($o): bool
    {
        foreach ($o as $k => $v) {
            return true;
        }

        return false;
    }

    /**
     * Filter whitespace-only nodes from a list of nodes.
     */
    public static function filterEmptyNodes(array $nodes): array
    {
        // js~php diff

        $firstKey = array_keys($nodes)[0] ?? null;
        $lastKey = array_keys($nodes)[count($nodes) - 1] ?? null;
        $filteredNodes = array_filter($nodes, function (Node $node, $key) use ($firstKey, $lastKey) {
            if ($node->isText() && preg_match("/^\s+$/", $node->innertext())) {
                $node->innertext = (
                    $key === $firstKey || $key === $lastKey
                    ? ''
                    : ' '
                );

                return $node->innertext;
            }

            if ($node->isElement() && ! $node->isNamespacedElement() && $node->innertext() == '') {
                // todo: this should catch in _createFbtFunctionCallNode
                invariant(false, 'text cannot be null');
            }

            return ! $node->isComment();
        }, ARRAY_FILTER_USE_BOTH);

        return array_values($filteredNodes);
    }

    /**
     * Does the token substitution fbt() but without the string lookup.
     * Used for in-place substitutions in translation mode.
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public static function substituteTokens($template, $_args)
    {
        $args = $_args;

        if (! $args) {
            return $template;
        }

        invariant(
            is_array($args),
            'The 2nd argument must be an object (not a string) for tx(%s, ...)',
            $template
        );

        // Splice in the arguments while keeping rich object ones separate.
        $objectPieces = [];
        $argNames = [];
        $stringPieces = explode("\x17", preg_replace_callback("/{([^}]+)}/", function ($matches) use ($args, &$argNames, &$objectPieces) {
            $match = $matches[0];
            $parameter = $matches[1];
            $punctuation = $matches[2] ?? '';

            $argument = $args[$parameter] ?? null;

            if ($argument && is_array($argument)) {
                $objectPieces[] = $argument;
                $argNames[] = $parameter; // End of Transmission Block sentinel marker

                return '\x17' . $punctuation;
            } elseif ($argument === null) {
                return '';
            }

            return (
                $argument . (IntlPunctuation::endsInPunct($argument) ? '' : $punctuation)
            );
        }, $template));

        if (count($stringPieces) === 1) {
            return $stringPieces[0];
        }

        // Zip together the lists of pieces.
        $pieces = [$stringPieces[0]];

        foreach ($objectPieces as $i => $piece) {
            $pieces[] = $piece;
            $pieces[] = $stringPieces[$i + 1];
        }

        return $pieces;
    }

    // js~php diff:

    /**
     * @param array|Node $nodes
     * @return array
     */
    public static function makeFbtElementArrayFromNode($nodes): array
    {
        $tree = [];

        if ($nodes instanceof Node) {
            $nodes = [$nodes];
        }

        foreach ($nodes as $node) {
            $children = self::makeFbtElementArrayFromNode($node->children());
            $tree[] = new fbtElement($node->tag, $node->innertext, $node->getAllAttributes(), $children);
        }

        return $tree;
    }
}
