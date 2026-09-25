<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

use dobron\DomForge\Node;

use function fbt\invariant;

use fbt\Transform\FbtTransform\FbtCallExpression;
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
 */
class FbtElementNode extends FbtNode implements IFbtElementNode
{
    public const TYPE = FbtNodeType::ELEMENT;

    /** @var array<string, FbtCallExpression|Node> */
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
                    FbtUtils::varDump($extraOptionValue),
                    FbtUtils::typeOf($extraOptionValue)
                );
                $extraOptions[$optionName] = $extraOptionValue;
            }

            return [
                'author' => FbtUtils::enforceStringOrNull($rawOptions['author'] ?? null),
                'common' => FbtUtils::enforceBooleanOrNull($rawOptions['common'] ?? null) ?: false,
                'doNotExtract' => FbtUtils::enforceBooleanOrNull($rawOptions['doNotExtract'] ?? null),
                'preserveWhitespace' => FbtUtils::enforceBooleanOrNull($rawOptions['preserveWhitespace'] ?? null) ?: false,
                'project' => FbtUtils::enforceString(($rawOptions['project'] ?? null) ?: ''),
                'subject' => $rawOptions['subject'] ?? null,
                // js~php diff: whether the result can be inlined (see FbtHooks::inlineMode())
                'reporting' => FbtUtils::enforceBooleanOrNull($rawOptions['reporting'] ?? null) ?? true,
                'extraOptions' => $extraOptions,
            ];
        } catch (\Throwable $error) {
            throw FbtUtils::errorAt($this->node, $error);
        }
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
            throw FbtUtils::errorAt($this->node, $error);
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
     * Create a new class instance given a root node (the fbt() FbtCallExpression).
     * If that node is incompatible, we'll just return `null`.
     *
     * @param string $moduleName
     * @param mixed $node
     * @param array $validExtraOptions
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function fromNode(string $moduleName, $node, array $validExtraOptions = []): ?self
    {
        if (! $node instanceof FbtCallExpression) {
            return null;
        }
        $fbtElement = new self([
            'moduleName' => $moduleName,
            'node' => $node,
            'validExtraOptions' => $validExtraOptions,
        ]);
        $fbtContentsNode = $node->arguments[0] ?? null;

        if (! is_array($fbtContentsNode)) {
            throw FbtUtils::errorAt(
                $node,
                "$moduleName: expected callsite's first argument to be an array"
            );
        }

        foreach ($fbtContentsNode as $elementChild) {
            if ($elementChild === null) {
                throw FbtUtils::errorAt($node, "$moduleName: elementChild must not be nullish");
            }
            $fbtElement->appendChild(self::createChildNode($moduleName, $elementChild));
        }

        return $fbtElement;
    }

    /**
     * Create a child fbt node for a given node.
     *
     * @param string $moduleName
     * @param string|FbtCallExpression|Node $node - js~php diff: a text is a string
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public static function createChildNode(string $moduleName, $node): FbtNode
    {
        // js~php diff: fbt constructs within HTML elements aren't converted to their
        // functional form yet (JSXFbtProcessor converts all of them), e.g. <fbt:param> to fbt::param()
        if ($node instanceof Node && $node->isElement()) {
            $name = FbtUtils::validateNamespacedFbtElement($moduleName, $node);
            if (FbtNodeType::cast($name) !== null) {
                $node = new FbtCallExpression($moduleName, $name, (new GetNamespacedArgs($moduleName))->{$name}($node), $node);
            }
        }

        $fbtChildNode = null;
        $fbtChildNodeClasses = [
            FbtEnumNode::class,
            FbtNameNode::class,
            FbtParamNode::class,
            FbtPluralNode::class,
            FbtPronounNode::class,
            FbtSameParamNode::class,
            FbtTextNode::class,
        ];

        foreach ($fbtChildNodeClasses as $constructor) {
            $fbtChildNode = $constructor::fromNode($moduleName, $node);
            if ($fbtChildNode !== null) {
                break;
            }
        }

        // Try to convert to FbtImplicitParamNode as a last resort
        if ($fbtChildNode === null && $node instanceof Node && $node->isElement()) {
            // js~php diff: empty elements (e.g. <br> or <i class="icon"></i>) don't
            // contain any text to translate, so they're kept as a part of the text
            if (self::isEmptyElement($node)) {
                return FbtTextNode::fromText($moduleName, $node->outerHtml(), $node);
            }

            // js~php diff: implicit params are rich contents, which the fbs runtime doesn't accept
            if ($moduleName === FbtConstants::MODULE_NAME['FBS']) {
                throw FbtUtils::errorAt($node, FbtConstants::FBS_RICH_CONTENT_ERROR);
            }

            $fbtChildNode = FbtImplicitParamNode::fromNode($moduleName, $node);
        }

        if ($fbtChildNode !== null) {
            return $fbtChildNode;
        }

        throw FbtUtils::errorAt(
            $node instanceof Node || $node instanceof FbtCallExpression ? $node : null,
            "$moduleName: unsupported node: " . FbtUtils::typeOf($node)
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

    public function getFbtRuntimeArg(): ?FbtCallExpression
    {
        $subject = $this->options['subject'];

        return $subject === null
            ? null
            : FbtUtils::createFbtRuntimeArgCallExpression($this, [$subject], FbtConstants::VALID_PRONOUN_USAGES_KEYS['subject']);
    }

    /**
     * @see IFbtElementNode::registerToken()
     */
    public function registerToken(string $name, FbtNode $source): void
    {
        FbtUtils::setUniqueToken($source->node, $this->moduleName, $name, $this->_tokenSet);
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
