<?php

namespace fbt;

use fbt\Transform\FbtTransform\FbtTransform;
use fbt\Transform\FbtTransform\FbtUtils;

class fbt implements \JsonSerializable
{
    /* @var string */
    protected static $moduleName = 'fbt';
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

    public static function param(string $name, string $value, array $options = []): string
    {
        return createElement(static::$moduleName . ':param', $value, [
            'name' => $name,
        ] + $options);
    }

    public static function enum(string $value, array $range): string
    {
        $range = json_encode($range);

        return createElement(static::$moduleName . ':enum', null, [
            'enum-range' => $range,
            'value' => $value,
        ]);
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
     * @param int|float $count
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

    public function _trace(array $trace): void
    {
        $this->trace = $trace;
    }

    /**
     * @throws \Exception
     * @throws Exceptions\FbtParserException|\Throwable
     */
    public function __toString(): string
    {
        $text = $this->text;
        if (is_string($text)) {
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
            return FbtTransform::transform($fbt, $this->trace);
        }

        return $fbt;
    }

    public function jsonSerialize(): string
    {
        return (string) $this;
    }
}
