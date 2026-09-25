<?php

namespace fbt;

use fbt\Exceptions\FbtParserException;
use fbt\Transform\FbtTransform\FbtCallExpression;
use fbt\Transform\FbtTransform\FbtExpression;
use fbt\Transform\FbtTransform\FbtTransform;
use fbt\Transform\FbtTransform\FbtUtils;
use Latte\Runtime\HtmlStringable;

/**
 * An fbt() callsite, e.g. `fbt('Hello ' . fbt::param('name', $name), 'greeting')`.
 *
 * fbt constructs, e.g. fbt::param(), return an FbtCallExpression (the equivalent of the
 * babel node of `fbt.param(...)`), whose runtime values are FbtExpression.
 * The callsite is compiled once for all of its values (see FbtTransform::transformCallExpression()),
 * like upstream fbt compiles the callsites at build time.
 */
class fbt implements \JsonSerializable, HtmlStringable
{
    /* @var string */
    protected static $moduleName = 'fbt';
    /* @var bool */
    protected $transform;
    /**
     * The fbt(contents, description, options) call, whose expressions refer to $values
     * @var FbtCallExpression
     */
    protected $callExpression;
    /**
     * Runtime values of the fbt() callsite
     * @var array
     */
    protected $values = [];
    protected $trace = [];

    /**
     * @param string|array $text
     * @param string|null $description - null for common strings (see fbt::c())
     * @param array $options
     *
     * @throws FbtParserException
     */
    public function __construct(
        $text,
        ?string $description,
        array $options = []
    ) {
        $this->trace = self::getCallsiteTrace();
        $this->transform = $options['transform'] ?? true;
        unset($options['transform']);

        $contents = [];
        foreach (is_array($text) ? $text : [$text] as $part) {
            if ($part instanceof FbtCallExpression) {
                $contents[] = $part;

                continue;
            }

            array_push($contents, ...FbtCallExpression::split((string)$part));
        }

        $options = self::checkOptionValues($options);
        if (array_key_exists('subject', $options)) {
            $options['subject'] = new FbtExpression($options['subject']);
        }

        $isCommon = ($options['common'] ?? false) === true || ($options['common'] ?? null) === 'true';

        $this->callExpression = $this->indexExpressions(new FbtCallExpression(
            static::$moduleName,
            $isCommon && $description === null ? 'c' : null,
            [$contents, $description, $options]
        ));
    }

    private static function getCallsiteTrace(): array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $trace = [];

        foreach ($backtrace as $frame) {
            if (! self::isApiFrame($frame)) {
                break;
            }
            $trace = $frame;
        }

        return $trace;
    }

    private static function isApiFrame(array $frame): bool
    {
        $class = $frame['class'] ?? null;
        $function = $frame['function'] ?? '';

        if ($class !== null) {
            return is_a($class, self::class, true) && in_array($function, ['__construct', 'c', 'getCallsiteTrace'], true);
        }

        $function = substr((string)strrchr('\\' . $function, '\\'), 1);

        return in_array($function, ['fbt', 'fbs'], true);
    }

    /**
     * Copies the given call, where the expressions refer to the values of this callsite.
     *
     * js~php diff: a string concatenated with fbt constructs within an fbt construct, e.g.
     * `fbt::param('outer', 'x ' . fbt::param('inner', $value))`, is expanded to its parts
     * (the equivalent of FbtUtil.expandStringConcat), so that the nested construct is
     * reported (see FbtFunctionCallProcessor::throwIfExistsNestedFbtConstruct()).
     *
     * @param mixed $node
     * @param bool $inConstruct
     * @return mixed
     */
    private function indexExpressions($node, bool $inConstruct = false)
    {
        if ($node instanceof FbtCallExpression) {
            return new FbtCallExpression(
                $node->moduleName,
                $node->name,
                $this->indexExpressions($node->arguments, $node->name !== null && $node->name !== 'c')
            );
        }

        if ($node instanceof FbtExpression) {
            if ($inConstruct && is_string($node->value) && FbtCallExpression::containsMarker($node->value)) {
                return $this->indexExpressions(FbtCallExpression::split($node->value), $inConstruct);
            }

            $index = count($this->values);
            $this->values[] = $node->value;

            return new FbtExpression(null, $index);
        }

        if (is_array($node)) {
            foreach ($node as $key => $value) {
                $node[$key] = $this->indexExpressions($value, $inConstruct);
            }

            return $node;
        }

        if ($inConstruct && is_string($node) && FbtCallExpression::containsMarker($node)) {
            return $this->indexExpressions(FbtCallExpression::split($node), $inConstruct);
        }

        return $node;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @param array $options
     *
     * @throws FbtParserException
     */
    public static function param(string $name, $value, array $options = []): FbtCallExpression
    {
        $options = self::checkOptionValues($options);
        if (array_key_exists('number', $options) && ! is_bool($options['number'])) {
            $options['number'] = new FbtExpression($options['number']);
        }
        if (array_key_exists('gender', $options)) {
            $options['gender'] = new FbtExpression($options['gender']);
        }

        return self::createConstruct('param', [$name, new FbtExpression($value)], $options);
    }

    /**
     * @param mixed $value
     * @param array $range - list of values, or map of `value => text`
     * @param array $options - e.g. ['key' => 'value identity'] (see docs/enums.md)
     *
     * @throws FbtParserException
     */
    public static function enum($value, array $range, array $options = []): FbtCallExpression
    {
        // js~php diff: a list of values is the equivalent of a map of `value => value`
        if ($range !== [] && array_keys($range) === range(0, count($range) - 1)) {
            $range = array_combine(array_map('strval', $range), $range);
        }

        return self::createConstruct('enum', [new FbtExpression($value), $range], self::checkOptionValues($options));
    }

    /**
     * @param string $tokenName
     * @param mixed $value
     * @param mixed $gender
     */
    public static function name(string $tokenName, $value, $gender): FbtCallExpression
    {
        return self::createConstruct('name', [$tokenName, new FbtExpression($value), new FbtExpression($gender)]);
    }

    /**
     * @param string $label
     * @param float|int $count
     * @param array $options
     *
     * @throws FbtParserException
     */
    public static function plural(string $label, $count, array $options = []): FbtCallExpression
    {
        $options = self::checkOptionValues($options);
        if (array_key_exists('value', $options)) {
            $options['value'] = new FbtExpression($options['value']);
        }

        return self::createConstruct('plural', [$label, new FbtExpression($count)], $options);
    }

    /**
     * @param string $usage
     * @param mixed $gender
     * @param array $options
     *
     * @throws FbtParserException
     */
    public static function pronoun(string $usage, $gender, array $options = []): FbtCallExpression
    {
        return self::createConstruct('pronoun', [$usage, new FbtExpression($gender)], self::checkOptionValues($options));
    }

    public static function sameParam(string $name): FbtCallExpression
    {
        return self::createConstruct('sameParam', [$name]);
    }

    /**
     * Common string, i.e. `fbt::c('Photo')` (see docs/common.md)
     *
     * @return static
     * @throws FbtParserException
     */
    public static function c(string $text, array $options = []): self
    {
        return new static($text, null, $options + [
            'common' => true,
        ]);
    }

    private static function createConstruct(string $name, array $args, array $options = []): FbtCallExpression
    {
        if (count($options) > 0) {
            $args[] = $options;
        }

        return new FbtCallExpression(static::$moduleName, $name, $args);
    }

    /**
     * Options are literals (except the runtime values, e.g. the `value` of fbt::plural())
     *
     * @throws FbtParserException
     */
    private static function checkOptionValues(array $options): array
    {
        foreach ($options as $name => $value) {
            if (is_object($value) && method_exists($value, '__toString')) {
                $options[$name] = (string)$value;
            } elseif (($value === 'true' || $value === 'false') && FbtUtils::isBooleanOption($name)) {
                // like the attributes of <fbt> constructs (see FbtUtils::getOptionsFromAttributes())
                $options[$name] = $value === 'true';
            } elseif ($value !== null && ! is_scalar($value)) {
                throw new FbtParserException(
                    "Option \"$name\" has an invalid value. " .
                    'Expected a string literal but value is ' . FbtUtils::describe($value)
                );
            }
        }

        return $options;
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
        FbtTransform::clearCache();
        Runtime\Shared\fbt::_purgeCache();
    }

    /**
     * @param mixed $value
     */
    public static function isFbtInstance($value): bool
    {
        return $value instanceof self || Runtime\Shared\fbt::isFbtInstance($value);
    }

    /**
     * Returns the equivalent <fbt> markup of this callsite (js~php diff: the equivalent
     * of the JSX of an fbt() call), where the runtime values are inlined.
     *
     * @throws FbtParserException
     */
    private function toMarkup(): string
    {
        [$contents, $description, $options] = $this->callExpression->arguments;

        $attributes = $description !== null ? ['desc' => $description] : [];

        return createElement(
            static::$moduleName,
            implode('', array_map([$this, 'toMarkupPart'], $contents)),
            $attributes + $this->toMarkupAttributes($options)
        );
    }

    /**
     * @param string|FbtCallExpression $part
     */
    private function toMarkupPart($part): string
    {
        if (! $part instanceof FbtCallExpression) {
            return $part;
        }

        $args = $part->arguments;
        $options = $part->name !== 'name' ? $this->toMarkupAttributes($args[2] ?? []) : [];
        $tag = static::$moduleName . ':' . ($part->name === 'sameParam' ? 'same-param' : $part->name);

        switch ($part->name) {
            case 'param':
                return createElement($tag, $this->toMarkupValue($args[1]), ['name' => $args[0]] + $options);
            case 'enum':
                return createElement($tag, null, [
                    'enum-range' => json_encode($args[1]),
                    'value' => $this->toMarkupValue($args[0]),
                ] + $options);
            case 'name':
                return createElement($tag, $this->toMarkupValue($args[1]), [
                    'name' => $args[0],
                    'gender' => $this->toMarkupValue($args[2]),
                ]);
            case 'plural':
                return createElement($tag, $args[0], ['count' => $this->toMarkupValue($args[1])] + $options);
            case 'pronoun':
                return createElement($tag, null, [
                    'type' => $args[0],
                    'gender' => $this->toMarkupValue($args[1]),
                ] + $options);
            default:
                return createElement($tag, null, ['name' => $args[0]]);
        }
    }

    private function toMarkupAttributes(array $options): array
    {
        return array_map([$this, 'toMarkupValue'], $options);
    }

    /**
     * @param mixed $value
     */
    private function toMarkupValue($value): string
    {
        if ($value instanceof FbtExpression) {
            $value = $this->values[$value->index];
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string)$value;
    }

    /**
     * @throws \Exception
     * @throws Exceptions\FbtParserException|\Throwable
     */
    public function __toString(): string
    {
        if (! $this->transform) {
            return $this->toMarkup();
        }

        return FbtTransform::transformCallExpression($this->callExpression, $this->values, $this->trace);
    }

    public function jsonSerialize(): string
    {
        return (string) $this;
    }
}
