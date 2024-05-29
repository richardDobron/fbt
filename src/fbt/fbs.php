<?php

namespace fbt;

use fbt\Transform\FbtTransform\FbtTransform;
use fbt\Transform\FbtTransform\FbtUtils;

class fbs extends fbt
{
    /* @var string */
    protected static $moduleName = 'fbs';

    /**
     * @param string|array $text
     * @param string|array $description
     * @param array $options
     */
    public function __construct(
        $text,
        $description = '',
        array $options = []
    ) {
        if (is_array($description) && empty($options)) {
            $options = $description;
            $description = '';
        }

        parent::__construct($text, $description, $options);
    }

    public function __toString(): string
    {
        static $cache;

        $text = $this->text;
        if (is_string($text)) {
            $text = [$this->text];
        }

        $attributes = [];
        if ($this->description) {
            $attributes['desc'] = $this->description;
        }
        $attributes += $this->options;

        foreach ($attributes as $attribute => $value) {
            if (array_key_exists($attribute, FbtUtils::SHORT_BOOL_CANDIDATES)) {
                $attributes[$attribute] = $value === true ? 'true' : 'false';
            }
        }

        $fbs = createElement(self::$moduleName, implode('', $text), $attributes);
        if ($this->transform) {
            $hash = md5($fbs);
            if (! isset($cache[$hash])) {
                $cache[$hash] = FbtTransform::transform($fbs, $this->trace);
            }

            return $cache[$hash];
        }

        return $fbs;
    }
}
