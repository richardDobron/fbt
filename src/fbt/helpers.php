<?php

namespace Latte\Runtime {
    if (! class_exists(\Latte\Engine::class)) {
        interface HtmlStringable
        {
            /** in HTML format */
            public function __toString(): string;
        }
    } elseif (! interface_exists(\Latte\Runtime\HtmlStringable::class)) {
        interface HtmlStringable extends \Latte\Runtime\IHtmlString
        {
        }
    }
}

namespace {
    if (! function_exists('mb_str_split')) {
        function mb_str_split(string $str, int $length = 1): array
        {
            if ($length < 1) {
                return [];
            }
            $result = [];
            for ($i = 0; $i < mb_strlen($str); $i += $length) {
                $result[] = mb_substr($str, $i, $length);
            }

            return $result;
        }
    }

    if (! function_exists("mb_ord")) {
        function mb_ord($charUTF8): int
        {
            $charUCS4 = mb_convert_encoding($charUTF8, 'UCS-4BE', 'UTF-8');
            $byte1 = ord(substr($charUCS4, 0, 1));
            $byte2 = ord(substr($charUCS4, 1, 1));
            $byte3 = ord(substr($charUCS4, 2, 1));
            $byte4 = ord(substr($charUCS4, 3, 1));

            return ($byte1 << 32) + ($byte2 << 16) + ($byte3 << 8) + $byte4;
        }
    }

    if (! function_exists('fbt')) {
        /**
         * @param string|array $text
         * @param string $description
         * @param array $options
         *
         * @return fbt\fbt
         */
        function fbt($text, string $description, array $options = []): fbt\fbt
        {
            if (class_exists(\fbt\LaravelPackage\fbt::class)) {
                return new \fbt\LaravelPackage\fbt($text, $description, $options);
            }

            return new fbt\fbt($text, $description, $options);
        }
    }

    if (! function_exists('fbs')) {
        /**
         * @param string|array $text
         * @param string|array|null $description
         * @param array $options
         *
         * @return fbt\fbs
         */
        function fbs($text, $description = null, array $options = []): fbt\fbs
        {
            if (class_exists(\fbt\LaravelPackage\fbs::class)) {
                return new \fbt\LaravelPackage\fbs($text, $description, $options);
            }

            return new fbt\fbs($text, $description, $options);
        }
    }

    if (! function_exists('fbtTransform')) {
        function fbtTransform()
        {
            ob_start(function (string $buffer) {
                return \fbt\Transform\FbtTransform\FbtTransform::transform($buffer);
            });
        }
    }

    if (! function_exists('endFbtTransform')) {
        function endFbtTransform()
        {
            ob_end_flush();
        }
    }
}

namespace fbt {
    use fbt\Exceptions\FbtException;
    use fbt\Runtime\fbtNamespace;
    use fbt\Runtime\Shared\IntlList;
    use fbt\Transform\FbtTransform\FbtConstants;
    use fbt\Util\SimpleHtmlDom\DOM;
    use fbt\Util\SimpleHtmlDom\Node;

    /**
     * @return void
     * @throws \fbt\Exceptions\FbtException
     */
    function invariant($condition, $message)
    {
        if (! $condition) {
            if (func_num_args() > 2) {
                $params = array_slice(func_get_args(), 2);
                $message = sprintf($message, ...$params);
            }

            throw new FbtException($message ?? 'Invariant Violation');
        }
    }

    /**
     * @param string|array $text
     * @param string $desc
     * @param array $options
     * @return Runtime\fbtNamespace
     *
     * @throws Exceptions\FbtParserException
     */
    function fbt($text, string $desc, array $options = []): Runtime\fbtNamespace
    {
        return (new fbtNamespace($text, $desc, $options, FbtConstants::MODULE_NAME['FBT']));
    }

    /**
     * @param string|array $text
     * @param string $desc
     * @param array $options
     * @return Runtime\fbtNamespace
     *
     * @throws Exceptions\FbtParserException
     */
    function fbs($text, string $desc, array $options = []): Runtime\fbtNamespace
    {
        return (new fbtNamespace($text, $desc, $options, FbtConstants::MODULE_NAME['FBS']));
    }

    function checkParentTags(Node $node, array $tags): bool
    {
        while ($node = $node->parent()) {
            if (in_array($node->tag, $tags)) {
                return true;
            }
        }

        return false;
    }

    function unsignedRightShift($a, $b): int
    {
        return ($a & 0xFFFFFFFF) >> ($b & 0x1F);
    }

    function rsearch($folder, $pattern): array
    {
        $dir = new \RecursiveDirectoryIterator($folder);
        $ite = new \RecursiveIteratorIterator($dir);
        $files = new \RegexIterator($ite, $pattern, \RegexIterator::MATCH);

        $result = [];
        foreach ($files as $file) {
            $result[] = $file->getPathName();
        }

        sort($result);

        return $result;
    }

    /**
     * @throws FbtException
     * @return \fbt\fbt|string
     */
    function intlList(array $items, string $conjunction = null, string $delimiter = null)
    {
        $intlList = new IntlList($items, $conjunction, $delimiter);

        return $intlList->format();
    }

    /**
     * @param string $tag
     * @param mixed|null $content
     * @param array $attributes
     * @return string
     */
    function createElement(string $tag, $content = null, array $attributes = []): string
    {
        static $dom, $cachedValues = [];

        if (empty($dom)) {
            $dom = new DOM();
        }

        $element = '<' . $tag;

        if ($attributes) {
            $attributeStrings = [];
            foreach ($attributes as $attribute => $value) {
                if ($value === '') {
                    $attributeStrings[] = $attribute;

                    continue;
                }

                $hash = md5($value);

                if (! isset($cachedValues[$hash])) {
                    $cachedValues[$hash] = htmlentities($value, ENT_QUOTES, 'UTF-8', false);
                }

                $attributeStrings[] = "$attribute=\"$cachedValues[$hash]\"";
            }

            $element .= ' ' . implode(' ', $attributeStrings);
        }

        if ($dom->isSelfClosingTag($tag)) {
            $element .= '/>';
        } else {
            if (! is_array($content)) {
                $content = [$content];
            }
            $element .= '>' . implode('', $content) . '</' . $tag . '>';
        }

        return $element;
    }
}
