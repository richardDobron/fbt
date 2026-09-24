<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

/**
 * @property FbtNode[] $children
 */
interface IFbtElementNode
{
    /**
     * Returns description of this fbt string for the given map of string variation arguments
     */
    public function getDescription(StringVariationArgsMap $argsMap): string;

    /**
     * Register a token name
     *
     * @throws \fbt\Exceptions\FbtParserException if the token name was already registered
     */
    public function registerToken(string $name, FbtNode $source): void;
}
