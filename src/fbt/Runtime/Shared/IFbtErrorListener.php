<?php

namespace fbt\Runtime\Shared;

/**
 * Error listener returned by the `errorListener` hook.
 *
 * It can optionally implement `onMissingParameterError(array $providedParamNames, string $missingParamName): void`,
 * called when a token of the translation has no parameter (js~php diff: optional methods
 * of an interface don't exist in PHP).
 *
 * @see FbtHooks::getErrorListener()
 */
interface IFbtErrorListener
{
    /**
     * Handle the error scenario where the FbtResultBase contains non-string elements
     * and tries to run __toString()
     *
     * @param mixed $content
     */
    public function onStringSerializationError($content): void;
}
