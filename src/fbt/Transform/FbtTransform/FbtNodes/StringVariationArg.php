<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

/**
 * Given an fbt callsite that may generate multiple string variations,
 * we know that these variations are issued from some specific arguments.
 *
 * This is the base class that represents these string variation arguments.
 *
 * I.e.
 *
 *     fbt(
 *       [
 *         'Wish ',
 *         fbt::pronoun(
 *           'object',
 *           $personGender, // <-- the string variation argument
 *           ['human' => true]
 *         ),
 *         ' a happy birthday.',
 *       ],
 *       'text with pronoun'
 *     );
 *
 * The string variation argument would be based on the `$personGender` variable.
 */
abstract class StringVariationArg extends FbtArgumentBase
{
    /**
     * List of candidate values that this SVArgument might have.
     * @var array
     */
    public $candidateValues;

    /**
     * Current SVArgument value of this instance among candidates from `candidateValues`.
     * @var mixed
     */
    public $value;

    /**
     * Given a list of SV arguments, some of them can be omitted because they're "redundant".
     * Note: a SV argument can be omitted because another one of the same type and same
     * source code expression already exist in the list of SV arguments.
     * Set this property to `true` if that's the case.
     * @var bool
     */
    public $isCollapsible;

    /**
     * @param FbtNode $fbtNode
     * @param mixed $node
     * @param array $candidateValues
     * @param mixed $value
     * @param bool $isCollapsible
     */
    public function __construct(
        FbtNode $fbtNode,
        $node,
        array $candidateValues,
        $value = null,
        bool $isCollapsible = false
    ) {
        parent::__construct($fbtNode, $node);
        $this->candidateValues = $candidateValues;
        $this->value = $value;
        $this->isCollapsible = $isCollapsible;
    }

    /**
     * @param mixed $value
     *
     * @return static
     */
    public function cloneWithValue($value, bool $isCollapsible): self
    {
        return new static(
            $this->fbtNode,
            $this->node,
            $this->candidateValues,
            $value,
            $isCollapsible
        );
    }
}
