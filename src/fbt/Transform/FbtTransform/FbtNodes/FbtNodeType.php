<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

class FbtNodeType
{
    public const ELEMENT = 'element';
    public const ENUM = 'enum';
    public const IMPLICIT_PARAM = 'implicitParam';
    public const NAME = 'name';
    public const PARAM = 'param';
    public const PLURAL = 'plural';
    public const PRONOUN = 'pronoun';
    public const SAME_PARAM = 'sameParam';
    public const TEXT = 'text';

    /**
     * Returns the given value if it's a valid FbtNodeType, or null.
     */
    public static function cast(?string $value): ?string
    {
        return in_array($value, [
            self::ELEMENT,
            self::ENUM,
            self::IMPLICIT_PARAM,
            self::NAME,
            self::PARAM,
            self::PLURAL,
            self::PRONOUN,
            self::SAME_PARAM,
            self::TEXT,
        ], true) ? $value : null;
    }
}
