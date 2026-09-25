<?php

namespace fbt\Transform\FbtTransform;

/**
 * A runtime value of an fbt call, e.g. `$name` in `fbt::param('name', $name)`, i.e. the
 * equivalent of a BabelNodeExpression which isn't a literal.
 *
 * Within an fbt() callsite, an expression refers to its value by the index in the values
 * of the callsite (the equivalent of an identifier in the generated code), so that the
 * compiled callsite doesn't depend on the values (see FbtRuntimeScope).
 */
final class FbtExpression
{
    /**
     * @var mixed
     */
    public $value;
    /**
     * Index of the value in the values of the fbt() callsite
     * @var int|null
     */
    public $index;

    /**
     * @param mixed $value
     */
    public function __construct($value = null, ?int $index = null)
    {
        $this->value = $value;
        $this->index = $index;
    }
}
