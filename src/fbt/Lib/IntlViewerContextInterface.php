<?php

namespace fbt\Lib;

interface IntlViewerContextInterface
{
    public function getLocale(): string;

    public function getGender(): int;
}
