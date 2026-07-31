<?php

namespace fbt\Transform\FbtTransform\Translate\CLDR;

interface IntlNumberConsistency
{
    public function getNumberVariations(): array;

    public function getFallback(): int;

    public function getExample(int $variation): ?string;

    public function getVariation(int $n): int;
}
