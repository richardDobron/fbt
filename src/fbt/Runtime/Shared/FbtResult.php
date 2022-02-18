<?php

namespace fbt\Runtime\Shared;

class FbtResult
{
    /** @var array */
    private $content;
    /** @var null|string */
    private $_stringValue = null;

    public function __construct(array $contents)
    {
        $this->content = $contents;
    }

    public function __toString()
    {
        if ($this->_stringValue !== null) {
            return $this->_stringValue;
        }

        $stringValue = "";
        $contents = $this->flattenToArray($this->content);
        foreach ($contents as $content) {
            if (is_string($content) || $content instanceof FbtResult) {
                $stringValue .= (string)$content;
            } else {
                $this->onStringSerializationError($content);
            }
        }

        $this->_stringValue = $stringValue;

        return $stringValue;
    }

    private function flattenToArray(array $contents = []): array
    {
        $result = [];

        foreach ($contents as $content) {
            if (is_array($content)) {
                $result = array_merge($result, $this->flattenToArray($content));
            } else {
                if ($content instanceof FbtResult) {
                    $result = array_merge($result, $content->flattenToArray($content->content));
                } else {
                    $result[] = $content;
                }
            }
        }

        return $result;
    }
}
