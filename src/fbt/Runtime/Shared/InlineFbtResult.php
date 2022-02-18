<?php

namespace fbt\Runtime\Shared;

use function fbt\createElement;

function cx(string $clsname)
{
    return str_replace('/', '_', $clsname);
}

function em($content, $inlineMode, $translation, $hash)
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

class InlineFbtResult
{
    public $contents;
    public $inlineMode;
    public $translation;
    /** @var null|string */
    public $hash;

    public function __construct(
        array $contents,
        string $inlineMode,
        string $translation,
        $hash
    ) {
        $this->hash = $hash;
        $this->translation = $translation;
        $this->inlineMode = $inlineMode;
        $this->contents = $contents;
    }

    public function __toString()
    {
        return em($this->contents, $this->inlineMode, $this->translation, $this->hash);
    }
}
