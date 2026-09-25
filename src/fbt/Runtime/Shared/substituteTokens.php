<?php

namespace fbt\Runtime\Shared;

use fbt\FbtConfig;

use function fbt\invariant;

class substituteTokens
{
    /**
     * This pattern finds tokens inside a string: 'string with {token} inside'.
     * It also grabs any punctuation that may be present after the token, such as
     * brackets, fullstops and elipsis (for various locales too!)
     */
    private const PARAMETER_REGEXP = '/\{([^}]+)\}(' . IntlPunctuation::PUNCT_CHAR_CLASS . '*)/u';

    /**
     * Does the token substitution fbt() but without the string lookup.
     * Used for in-place substitutions in translation mode.
     *
     * @param string $template
     * @param array|null $args
     * @param IFbtErrorListener|null $errorListener
     *
     * @return string|array
     * @throws \fbt\Exceptions\FbtException
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     */
    public static function substitute(string $template, ?array $args, ?IFbtErrorListener $errorListener = null)
    {
        if ($args === null) {
            return $template;
        }

        // js~php diff: without any substitutions, the tokens of the template are kept
        // so that literal braces in plain strings are preserved
        if (! $args) {
            if (preg_match_all(self::PARAMETER_REGEXP, $template, $matches)) {
                foreach ($matches[1] as $parameter) {
                    self::onMissingParameterError($errorListener, [], $parameter);
                }
            }

            return IntlPunctuation::applyPhonologicalRules($template);
        }

        $debug = FbtConfig::get('debug');

        // Splice in the arguments while keeping rich object ones separate.
        $objectPieces = [];
        $argNames = [];
        $stringPieces = array_map(
            [IntlPunctuation::class, 'applyPhonologicalRules'],
            explode("\x17", preg_replace_callback(
                self::PARAMETER_REGEXP,
                function (array $matches) use ($args, $debug, $errorListener, &$objectPieces, &$argNames): string {
                    [, $parameter, $punctuation] = $matches;

                    if (! array_key_exists($parameter, $args)) {
                        self::onMissingParameterError($errorListener, array_keys($args), $parameter);
                    }

                    // js~php diff: without an error listener handling missing parameters,
                    // they still fail in debug mode
                    if ($debug && ! self::handlesMissingParameters($errorListener)) {
                        invariant(
                            array_key_exists($parameter, $args),
                            'Expected fbt parameter names (%s) to also contain `%s`',
                            implode(', ', array_map(function ($paramName) {
                                return "`$paramName`";
                            }, array_keys($args))),
                            $parameter
                        );
                    }

                    // js~php diff: a missing argument is substituted with an empty
                    // string instead of the literal "undefined"
                    $argument = $args[$parameter] ?? null;

                    // js~php diff: PHP arrays are the equivalent of JS objects here
                    if (is_object($argument) || is_array($argument)) {
                        $objectPieces[] = $argument;
                        $argNames[] = $parameter;

                        // End of Transmission Block sentinel marker
                        return "\x17" . $punctuation;
                    } elseif ($argument === null) {
                        return '';
                    }

                    // js~php diff: like JS `String(argument)`
                    if (is_bool($argument)) {
                        $argument = $argument ? 'true' : 'false';
                    } elseif (is_float($argument)) {
                        $argument = intlNumUtils::toJsString($argument);
                    } else {
                        $argument = (string)$argument;
                    }

                    return $argument . IntlPunctuation::dedupeStops($argument, $punctuation);
                },
                $template
            ))
        );

        if (count($stringPieces) === 1) {
            return $stringPieces[0];
        }

        // Zip together the lists of pieces.
        // We skip adding empty strings from stringPieces since they were
        // injected from translation patterns that only contain tokens.
        $pieces = $stringPieces[0] !== '' ? [$stringPieces[0]] : [];
        foreach ($objectPieces as $i => $objectPiece) {
            $pieces[] = $objectPiece;
            if ($stringPieces[$i + 1] !== '') {
                $pieces[] = $stringPieces[$i + 1];
            }
        }

        return $pieces;
    }

    /**
     * js~php diff: `onMissingParameterError` is an optional method of the error listener
     */
    private static function handlesMissingParameters(?IFbtErrorListener $errorListener): bool
    {
        return $errorListener !== null && method_exists($errorListener, 'onMissingParameterError');
    }

    private static function onMissingParameterError(?IFbtErrorListener $errorListener, array $providedParamNames, string $missingParamName): void
    {
        if (self::handlesMissingParameters($errorListener)) {
            $errorListener->onMissingParameterError($providedParamNames, $missingParamName);
        }
    }
}
