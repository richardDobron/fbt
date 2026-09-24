<?php

namespace fbt\Runtime\Shared;

class FbtResultBase implements \JsonSerializable
{
    /** @var array */
    protected $_contents;
    /** @var string|null */
    protected $_stringValue = null;
    /**
     * Helps detect infinite recursion cycles with __toString()
     * @var bool
     */
    protected $_isSerializing = false;
    /** @var IFbtErrorListener|null */
    protected $__errorListener;

    public function __construct(array $contents, ?IFbtErrorListener $errorListener = null)
    {
        $this->_contents = $contents;
        $this->__errorListener = $errorListener;
    }

    public function flattenToArray(): array
    {
        return self::flattenContentsToArray($this->_contents);
    }

    public function getContents(): array
    {
        return $this->_contents;
    }

    /**
     * No return type, so that subclasses declaring `__toString()` without one
     * stay compatible on PHP 7.
     *
     * @return string
     */
    public function __toString()
    {
        // Prevent risk of infinite recursions if the error listener or nested contents __toString()
        // reenters this method on the same instance
        if ($this->_isSerializing) {
            return '<<Reentering fbt.toString() is forbidden>>';
        }

        $this->_isSerializing = true;

        try {
            return $this->_toString();
        } finally {
            $this->_isSerializing = false;
        }
    }

    protected function _toString(): string
    {
        if ($this->_stringValue !== null) {
            return $this->_stringValue;
        }

        $stringValue = '';
        foreach ($this->flattenToArray() as $content) {
            if (is_string($content) || $content instanceof FbtResultBase) {
                $stringValue .= $content;
            } elseif (is_object($content) && method_exists($content, '__toString')) {
                // js~php diff: stringable objects can be safely serialized
                $stringValue .= $content;
            } elseif ($this->__errorListener !== null) {
                $this->__errorListener->onStringSerializationError($content);
            }
        }

        $this->_stringValue = $stringValue;

        return $stringValue;
    }

    public function jsonSerialize(): string
    {
        return $this->__toString();
    }

    /**
     * js~php diff: static counterpart of the upstream `FbtResultBase.flattenToArray(contents)`
     */
    public static function flattenContentsToArray(array $contents): array
    {
        $result = [];
        foreach ($contents as $content) {
            if (is_array($content)) {
                $result = array_merge($result, self::flattenContentsToArray($content));
            } elseif ($content instanceof FbtResultBase) {
                $result = array_merge($result, $content->flattenToArray());
            } else {
                $result[] = $content;
            }
        }

        return $result;
    }
}
