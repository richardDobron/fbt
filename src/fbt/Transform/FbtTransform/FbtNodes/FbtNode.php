<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

use dobron\DomForge\Node;
use fbt\Runtime\Shared\fbs;
use fbt\Runtime\Shared\fbt;
use fbt\Transform\FbtTransform\FbtConstants;
use fbt\Transform\FbtTransform\FbtNodeChecker;

/**
 * Base class that represents an fbt construct like <fbt>, <fbt:param>, etc...
 *
 * While DOM nodes are considered "low-level" representations of the source code,
 * FbtNode is a high-level abstraction of the fbt API syntax.
 *
 * See `FbtElementNode` for more info on how this class will be used.
 *
 * We'll usually not use this class directly, favoring specialized child classes instead.
 */
abstract class FbtNode
{
    public const TYPE = null;

    /** @var string */
    public $moduleName;
    /** @var FbtNode[] */
    public $children = [];
    /**
     * Reference to the DOM node that this fbt node represents (if any)
     * @var Node|null
     */
    public $node;
    /** @var FbtNodeChecker */
    public $nodeChecker;
    /** @var FbtNode|null */
    public $parent = null;
    /**
     * Standardized "options" of the current fbt construct.
     *
     * I.e. the attributes on `<fbt:construct {...options}>` or
     * the `options` argument from `fbt::construct(..., options)`
     * @var array|null
     */
    public $options;
    /**
     * js~php diff: arguments of the fbt construct "function call", which were
     * extracted from the DOM node (see GetNamespacedArgs), or null.
     * @var array|null
     */
    protected $callArgs;

    /**
     * @param array{
     *   moduleName: string,
     *   node?: Node|null,
     *   callArgs?: array|null,
     *   children?: FbtNode[]|null,
     *   parent?: FbtNode|null,
     *   validExtraOptions?: array,
     * } $params
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public function __construct(array $params)
    {
        $this->moduleName = $params['moduleName'];
        $this->node = $params['node'] ?? null;
        $this->callArgs = $params['callArgs'] ?? null;
        if (isset($params['parent'])) {
            $this->parent = $params['parent'];
        }
        $this->children = $params['children'] ?? [];
        $this->nodeChecker = new FbtNodeChecker($this->moduleName);
        $this->options = $this->getOptions($params['validExtraOptions'] ?? []);
        $this->initCheck();
    }

    /**
     * Gather and standardize the valid "options" of the fbt construct.
     * @see FbtNode::$options
     *
     * @param array $validExtraOptions
     *    Options allowed in addition to the standard options of this construct.
     *    This is only used by FbtElementNode at the moment.
     *
     * @return array|null
     */
    abstract public function getOptions(array $validExtraOptions = []): ?array;

    /**
     * Run integrity checks to ensure this fbt construct is in a valid state
     * These checks are non-exhaustive. Some new exceptions may arise later on.
     */
    public function initCheck(): void
    {
    }

    /**
     * @return static
     */
    public function setParent(?FbtNode $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return static
     */
    public function appendChild(?FbtNode $child): self
    {
        if ($child !== null) {
            $this->children[] = $child;
            $child->setParent($this);
        }

        return $this;
    }

    /**
     * Get the list of string variation arguments (SVArgument) for this node and all its children.
     * Note that the node tree is explored using the "postorder traversal" algorithm
     * (I.e. left, right, root)
     *
     * @return StringVariationArg[]
     */
    abstract public function getArgsForStringVariationCalc(): array;

    abstract public function getText(StringVariationArgsMap $argsMap): string;

    public function getTokenAliases(StringVariationArgsMap $argsMap): ?array
    {
        return null;
    }

    public function getTokenName(StringVariationArgsMap $argsMap): ?string
    {
        return null;
    }

    /**
     * js~php diff: identity of the string variation arguments of this construct
     * (see FbtArgumentBase::getArgCode())
     */
    public function getVariationKey(): ?string
    {
        $key = $this->options['key'] ?? null;

        return $key !== null && $key !== '' ? (string)$key : null;
    }

    /**
     * For debugging and unit tests
     */
    public function __toJSONForTestsOnly(): array
    {
        $ret = [
            'moduleName' => $this->moduleName,
            'type' => static::TYPE,
            'options' => $this->options,
            '__stringVariationArgs' => array_map(function (FbtArgumentBase $arg) {
                return $arg->__toJSONForTestsOnly();
            }, $this->getArgsForStringVariationCalc()),
            'parent' => $this->parent !== null ? get_class($this->parent) : null,
            'children' => array_map(function (FbtNode $child) {
                return $child->__toJSONForTestsOnly();
            }, $this->children),
        ];

        return $ret;
    }

    /**
     * Returns a JSON-friendly representation of this instance that can be consumed
     * in other programming languages.
     * NOTE: this only represents the current node but not its children!
     */
    public function toPlainFbtNode(): array
    {
        return ['type' => static::TYPE];
    }

    /**
     * Returns the list of arguments of this fbt node
     * (assuming that it's based on a function call), or null.
     */
    public function getCallNodeArguments(): ?array
    {
        return $this->callArgs;
    }

    /**
     * Returns the first parent FbtNode that is an instance of the given class.
     *
     * @template T
     * @param class-string<T> $ancestorConstructor
     * @return T|null
     */
    public function getFirstAncestorOfType(string $ancestorConstructor): ?FbtNode
    {
        for ($parent = $this->parent; $parent !== null; $parent = $parent->parent) {
            if ($parent instanceof $ancestorConstructor) {
                return $parent;
            }
        }

        return null;
    }

    /**
     * Returns the fbt runtime argument that will be used by an fbt runtime call.
     * I.e.
     * Given the fbt runtime call:
     *
     *   fbt::_($jsfbtTable, [
     *     <<runtimeFbtArg>>
     *   ])
     *
     * This method is responsible to generate <<runtimeFbtArg>>
     *
     * js~php diff: the runtime argument is evaluated right away instead of
     * generating the code of the runtime call.
     */
    abstract public function getFbtRuntimeArg(): ?array;

    /**
     * Creates the result of an `fbt::_<<methodName>>(args)` runtime function call.
     * <<methodName>> is inferred from the current fbt node.
     *
     * @param array $args Arguments of the function call
     * @param string|null $overrideMethodName Use this method name instead of the one from the fbtNode
     */
    public function createFbtRuntimeArgCallExpression(array $args, ?string $overrideMethodName = null): array
    {
        $runtime = $this->moduleName === FbtConstants::MODULE_NAME['FBS'] ? fbs::class : fbt::class;

        return call_user_func_array([$runtime, '_' . ($overrideMethodName ?? static::TYPE)], $args);
    }
}
