<?php

namespace fbt\Runtime;

use dobron\DomForge\Node;

class fbtNode
{
    public $name;
    public $node;
    public $args;
    /* @var string | fbtElement | fbtNamespace */
    public $value = '';

    public function __construct(
        string $name,
        Node $node,
        array $args,
        $value = ''
    ) {
        $this->value = $value;
        $this->args = $args;
        $this->node = $node;
        $this->name = $name;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
