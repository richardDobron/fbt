<?php

namespace fbt\Runtime\Shared;

class FbtResult extends FbtResultBase
{
    /**
     * @param array{contents: array, errorListener?: IFbtErrorListener|null} $input
     *
     * @return static
     */
    public static function get(array $input): self
    {
        return new static($input['contents'], $input['errorListener'] ?? null);
    }
}
