<?php

namespace fbt\Transform\FbtTransform;

use fbt\Runtime\Shared\fbs;
use fbt\Runtime\Shared\fbt;

/**
 * js~php diff: instead of generating the code of the fbt runtime calls, the fbt runtime
 * calls (FbtCallExpression) are evaluated with the values of the fbt() callsite, i.e. the
 * values that the expressions of the generated code would refer to.
 *
 * Values of an HTML document may also contain markers of the fbt callsites nested in it
 * (e.g. `<fbt:param><fbt>...</fbt></fbt:param>`), which are rendered by `$renderItem`.
 */
final class FbtRuntimeScope
{
    public const ITEM_START = "\u{E002}";
    public const ITEM_END = "\u{E003}";
    public const ITEM_PATTERN = '/\x{E002}(\d+)\x{E003}/u';

    /** @var array */
    private $values;
    /** @var callable|null */
    private $renderItem;

    public function __construct(array $values = [], ?callable $renderItem = null)
    {
        $this->values = $values;
        $this->renderItem = $renderItem;
    }

    public static function itemMarker(int $index): string
    {
        return self::ITEM_START . $index . self::ITEM_END;
    }

    /**
     * @param mixed $node - literal, FbtExpression, FbtCallExpression, or an array of those
     * @return mixed
     */
    public function evaluate($node)
    {
        if ($node instanceof FbtCallExpression) {
            $runtime = $node->moduleName === FbtConstants::MODULE_NAME['FBS'] ? fbs::class : fbt::class;

            return call_user_func_array([$runtime, $node->name], $this->evaluate($node->arguments));
        }

        if ($node instanceof FbtExpression) {
            return $node->index !== null ? ($this->values[$node->index] ?? null) : $node->value;
        }

        if (is_array($node)) {
            return array_map([$this, 'evaluate'], $node);
        }

        if (is_string($node) && strpos($node, self::ITEM_START) !== false) {
            return preg_replace_callback(self::ITEM_PATTERN, function (array $matches) {
                return $this->renderItem !== null ? (string)($this->renderItem)((int)$matches[1]) : '';
            }, $node);
        }

        return $node;
    }
}
