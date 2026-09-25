<?php

/**
 * Copyright 2014-present Richard Dobroň
 */

declare(strict_types=1);

namespace fbt\Exceptions;

class FbtParserException extends FbtRootException
{
    /**
     * Whether the message contains the location of the error (see FbtUtils::errorAt())
     * @var bool
     */
    public $_hasBabelNodeLocation = false;
}
