<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

/**
 * Base class representing fbt construct arguments that support dynamic values at runtime.
 *
 * E.g.
 *
 *    <fbt:plural
 *      count="<?= $numParticipants ?>"              <-- FbtArgumentBase
 *      value="<?= formatted($numParticipants) ?>"   <-- FbtArgumentBase
 *      showCount="yes"                              <-- hard-coded, so not an FbtArgumentBase
 *    >
 *      challenger
 *    </fbt:plural>
 */
abstract class FbtArgumentBase
{
    /**
     * Reference of the FbtNode creator of this instance
     * @var FbtNode
     */
    public $fbtNode;
    /**
     * js~php diff: the (already evaluated) runtime value of this argument,
     * instead of the BabelNode representing it
     * @var mixed
     */
    public $node;

    /**
     * @param FbtNode $fbtNode
     * @param mixed $node
     */
    public function __construct(FbtNode $fbtNode, $node)
    {
        $this->fbtNode = $fbtNode;
        $this->node = $node;
    }

    /**
     * Identity of the argument's "source code", used to detect that multiple
     * string variation arguments refer to the same value.
     *
     * js~php diff: the source code of PHP values is not available at runtime, so
     * the identity is given by the construct's explicit `key` option. Without it,
     * the identity is unique for each construct (which means no deduplication).
     */
    public function getArgCode(): string
    {
        $key = $this->fbtNode->getVariationKey();

        return $key !== null
            ? 'key:' . $key
            : 'node#' . spl_object_id($this->fbtNode);
    }

    /**
     * For debugging and unit tests
     */
    public function __toJSONForTestsOnly(): array
    {
        return [
            'fbtNode' => get_class($this->fbtNode),
            'node' => $this->node,
        ] + array_diff_key(get_object_vars($this), ['fbtNode' => true, 'node' => true]);
    }
}
