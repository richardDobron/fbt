<?php

namespace fbt\Runtime;

use function fbt\createElement;
use fbt\Transform\FbtTransform\FbtUtils;

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
        $attributes = array_diff_key($this->attributes, FbtUtils::FBT_CORE_ATTRIBUTES);
        $content = implode('', array_map(function (self $child) {
            return (string)$child;
        }, $this->children)) ?: $this->content;

        if ($this->tag === 'text') {
            return $content;
        }

        return createElement($this->tag, $content, $attributes);
    }
}
