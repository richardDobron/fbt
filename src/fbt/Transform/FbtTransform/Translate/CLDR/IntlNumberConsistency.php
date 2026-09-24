<?php

namespace fbt\Transform\FbtTransform\Translate\CLDR;

interface IntlNumberConsistency
{
    public function getNumberVariations(): array;

    public function getFallback(): int;

    public function getExample(int $variation): ?string;

    /**
     * @param int|float $n
     */
    public function getVariation($n): int;
}
