<?php

namespace fbt\Transform\FbtTransform;

use dobron\DomForge\Node;
use fbt\Transform\FbtTransform\FbtNodes\FbtNodeType;

class FbtNodeChecker
{
    /** @var string */
    public $moduleName;

    /** @var FbtNodeChecker|null */
    private static $fbsChecker = null;
    /** @var FbtNodeChecker|null */
    private static $fbtChecker = null;

    public function __construct(string $moduleName)
    {
        $this->moduleName = FbtUtils::assertModuleName($moduleName);
    }

    /**
     * js~php diff: the equivalent of the module constant `fbtChecker`
     */
    public static function fbtChecker(): self
    {
        return self::$fbtChecker ?? (self::$fbtChecker = new FbtNodeChecker(FbtConstants::MODULE_NAME['FBT']));
    }

    /**
     * js~php diff: the equivalent of the module constant `fbsChecker`
     */
    public static function fbsChecker(): self
    {
        return self::$fbsChecker ?? (self::$fbsChecker = new FbtNodeChecker(FbtConstants::MODULE_NAME['FBS']));
    }

    public static function forModule(string $moduleName): FbtNodeChecker
    {
        return FbtUtils::assertModuleName($moduleName) === FbtConstants::MODULE_NAME['FBT']
            ? self::fbtChecker()
            : self::fbsChecker();
    }

    public function isNameOfModule(string $name): bool
    {
        return $this->moduleName === FbtConstants::MODULE_NAME['FBT']
            ? FbtNodeChecker::isFbtName($name)
            : FbtNodeChecker::isFbsName($name);
    }

    /**
     * @param Node $node
     * @return FbtNodeChecker|null
     */
    public static function forFbt(Node $node): ?FbtNodeChecker
    {
        if (self::fbtChecker()->isElement($node)) {
            return self::fbtChecker();
        } elseif (self::fbsChecker()->isElement($node)) {
            return self::fbsChecker();
        }

        return null;
    }

    /**
     * @param mixed $node
     */
    public static function forFbtFunctionCall($node): ?FbtNodeChecker
    {
        if (self::fbtChecker()->isModuleCall($node)) {
            return self::fbtChecker();
        } elseif (self::fbsChecker()->isModuleCall($node)) {
            return self::fbsChecker();
        }

        return null;
    }

    /**
     * Whether the node is an fbt() call, i.e. `fbt(contents, description, options)`
     *
     * @param mixed $node
     */
    public function isModuleCall($node): bool
    {
        return $node instanceof FbtCallExpression &&
            $node->name === null &&
            $this->isNameOfModule($node->moduleName);
    }

    /**
     * @param mixed $node
     * @return string|null - FbtNodeType
     *
     * js~php diff: fbt constructs within HTML elements aren't converted to function
     * calls yet (see FbtElementNode::createChildNode()), so their DOM nodes are accepted too
     */
    public function getFbtConstructNameFromFunctionCall($node): ?string
    {
        if ($node instanceof FbtCallExpression) {
            return $this->isNameOfModule($node->moduleName) ? FbtNodeType::cast($node->name) : null;
        }

        if ($node instanceof Node && $this->isNamespacedElement($node)) {
            return FbtNodeType::cast(FbtUtils::validateNamespacedFbtElement($this->moduleName, $node));
        }

        return null;
    }

    public function isElement(Node $node): bool
    {
        if (! $node->isElement()) {
            return false;
        }

        return $this->isNameOfModule($node->tag);
    }

    public function isNamespacedElement(Node $node): bool
    {
        if (! $node->isElement()) {
            return false;
        }

        return $node->isNamespacedElement() && $this->isNameOfModule(explode(':', $node->tag)[0]);
    }

    /**
     * Ensure that, given an <fbt/fbs> JSXElement, we don't have any nested <fbt/fbs> element.
     * And also checks that all "parameter" child elements follow the same namespace.
     * E.g.
     * Inside <fbt>, don't allow <fbs:param>.
     * Inside <fbs>, don't allow <fbt:param>.
     *
     * @return void
     * @throws \fbt\Exceptions\FbtParserException
     */
    public function assertNoNestedFbts(Node $node): void
    {
        $moduleName = $this->moduleName;

        foreach ($node->children as $child) {
            if ($child->isElement() &&
                (self::fbtChecker()->isElement($child) || self::fbsChecker()->isElement($child))) {
                $nestedJSXElementName = $child->tag;
                $rootJSXElementName = $node->tag;

                throw FbtUtils::errorAt(
                    $child,
                    "Don't put <$nestedJSXElementName> directly within <$rootJSXElementName>. " .
                    "This is redundant. The text is already translated so you don't need " .
                    "to translate it again"
                );
            } else {
                $otherChecker = $moduleName === FbtConstants::MODULE_NAME['FBT']
                    ? self::fbsChecker()
                    : self::fbtChecker();

                if ($otherChecker->isNamespacedElement($child)) {
                    $jsxNamespacedName = $child->tag;

                    throw FbtUtils::errorAt(
                        $child,
                        "Don't mix <fbt> and <fbs> HTML namespaces. " .
                        "Found a <$jsxNamespacedName> " .
                        "directly within a <$moduleName>"
                    );
                }
            }
        }
    }

    public static function isFbtName(string $name): bool
    {
        return $name === FbtConstants::MODULE_NAME['FBT'];
    }

    public static function isFbsName(string $name): bool
    {
        return $name === FbtConstants::MODULE_NAME['FBS'];
    }
}
