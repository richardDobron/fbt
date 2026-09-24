<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

use dobron\DomForge\Node;

use function fbt\invariant;

use fbt\Transform\FbtTransform\FbtConstants;
use fbt\Transform\FbtTransform\FbtUtils;
use fbt\Transform\FbtTransform\Translate\IntlVariations;
use fbt\Transform\FbtTransform\Utils\GetNamespacedArgs;

/**
 * Represents the main fbt() or <fbt> construct.
 * Every nested fbt construct will be reachable from the `children` property.
 *
 * E.g. When we have an fbt callsite like this:
 *
 *     <fbt desc="description">
 *       Hello
 *       <strong>
 *         World!
 *       </strong>
 *     </fbt>
 *
 * We'll represent it like this:
 *
 * FbtElementNode                    // <fbt>
 *   |
 *   *- FbtTextNode                  // 'Hello'
 *   *- FbtImplicitParamNode         // <strong/>
 *        |
 *        *- FbtTextNode             // 'World!'
 *
 * js~php diff: the "function call arguments" of this node are:
 *   [contents (list of strings or DOM nodes), description, options]
 */
class FbtElementNode extends FbtNode implements IFbtElementNode
{
    public const TYPE = FbtNodeType::ELEMENT;

    private const CONSTRUCTS = [
        'enum' => FbtEnumNode::class,
        'name' => FbtNameNode::class,
        'param' => FbtParamNode::class,
        'plural' => FbtPluralNode::class,
        'pronoun' => FbtPronounNode::class,
        'sameParam' => FbtSameParamNode::class,
    ];

    /** @var array<string, FbtNode> */
    public $_tokenSet = [];

    public function getOptions(array $validExtraOptions = []): ?array
    {
        $allValidOptions = array_merge($validExtraOptions, FbtConstants::VALID_FBT_OPTIONS);

        try {
            $rawOptions = FbtUtils::collectOptionsFromFbtConstruct(
                $this->moduleName,
                ($this->getCallNodeArguments() ?? [])[2] ?? null,
                $allValidOptions,
                FbtConstants::FBT_BOOLEAN_OPTIONS
            );

            // Build extra options
            $extraOptions = [];
            foreach ($rawOptions as $optionName => $extraOptionValue) {
                if (! isset($validExtraOptions[$optionName])) {
                    continue;
                }
                invariant(
                    is_string($extraOptionValue),
                    'Expected extra option values to be strings but got `%s` (%s)',
                    is_scalar($extraOptionValue) ? var_export($extraOptionValue, true) : '',
                    gettype($extraOptionValue)
                );
                $extraOptions[$optionName] = $extraOptionValue;
            }

            return [
                'author' => self::enforceStringOrNull($rawOptions['author'] ?? null),
                'common' => self::enforceBooleanOrNull($rawOptions['common'] ?? null) ?? false,
                'doNotExtract' => self::enforceBooleanOrNull($rawOptions['doNotExtract'] ?? null),
                'preserveWhitespace' => self::enforceBooleanOrNull($rawOptions['preserveWhitespace'] ?? null) ?? false,
                'project' => (string)(($rawOptions['project'] ?? '') ?: ''),
                'subject' => $rawOptions['subject'] ?? null,
                // js~php diff: whether the result can be inlined (see FbtHooks::inlineMode())
                'reporting' => self::enforceBooleanOrNull($rawOptions['reporting'] ?? null) ?? true,
                'extraOptions' => $extraOptions,
            ];
        } catch (\Throwable $error) {
            throw FbtNodeUtil::errorAt($this->node, $error);
        }
    }

    /**
     * @param mixed $value
     *
     * @throws \fbt\Exceptions\FbtException
     */
    private static function enforceStringOrNull($value): ?string
    {
        invariant(
            $value === null || is_string($value),
            'Expected string value instead of %s',
            gettype($value)
        );

        return $value;
    }

    /**
     * @param mixed $value
     *
     * @throws \fbt\Exceptions\FbtException
     */
    private static function enforceBooleanOrNull($value): ?bool
    {
        invariant(
            $value === null || is_bool($value),
            'Expected boolean value instead of %s',
            is_scalar($value) ? var_export($value, true) : gettype($value)
        );

        return $value;
    }

    /**
     * js~php diff: equivalent of `getExtraOptionsNode()`
     */
    public function getExtraOptions(): ?array
    {
        return $this->options['extraOptions'] ?: null;
    }

    /**
     * @param FbtElementNode|FbtImplicitParamNode $instance
     * @param mixed $subject
     *
     * @return StringVariationArg[]
     */
    public static function getArgsForStringVariationCalcForFbtElement(FbtNode $instance, $subject): array
    {
        $args = $subject !== null
            ? [new GenderStringVariationArg($instance, $subject, [IntlVariations::GENDER_ANY])]
            : [];

        foreach ($instance->children as $child) {
            $args = array_merge($args, $child->getArgsForStringVariationCalc());
        }

        return $args;
    }

    public function getArgsForStringVariationCalc(): array
    {
        return self::getArgsForStringVariationCalcForFbtElement($this, $this->options['subject']);
    }

    /**
     * Run some sanity checks before producing text
     *
     * @param FbtElementNode|FbtImplicitParamNode $instance
     *
     * @throws \fbt\Exceptions\FbtParserException if some fbt nodes in the tree have duplicate token names
     */
    public static function beforeGetTextSanityCheck(IFbtElementNode $instance, StringVariationArgsMap $argsMap): void
    {
        foreach ($instance->children as $child) {
            $tokenName = $child->getTokenName($argsMap);
            // FbtSameParamNode token names are allowed to be redundant by design
            if ($tokenName !== null && ! ($child instanceof FbtSameParamNode)) {
                $instance->registerToken($tokenName, $child);
            }
        }
    }

    public function getText(StringVariationArgsMap $argsMap): string
    {
        try {
            self::beforeGetTextSanityCheck($this, $argsMap);

            return FbtNodeUtil::getTextFromFbtNodeTree(
                $this,
                $argsMap,
                $this->options['subject'],
                $this->options['preserveWhitespace'],
                [FbtNodeUtil::class, 'getChildNodeText']
            );
        } catch (\Throwable $error) {
            throw FbtNodeUtil::errorAt($this->node, $error);
        }
    }

    public function getTextForDescription(StringVariationArgsMap $argsMap, FbtImplicitParamNode $targetFbtNode): string
    {
        return FbtNodeUtil::getTextFromFbtNodeTree(
            $this,
            $argsMap,
            $this->options['subject'],
            $this->options['preserveWhitespace'],
            function (StringVariationArgsMap $argsMap, FbtNode $child) use ($targetFbtNode) {
                return FbtNodeUtil::getChildNodeTextForDescription($targetFbtNode, $argsMap, $child);
            }
        );
    }

    /**
     * @see IFbtElementNode::getDescription()
     */
    public function getDescription(StringVariationArgsMap $argsMap): string
    {
        $description = ($this->getCallNodeArguments() ?? [])[1] ?? null;
        invariant(
            $description !== null,
            'fbt description argument cannot be found'
        );

        return FbtUtils::jsTrim(FbtUtils::normalizeSpaces(
            $description,
            ['preserveWhitespace' => (bool)$this->options['preserveWhitespace']]
        ));
    }

    public function getTokenAliases(StringVariationArgsMap $argsMap): ?array
    {
        return FbtNodeUtil::getTokenAliasesFromFbtNodeTree($this, $argsMap);
    }

    /**
     * Create a new class instance given the <fbt> DOM node and the arguments of the
     * fbt "function call".
     *
     * @param string $moduleName
     * @param Node|null $node
     * @param array $callArgs - [contents (list of strings or DOM nodes), description, options]
     * @param array $validExtraOptions
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function fromNode(
        string $moduleName,
        ?Node $node,
        array $callArgs,
        array $validExtraOptions = []
    ): self {
        $fbtElement = new self([
            'moduleName' => $moduleName,
            'node' => $node,
            'callArgs' => $callArgs,
            'validExtraOptions' => $validExtraOptions,
        ]);

        $fbtContents = $callArgs[0] ?? null;
        if (! is_array($fbtContents)) {
            throw FbtNodeUtil::errorAt($node, "$moduleName: expected callsite's first argument to be an array");
        }

        foreach ($fbtContents as $elementChild) {
            if ($elementChild === null) {
                throw FbtNodeUtil::errorAt($node, "$moduleName: elementChild must not be nullish");
            }
            $fbtElement->appendChild(self::createChildNode($moduleName, $elementChild));
        }

        return $fbtElement;
    }

    /**
     * Create a child fbt node for a given text or DOM node.
     *
     * @param string $moduleName
     * @param string|Node $node
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function createChildNode(string $moduleName, $node): FbtNode
    {
        if (is_string($node)) {
            return FbtTextNode::fromText($moduleName, $node);
        }

        if ($node instanceof Node) {
            if ($node->isText()) {
                return FbtTextNode::fromText($moduleName, $node->innerHtml(), $node);
            }

            if ($node->isElement()) {
                $name = FbtUtils::validateNamespacedFbtElement($moduleName, $node);
                if (isset(self::CONSTRUCTS[$name])) {
                    $args = (new GetNamespacedArgs($moduleName))->{$name}($node);

                    return call_user_func([self::CONSTRUCTS[$name], 'fromNode'], $moduleName, $node, $args);
                }

                // js~php diff: empty elements (e.g. <br> or <i class="icon"></i>) don't
                // contain any text to translate, so they're kept as a part of the text
                if (self::isEmptyElement($node)) {
                    return FbtTextNode::fromText($moduleName, $node->outerHtml(), $node);
                }

                // Try to convert to FbtImplicitParamNode as a last resort
                return FbtImplicitParamNode::fromNode($moduleName, $node);
            }
        }

        throw FbtNodeUtil::errorAt(
            $node instanceof Node ? $node : null,
            "$moduleName: unsupported node: " . ($node instanceof Node ? $node->tag : gettype($node))
        );
    }

    /**
     * Whether the element doesn't contain anything but whitespace (or comments)
     */
    private static function isEmptyElement(Node $node): bool
    {
        foreach ($node->nodes as $child) {
            if ($child->isElement() || ($child->isText() && FbtUtils::jsTrim($child->innerHtml()) !== '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return FbtImplicitParamNode[]
     */
    public function getImplicitParamNodes(): array
    {
        $ret = [];
        FbtNodeUtil::runOnNestedChildren($this, function (FbtNode $child) use (&$ret) {
            if ($child instanceof FbtImplicitParamNode) {
                $ret[] = $child;
            }
        });

        return $ret;
    }

    public function getFbtRuntimeArg(): ?array
    {
        $subject = $this->options['subject'];

        return $subject === null
            ? null
            : $this->createFbtRuntimeArgCallExpression([$subject], 'subject');
    }

    /**
     * @see IFbtElementNode::registerToken()
     */
    public function registerToken(string $name, FbtNode $source): void
    {
        FbtElementNode::setUniqueToken($source, $this->moduleName, $name, $this->_tokenSet);
    }

    /**
     * @param FbtNode $source
     * @param string $moduleName
     * @param string $name
     * @param array<string, FbtNode> $paramSet
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function setUniqueToken(FbtNode $source, string $moduleName, string $name, array &$paramSet): void
    {
        $cachedNode = $paramSet[$name] ?? null;
        if ($cachedNode !== null && $cachedNode !== $source) {
            throw FbtNodeUtil::errorAt(
                $source->node,
                "There's already a token called \"$name\" in this $moduleName call. " .
                "Use $moduleName::sameParam if you want to reuse the same token name or " .
                "give this token a different name"
            );
        }
        $paramSet[$name] = $source;
    }

    /**
     * @param StringVariationArgsMap[] $argsMapList
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public function assertNoOverallTokenNameCollision(array $argsMapList): void
    {
        foreach ($argsMapList as $argsMap) {
            FbtNodeUtil::buildFbtNodeMapForSameParam($this, $argsMap);
        }
    }

    public function __toJSONForTestsOnly(): array
    {
        return parent::__toJSONForTestsOnly() + [
            '_tokenSet' => array_map('get_class', $this->_tokenSet),
        ];
    }
}
