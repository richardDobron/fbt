<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

use function fbt\invariant;

/**
 * Map of string variation arguments keyed by their source FbtNode
 */
class StringVariationArgsMap
{
    /** @var array<int, StringVariationArg> */
    private $_map = [];

    /**
     * @param StringVariationArg[] $svArgs
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public function __construct(array $svArgs)
    {
        foreach ($svArgs as $arg) {
            $this->_map[spl_object_id($arg->fbtNode)] = $arg;
        }

        invariant(
            count($svArgs) === count($this->_map),
            'Expected only one StringVariationArg per FbtNode. ' .
            'Input array length=%s but resulting map size=%s',
            count($svArgs),
            count($this->_map)
        );
    }

    /**
     * @return StringVariationArg corresponding to the given FbtNode
     *
     * @throws \fbt\Exceptions\FbtException
     */
    public function get(FbtNode $fbtNode): StringVariationArg
    {
        $ret = $this->_map[spl_object_id($fbtNode)] ?? null;
        invariant(
            $ret !== null,
            'Unable to find entry for FbtNode: %s',
            get_class($fbtNode)
        );

        return $ret;
    }

    /**
     * @throws \fbt\Exceptions\FbtException if the given FbtNode cannot be found
     */
    public function mustHave(FbtNode $fbtNode): void
    {
        $this->get($fbtNode);
    }
}
