<?php

namespace fbt\Transform\FbtTransform;

use dobron\DomForge\Node;
use fbt\Exceptions\FbtParserException;
use fbt\Runtime\Shared\substituteTokens;
use fbt\Transform\FbtTransform\FbtNodes\FbtNodeUtil;

class FbtUtils
{
    /**
     * js~php diff: equivalent of the JS `\s` character class (whitespace and line
     * terminators as defined by ECMAScript), without the non-breaking space
     */
    private const JS_WHITESPACE_WITHOUT_NBSP = '\x{9}-\x{D}\x{20}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}';

    public static function normalizeSpaces(string $value, array $options = []): string
    {
        if (! empty($options['preserveWhitespace'])) {
            return $value;
        }

        // We're willingly preserving non-breaking space characters ( )
        return preg_replace('/[' . self::JS_WHITESPACE_WITHOUT_NBSP . ']+/u', ' ', $value);
    }

    /**
     * js~php diff: equivalent of JS `String.prototype.trim()`
     */
    public static function jsTrim(string $value): string
    {
        return preg_replace('/^[' . self::JS_WHITESPACE_WITHOUT_NBSP . '\x{A0}]+|[' . self::JS_WHITESPACE_WITHOUT_NBSP . '\x{A0}]+$/uD', '', $value);
    }

    /**
     * js~php diff: equivalent of JS `String.prototype.trimRight()`
     */
    public static function jsTrimRight(string $value): string
    {
        return preg_replace('/[' . self::JS_WHITESPACE_WITHOUT_NBSP . '\x{A0}]+$/uD', '', $value);
    }

    /**
     * js~php diff: equivalent of `Object.keys()` (integer-like keys come first, in ascending order)
     *
     * @return array<int, string>
     */
    public static function jsObjectKeys(array $object): array
    {
        $indices = [];
        $others = [];
        foreach (array_keys($object) as $key) {
            if (is_int($key) && $key >= 0) {
                $indices[] = $key;
            } else {
                $others[] = (string)$key;
            }
        }
        sort($indices);

        return array_merge(array_map('strval', $indices), $others);
    }

    /**
     * Validates allowed children inside <fbt>.
     * Currently allowed:
     *   <fbt:param>
     *   <fbt:enum>
     *   <fbt:name>
     * And returns a name of a corresponding handler.
     * If a child is not valid, it is flagged as an Implicit Parameter and is
     * automatically wrapped with <fbt:param>
     *
     * @param $node - The node that contains the name of any parent node. For
     * example, for a JSXElement, the containing name is the openingElement's name.
     */
    public static function validateNamespacedFbtElement(string $moduleName, Node $node): string
    {
        $valid = false;
        $handlerName = null;

        // Actual namespaced version, e.g. <fbt:param>
        if ($node->isNamespacedElement()) {
            [$namespace, $handlerName] = explode(":", $node->tag);
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

    private static function canBeShortBoolAttr(string $name): bool
    {
        return in_array($name, self::SHORT_BOOL_CANDIDATES);
    }

    /**
     * @return void
     * @throws FbtParserException
     */
    public static function setUniqueToken(Node $node, string $moduleName, string $name, array &$paramSet): void
    {
        if (isset($paramSet[$name])) {
            throw self::errorAt(
                $node,
                "There's already a token called \"$name\" in this $moduleName call. " .
                "Use $moduleName::sameParam if you want to reuse the same token name or " .
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
        $validValues = $validOptions[$option] ?? null;

        if (! array_key_exists($option, $validOptions) || empty($validValues)) {
            throw new FbtParserException(
                "Invalid option \"$option\". " .
                "Only allowed: " . implode(', ', array_keys($validOptions)) . " "
            );
        } elseif ($validValues !== true) {
            if (is_bool($value)) { // js~php diff
                $valueStr = $value ? 'true' : 'false';
            } else {
                $valueStr = $value;
            }
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

    public static function collectOptions(string $moduleName, ?array $options, array $validOptions): array
    {
        $key2value = [];
        if ($options === null) {
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
     * Collect options from an fbt construct in functional form only.
     *
     * js~php diff: string values are normalized, except the `value` option,
     * since it's not possible to differentiate literals from runtime values
     *
     * @param string $moduleName
     * @param array|null $options - raw options of the fbt construct
     * @param array $validOptions
     * @param array $booleanOptions
     *
     * @throws FbtParserException
     */
    public static function collectOptionsFromFbtConstruct(
        string $moduleName,
        ?array $options,
        array $validOptions,
        array $booleanOptions = []
    ): array {
        $options = self::collectOptions($moduleName, $options, $validOptions);

        foreach ($options as $key => $value) {
            if (is_string($value) && $key !== 'value') {
                $options[$key] = self::normalizeSpaces($value);
            }

            if (isset($booleanOptions[$key])) {
                $options[$key] = self::getOptionBooleanValue($options, $key);
            }
        }

        return $options;
    }

    /**
     * Build options list form corresponding attributes.
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function getOptionsFromAttributes(Node $attributesNode, array $validOptions = [], array $ignoredAttrs = []): array
    {
        $options = [];

        foreach ($attributesNode->getAttributes() as $name => $value) {
            // Required attributes are passed as a separate argument in the fbt(...)
            // call, because they're required. They're not passed as options.
            // Ignored attributes are simply stripped from the function call entirely
            // and ignored.  By default, we ignore all "private" attributes with a
            // leading '__' like '__source' and '__self' as added by certain
            // babel/react plugins
            if (isset($ignoredAttrs[$name]) || strpos($name, '__') === 0) {
                continue;
            }

            if (self::canBeShortBoolAttr($name) && ($value === null || $value === true)) {
                // A tag attribute without value is default to boolean value true
                $value = true;
            } elseif ($value === 'true' || $value === 'false') {
                $value = $value === 'true';
            }

            $options[self::checkOption($name, $validOptions, $value)] = $value;
        }

        return $options;
    }

    public static function errorAt(Node $node, string $msg): FbtParserException
    {
        $_node = clone $node;
        $_node->dom()->removeCallback();

        $errorMsg = "$msg\n---\n" . $_node->outerHtml() . "\n---";

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
     * @param string $moduleName
     * @param string $variationName
     * @param $variationInfo
     * @param Node $node
     *
     * @return int|float|string|null
     * @throws FbtParserException
     */
    public static function getVariationValue(string $moduleName, string $variationName, $variationInfo, Node $node)
    {
        // Numbers allow only `true` or expression.
        if (
            $variationName === 'number' &&
            is_bool($variationInfo)
        ) {
            if ($variationInfo !== true) {
                throw self::errorAt(
                    $node,
                    "$moduleName::param's number option should be HTML element or 'true'"
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
        $value = $node->getAttribute($name);

        // An attribute without value
        return $value === true ? '' : $value;
    }

    /**
     * @param mixed $range
     * @return array
     *
     * @throws FbtParserException
     * @throws \Exception
     */
    public static function extractEnumRange(?string $range): array
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
            throw new \Exception("fbt enum range value must be array or object, got " . getType($rangeArg));
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
    public static function hasKeys(array $o): bool
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

        $filteredNodes = array_filter($nodes, function (Node $node) {
            if ($node->isText() && preg_match("/^\s+$/", $node->innerHtml)) {
                return $node->innerHtml;
            }

            return ! $node->isComment();
        });

        return array_values($filteredNodes);
    }

    /**
     * Clear token names in translations and runtime call texts need to be replaced
     * by their aliases in order for the runtime logic to work.
     */
    public static function replaceClearTokensWithTokenAliases(string $textOrTranslation, ?array $tokenAliases): string
    {
        if ($tokenAliases === null) {
            return $textOrTranslation;
        }

        $mangledText = $textOrTranslation;
        foreach ($tokenAliases as $clearToken => $alias) {
            $clearTokenName = FbtNodeUtil::tokenNameToTextPattern((string)$clearToken);
            $mangledTokenName = FbtNodeUtil::tokenNameToTextPattern($alias);
            // Since a string is not allowed to have implicit params with duplicated
            // token names, replacing the first and therefore the only occurence of
            // `clearTokenName` is sufficient.
            $position = strpos($mangledText, $clearTokenName);
            if ($position !== false) {
                $mangledText = substr_replace($mangledText, $mangledTokenName, $position, strlen($clearTokenName));
            }
        }

        return $mangledText;
    }

    /**
     * Does the token substitution fbt() but without the string lookup.
     * Used for in-place substitutions in translation mode.
     *
     * @deprecated Use \fbt\Runtime\Shared\substituteTokens::substitute()
     *
     * @return string|array
     * @throws \fbt\Exceptions\FbtException
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     */
    public static function substituteTokens(string $template, array $args)
    {
        return substituteTokens::substitute($template, $args);
    }
}
