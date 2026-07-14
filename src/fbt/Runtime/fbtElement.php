<?php

namespace fbt\Runtime;

use function fbt\createElement;

class fbtElement
{
    public $attributes = [];
    /* @var string | array | fbtNamespace */
    public $content;
    public $tag;
    public $children = [];

    public function __construct(
        string $tag,
        $content,
        array $attributes = [],
        array $children = []
    ) {
        $this->children = $children;
        $this->tag = $tag;
        $this->content = $content;
        $this->attributes = $attributes;
    }

    public function __toString()
    {
        $content = implode('', array_map('strval', $this->children)) ?: $this->content;

        if ($this->tag === 'text') {
            return $content;
        }

        return createElement($this->tag, $content, $this->attributes);
    }
}
