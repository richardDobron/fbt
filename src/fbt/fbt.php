<?php

namespace fbt;

use fbt\Runtime\Shared\FbtHooks;
use fbt\Transform\FbtTransform\FbtTransform;
use fbt\Transform\FbtTransform\FbtUtils;
use Latte\Runtime\HtmlStringable;

class fbt implements \JsonSerializable, HtmlStringable
{
    /* @var string */
    protected static $moduleName = 'fbt';
    protected static $cachedFbt = [];
    /** @var array<string, true> */
    protected static $collectedFbt = [];
    /* @var bool */
    protected $transform;
    /* @var string|array */
    protected $text;
    /* @var string */
    protected $description;
    /* @var array */
    protected $options = [];
    protected $trace = [];

    public function __construct(
        $text,
        string $description,
        array $options = []
    ) {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        if (in_array($backtrace[1]['function'], ['fbt', 'fbs'])) {
            $this->trace = $backtrace[1];
        } else {
            $this->trace = $backtrace[0];
        }

        $this->options = $options;
        $this->description = $description;
        $this->text = $text;
        $this->transform = $this->options['transform'] ?? true;
        unset($this->options['transform']);
    }

    /**
     * @param string $name
     * @param string|float|int $value
     * @param array $options
     * @return string
     */
    public static function param(string $name, $value, array $options = []): string
    {
        if (isset($options['number']) && is_bool($options['number'])) {
            $options['number'] = $options['number'] ? 'true' : 'false';
        }

        return createElement(static::$moduleName . ':param', $value, [
            'name' => $name,
        ] + $options);
    }

    /**
     * @param string $value
     * @param array $range
     * @param array $options - e.g. ['key' => 'value identity'] (see docs/enums.md)
     */
    public static function enum(string $value, array $range, array $options = []): string
    {
        $range = json_encode($range);

        return createElement(static::$moduleName . ':enum', null, [
            'enum-range' => $range,
            'value' => $value,
        ] + $options);
    }

    public static function name(string $tokenName, string $value, int $gender): string
    {
        return createElement(static::$moduleName . ':name', $value, [
            'name' => $tokenName,
            'gender' => $gender,
        ]);
    }

    /**
     * @param string $label
     * @param float|int $count
     * @param array $options
     *
     * @return string
     */
    public static function plural(string $label, $count, array $options = []): string
    {
        return createElement(static::$moduleName . ':plural', $label, [
            'count' => $count,
        ] + $options);
    }

    public static function pronoun(string $usage, int $gender, array $options = []): string
    {
        return createElement(static::$moduleName . ':pronoun', null, [
            'type' => $usage,
            'gender' => $gender,
        ] + $options);
    }

    public static function sameParam(string $name): string
    {
        return createElement(static::$moduleName . ':same-param', null, [
            'name' => $name,
        ]);
    }

    public static function c(string $name, array $options = []): fbs
    {
        return new fbs($name, $options + [
            'common' => true,
        ]);
    }

    /**
     * @internal
     */
    public function _trace(array $trace): void
    {
        $this->trace = $trace;
    }

    /**
     * @internal
     */
    public static function _purgeCache(): void
    {
        self::$cachedFbt = [];
        self::$collectedFbt = [];
    }

    /**
     * @throws \Exception
     * @throws Exceptions\FbtParserException|\Throwable
     */
    public function __toString(): string
    {
        $text = $this->text;
        if (! is_array($text)) {
            $text = [$this->text];
        }

        $attributes = [
            'desc' => $this->description,
        ] + $this->options;

        foreach ($attributes as $attribute => $value) {
            if (array_key_exists($attribute, FbtUtils::SHORT_BOOL_CANDIDATES)) {
                $attributes[$attribute] = $value === true ? 'true' : 'false';
            }
        }

        $fbt = createElement(self::$moduleName, implode('', $text), $attributes);
        if ($this->transform) {
            return $this->_transform($fbt);
        }

        return $fbt;
    }

    /**
     * @throws \Throwable
     */
    protected function _transform(string $html): string
    {
        $inlineMode = FbtHooks::inlineMode();
        if ($inlineMode && $inlineMode !== 'NO_INLINE') {
            return $this->_transformOnce($html);
        }

        // The result depends on the viewer's locale and gender as well
        $hash = md5(
            $html . "\0" .
            FbtHooks::locale() . "\0" .
            FbtHooks::getIntlViewerContext()->getGender()
        );

        if (! isset(self::$cachedFbt[$hash])) {
            self::$cachedFbt[$hash] = $this->_transformOnce($html);
        }

        return self::$cachedFbt[$hash];
    }

    /**
     * Transforms the fbt, collecting its phrases only the first time.
     *
     * @throws \Throwable
     */
    private function _transformOnce(string $html): string
    {
        $key = md5($html);
        if (! isset(self::$collectedFbt[$key])) {
            self::$collectedFbt[$key] = true;

            return FbtTransform::transform($html, $this->trace, true);
        }

        $collectPhrases = FbtTransform::$collectPhrases;
        FbtTransform::$collectPhrases = false;

        try {
            return FbtTransform::transform($html, $this->trace, true);
        } finally {
            FbtTransform::$collectPhrases = $collectPhrases;
        }
    }

    public function jsonSerialize(): string
    {
        return (string) $this;
    }
}
