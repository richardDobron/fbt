<?php

namespace fbt\Runtime\Shared;

use function fbt\invariant;

class fbs extends fbt
{
    /**
     * @see fbt::_param()
     *
     * @param string $label
     * @param string|FbtPureStringResult $value
     * @param array $variations
     *
     * @return array
     * @throws \fbt\Exceptions\FbtException
     */
    public static function _param(string $label, $value, array $variations = []): array
    {
        // js~php diff: fbs() returns an \fbt\fbs object (rendered when it's converted to a string)
        invariant(
            is_string($value) || $value instanceof FbtPureStringResult || $value instanceof \fbt\fbs,
            'Expected fbs parameter value to be the result of fbs(), <fbs/>, or a string; ' .
            'instead we got `%s` (type: %s)',
            is_object($value) ? get_class($value) : json_encode($value),
            gettype($value)
        );

        return parent::_param($label, $value, $variations);
    }

    /**
     * @see fbt::_plural()
     *
     * @param float|int|string $count
     * @param string|null $label
     * @param string|FbtPureStringResult|null $value
     *
     * @return array
     * @throws \fbt\Exceptions\FbtException
     */
    public static function _plural($count, ?string $label = null, $value = null): array
    {
        invariant(
            $value === null || is_string($value) || $value instanceof FbtPureStringResult || $value instanceof \fbt\fbs,
            'Expected fbs plural UI value to be nullish or the result of fbs(), <fbs/>, or a string; ' .
            'instead we got `%s` (type: %s)',
            is_object($value) ? get_class($value) : json_encode($value),
            gettype($value)
        );

        return parent::_plural($count, $label, $value);
    }

    /**
     * @param string|array $fbtContent
     * @param string $translation
     * @param string|null $hash
     * @param array|null $extraOptions
     * @param bool $reporting
     *
     * @return FbtPureStringResult|mixed
     */
    protected function _wrapContent(
        $fbtContent,
        string $translation,
        ?string $hash,
        ?array $extraOptions = null,
        bool $reporting = true
    ) {
        $contents = is_string($fbtContent) ? [$fbtContent] : $fbtContent;
        $errorListener = FbtHooks::getErrorListener([
            'hash' => $hash,
            'translation' => $translation,
        ]);

        return FbtHooks::getFbsResult([
            'contents' => $contents,
            'errorListener' => $errorListener,
            'extraOptions' => $extraOptions,
            'patternHash' => $hash,
            'patternString' => $translation,
            'reporting' => $reporting,
        ]);
    }
}
