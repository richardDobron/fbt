<?php

namespace fbt\Transform\FbtTransform;

use dobron\DomForge\Node;
use fbt\Exceptions\FbtException;
use fbt\Exceptions\FbtParserException;

use function fbt\invariant;

use fbt\Transform\FbtTransform\FbtNodes\FbtNode;
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
     * @param $node - The node that contains the name of any parent node.
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
        'reporting' => 'reporting', // js~php diff: whether the result can be inlined
    ];

    private static function canBeShortBoolAttr(string $name): bool
    {
        return in_array($name, self::SHORT_BOOL_CANDIDATES);
    }

    public static function isBooleanOption(string $name): bool
    {
        return self::canBeShortBoolAttr($name) || isset(FbtConstants::VALID_PRONOUN_OPTIONS_BOOLEAN[$name]);
    }

    /**
     * js~php diff: attribute values are decoded like JSX attribute strings (e.g.
     * `desc="Tom &amp; Jerry"` is "Tom & Jerry"), which also restores the values
     * escaped by createElement().
     */
    public static function decodeAttributeValue(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @param FbtCallExpression|Node $node
     * @param string $moduleName
     * @param string $name
     * @param array<string, FbtCallExpression|Node> $paramSet
     *
     * @throws FbtParserException
     */
    public static function setUniqueToken($node, string $moduleName, string $name, array &$paramSet): void
    {
        $cachedNode = $paramSet[$name] ?? null;
        if ($cachedNode && $cachedNode !== $node) {
            throw self::errorAt(
                $node,
                "There's already a token called \"$name\" in this $moduleName call. " .
                "Use $moduleName::sameParam if you want to reuse the same token name or " .
                "give this token a different name"
            );
        }
        $paramSet[$name] = $node;
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
            throw self::errorAt(
                null,
                "Invalid option \"$option\". " .
                "Only allowed: " . implode(', ', array_keys($validOptions)) . " "
            );
        } elseif ($validValues !== true) {
            if (is_bool($value)) {
                $valueStr = $value ? 'true' : 'false';
            } elseif (is_string($value)) {
                $valueStr = $value;
            } else {
                throw self::errorAt(
                    null,
                    "Option \"$option\" has an invalid value. " .
                    'Expected a string literal but value is ' . self::describe($value)
                );
            }
            if (! isset($validValues[$valueStr])) {
                throw self::errorAt(
                    null,
                    "Option \"$option\" has an invalid value: \"$valueStr\". " .
                    "Only allowed: " . implode(', ', array_keys($validValues))
                );
            }
        }

        return $option;
    }

    /**
     * Type name of a value for error messages (like `get_debug_type()` of PHP 8)
     *
     * @param mixed $value
     */
    public static function typeOf($value): string
    {
        if ($value === null) {
            return 'null';
        } elseif (is_bool($value)) {
            return 'bool';
        } elseif (is_int($value)) {
            return 'int';
        } elseif (is_float($value)) {
            return 'float';
        } elseif (is_string($value)) {
            return 'string';
        } elseif (is_array($value)) {
            return 'array';
        } elseif (is_object($value)) {
            return get_class($value);
        }

        return gettype($value);
    }

    /**
     * @param mixed $value
     */
    public static function varDump($value): string
    {
        if (is_string($value)) {
            return $value;
        } elseif ($value === null) {
            return 'null';
        } elseif (is_scalar($value)) {
            return var_export($value, true);
        } elseif (is_object($value) && ! $value instanceof \JsonSerializable) {
            return get_class($value);
        }

        return (string)json_encode($value);
    }

    /**
     * Value and type of a value for error messages, e.g. `'abc'` (string) or `null`
     *
     * @param mixed $value
     */
    public static function describe($value): string
    {
        if ($value === null) {
            return '`null`';
        } elseif (is_object($value)) {
            return 'an instance of `' . get_class($value) . '`';
        } elseif (is_string($value)) {
            return '`' . var_export($value, true) . '` (string)';
        }

        return '`' . self::varDump($value) . '` (' . self::typeOf($value) . ')';
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
            // leading '__' (e.g. '__source')
            if (isset($ignoredAttrs[$name]) || strpos($name, '__') === 0) {
                continue;
            }

            if (self::canBeShortBoolAttr($name) && ($value === null || $value === true)) {
                // A tag attribute without value is default to boolean value true
                $value = true;
            } elseif (is_string($value)) {
                $value = self::decodeAttributeValue($value);
                // js~php diff: HTML attributes are strings, so boolean options can
                // be written as "true" / "false" (JSX uses {true} / {false})
                if (($value === 'true' || $value === 'false') && self::isBooleanOption($name)) {
                    $value = $value === 'true';
                }
            }

            $options[self::checkOption($name, $validOptions, $value)] = $value;
        }

        return $options;
    }

    /**
     * @throws FbtException
     */
    public static function assertModuleName(string $moduleName): string
    {
        if ($moduleName === FbtConstants::MODULE_NAME['FBT'] || $moduleName === FbtConstants::MODULE_NAME['FBS']) {
            return $moduleName;
        }

        throw new FbtException("Unsupported module name: \"$moduleName\"");
    }

    /**
     * @param mixed $value
     * @throws FbtException
     */
    public static function enforceString($value, ?string $valueDesc = null): string
    {
        invariant(
            is_string($value),
            '%sExpected string value instead of %s (%s)',
            $valueDesc ? $valueDesc . ' - ' : '',
            self::varDump($value),
            self::typeOf($value)
        );

        return $value;
    }

    /**
     * @param mixed $value
     * @throws FbtException
     */
    public static function enforceBoolean($value, ?string $valueDesc = null): bool
    {
        invariant(
            is_bool($value),
            '%sExpected boolean value instead of %s (%s)',
            $valueDesc ? $valueDesc . ' - ' : '',
            self::varDump($value),
            self::typeOf($value)
        );

        return $value;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed> $keys
     * @throws FbtException
     */
    public static function enforceStringEnum($value, array $keys, ?string $valueDesc = null): string
    {
        invariant(
            is_string($value) && array_key_exists($value, $keys),
            '%sExpected value to be one of [%s] but we got %s (%s) instead',
            $valueDesc ? $valueDesc . ' - ' : '',
            implode(', ', array_keys($keys)),
            self::varDump($value),
            self::typeOf($value)
        );

        return $value;
    }

    /**
     * @param mixed $value
     * @throws FbtException
     */
    public static function enforceStringOrNull($value, ?string $valueDesc = null): ?string
    {
        return $value === null ? null : self::enforceString($value, $valueDesc);
    }

    /**
     * @param mixed $value
     * @throws FbtException
     */
    public static function enforceBooleanOrNull($value, ?string $valueDesc = null): ?bool
    {
        return $value === null ? null : self::enforceBoolean($value, $valueDesc);
    }

    /**
     * @param mixed $value
     * @param array<string, mixed> $keys
     * @throws FbtException
     */
    public static function enforceStringEnumOrNull($value, array $keys, ?string $valueDesc = null): ?string
    {
        return $value === null ? null : self::enforceStringEnum($value, $keys, $valueDesc);
    }

    /**
     * Creates an `fbt::_<<methodName>>(args)` runtime function call.
     * <<methodName>> is inferred from the given fbt node.
     *
     * @param FbtNode $fbtNode
     * @param array $args Arguments of the function call
     * @param string|null $overrideMethodName Use this method name instead of the one from the fbtNode
     */
    public static function createFbtRuntimeArgCallExpression(
        FbtNode $fbtNode,
        array $args,
        ?string $overrideMethodName = null
    ): FbtCallExpression {
        return new FbtCallExpression($fbtNode->moduleName, '_' . ($overrideMethodName ?? $fbtNode::TYPE), $args);
    }

    /**
     * @param Node|FbtCallExpression|null $astNode
     * @param string|\Throwable $msgOrError
     */
    public static function errorAt($astNode, $msgOrError = ''): FbtParserException
    {
        // js~php diff: the source code of an fbt call is its DOM node (if any)
        if ($astNode instanceof FbtCallExpression) {
            $astNode = $astNode->node;
        }

        // js~php diff: the location is the line of the fbt() callsite, or the DOM node
        // (the equivalent of `astNode?.loc != null`)
        if (is_string($msgOrError)) {
            $error = new FbtParserException(self::createErrorMessageAtNode($astNode, $msgOrError));
            $error->_hasBabelNodeLocation = $astNode !== null || FbtTransform::getCallsiteLine() !== null;
        } else {
            $error = $msgOrError;
            // js~php diff: the message of an exception can't be changed, so an exception
            // without location (or of another type) is replaced by an FbtParserException
            if (! $error instanceof FbtParserException || $error->_hasBabelNodeLocation !== true) {
                $error = new FbtParserException(
                    self::createErrorMessageAtNode($astNode, $msgOrError->getMessage()),
                    0,
                    $msgOrError
                );
                $error->_hasBabelNodeLocation = $astNode !== null || FbtTransform::getCallsiteLine() !== null;
            }
        }

        return $error;
    }

    private static function createErrorMessageAtNode(?Node $astNode, string $msg = ''): string
    {
        // js~php diff: the location is the line of the fbt() callsite (columns are not available)
        $location = FbtTransform::getCallsiteLine();

        $code = null;
        if ($astNode !== null) {
            $code = clone $astNode;
            $code->dom()->removeCallback();
        }

        return ($location !== null ? "Line $location: " : '') .
            $msg .
            ($code !== null ? "\n---\n" . $code->outerHtml() . "\n---" : '');
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

        return self::getAttributeByName($node, $name);
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
        if ($value === true) {
            return '';
        }

        return is_string($value) ? self::decodeAttributeValue($value) : $value;
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

        $rangeArg = json_decode($range);

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
        // Filter whitespace and comment block
        $filteredNodes = array_filter($nodes, function (Node $node) {
            if ($node->isText() && preg_match("/^\s+$/", $node->innerHtml)) {
                return false;
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
}
