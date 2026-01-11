<?php

namespace fbt\Transform\FbtTransform;

use dobron\DomForge\Node;

class FbtNodeChecker
{
    /** @var string */
    public $moduleName;

    public function __construct(string $moduleName)
    {
        $this->moduleName = $moduleName;
    }

    public static function fbtChecker(): self
    {
        return new FbtNodeChecker(FbtConstants::MODULE_NAME['FBT']);
    }

    public static function fbsChecker(): self
    {
        return new FbtNodeChecker(FbtConstants::MODULE_NAME['FBS']);
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
    public static function forFbt(Node $node)
    {
        if (self::fbtChecker()->isElement($node)) {
            return self::fbtChecker();
        } elseif (self::fbsChecker()->isElement($node)) {
            return self::fbsChecker();
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
    public function assertNoNestedFbts(Node $node)
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
