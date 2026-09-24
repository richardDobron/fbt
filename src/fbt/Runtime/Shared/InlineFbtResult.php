<?php

namespace fbt\Runtime\Shared;

use function fbt\createElement;

function cx(string $clsname)
{
    return str_replace('/', '_', $clsname);
}

function em($content, string $inlineMode, string $translation, ?string $hash)
{
    // TODO: in the future, might depend on the translation status of the
    // string to decide on the proper inline mode.

    $className = cx('intlInlineMode/normal');
    if ($hash) {
        if ($inlineMode === 'TRANSLATION') {
            $className = cx('intlInlineMode/translatable');
        } elseif ($inlineMode === 'APPROVE') {
            $className = cx('intlInlineMode/approvable');
        } elseif ($inlineMode === 'REPORT') {
            $className = cx('intlInlineMode/reportable');
        }

        if (FbtHooks::canInline(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))) {
            return createElement('em', $content, [
                'class' => $className,
                'data-intl-hash' => $hash,
                'data-intl-locale' => FbtHooks::locale(),
                // 'data-intl-translation' => $translation,
                // 'data-intl-trid' => '',
            ]);
        }
    }

    return new FbtResult($content);
}

class InlineFbtResult extends FbtResult
{
    public $contents;
    public $inlineMode;
    public $translation;
    public $hash;

    public function __construct(
        array $contents,
        string $inlineMode,
        string $translation,
        ?string $hash,
        ?IFbtErrorListener $errorListener = null
    ) {
        parent::__construct($contents, $errorListener);

        $this->hash = $hash;
        $this->translation = $translation;
        $this->inlineMode = $inlineMode;
        $this->contents = $contents;
    }

    /**
     * @param array{
     *   contents: array,
     *   errorListener?: IFbtErrorListener|null,
     *   patternString: string,
     *   patternHash: string|null
     * } $input
     *
     * @return static
     */
    public static function get(array $input): FbtResult
    {
        return new static(
            $input['contents'],
            FbtHooks::inlineMode(),
            $input['patternString'],
            $input['patternHash'] ?? null,
            $input['errorListener'] ?? null
        );
    }

    /**
     * Keep this result as a single item when nested in another result, so that
     * its inline wrapper is not lost.
     */
    public function flattenToArray(): array
    {
        return [$this];
    }

    public function __toString(): string
    {
        // Not memoized: inlining depends on the call site (see FbtHooks::canInline),
        // which is why the call stack depth must stay as it is
        return (string) em(
            self::flattenContentsToArray($this->contents),
            $this->inlineMode,
            $this->translation,
            $this->hash
        );
    }
}
