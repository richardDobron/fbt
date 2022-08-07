<?php

namespace fbt\Runtime;

use fbt\FbtConfig;
use fbt\Transform\FbtRuntime\FbtRuntimeTransform;
use fbt\Transform\FbtTransform\FbtConstants;
use fbt\Transform\FbtTransform\FbtTransform;
use fbt\Transform\FbtTransform\Processors\FbtFunctionCallProcessor;
use fbt\Transform\FbtTransform\Utils\TextPackager;
use fbt\Util\SimpleHtmlDom\Node;

class fbtNamespace extends FbtFunctionCallProcessor
{
    /**
     * @param string|array $text
     * @param string $desc
     * @param array $options
     * @param string $moduleName
     *
     * @throws \fbt\Exceptions\FbtParserException
     */
    public function __construct($text, string $desc, array $options = [], string $moduleName = 'fbt')
    {
        if (! is_array($text)) {
            $text = [$text];
        }

        $this->text = $text;
        $this->desc = $desc;
        $this->moduleName = $moduleName;

        $this->_getOptions($options);
    }

    public static function __callStatic($method, $args)
    {
        return self::{$method}(...$args);
    }

    protected static function plural(Node $node, array $args): fbtNode
    {
        return new fbtNode('plural', $node, $args);
    }

    /**
     * @param Node $node
     * @param array $args
     * @param string|fbtElement|fbtNamespace $value
     *
     * @return fbtNode
     */
    protected static function param(Node $node, array $args, $value = ''): fbtNode
    {
        return new fbtNode('param', $node, $args, $value);
    }

    protected static function enum(Node $node, array $args): fbtNode
    {
        return new fbtNode('enum', $node, $args);
    }

    protected static function pronoun(Node $node, array $args): fbtNode
    {
        return new fbtNode('pronoun', $node, $args);
    }

    /**
     * @param Node $node
     * @param array $args
     * @param string|fbtElement|fbtNamespace $value
     *
     * @return fbtNode
     */
    protected static function name(Node $node, array $args, $value = ''): fbtNode
    {
        return new fbtNode('name', $node, $args, $value);
    }

    /**
     * @param \fbt\Util\SimpleHtmlDom\Node $node
     * @param array $args
     *
     * @return \fbt\Runtime\fbtNode
     */
    protected static function sameParam(Node $node, array $args): fbtNode
    {
        return new fbtNode('sameParam', $node, $args);
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     * @throws \fbt\Exceptions\FbtParserException
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     */
    public function __toString(): string
    {
        $this->_collectFbtCalls();

        $isTable = $this->_isTableNeeded();

        $texts = $this->_getTexts($this->variations, $isTable);

        $desc = $this->_getDescription();

        // js~php diff:
        $textPackager = new TextPackager(FbtConfig::get('hash_module'));

        $phrase = $textPackager->pack([$this->_getPhrase($texts, $desc, $isTable)])[0];

        if (FbtConfig::get('collectFbt') && empty($phrase['doNotExtract'])) {
            FbtTransform::$phrases[] = $phrase;

            if (! empty($phrase['implicitFbt'])) {
                FbtTransform::addEnclosingString(count(FbtTransform::$phrases) - 1, count(FbtTransform::$phrases));
            }
        }

        $table = $phrase['type'] === FbtConstants::FBT_TYPE['TABLE']
            ? $phrase['jsfbt']['t']
            : $phrase['jsfbt'];

        $modules = [
            FbtConstants::MODULE_NAME['FBT'] => Shared\fbt::class,
            FbtConstants::MODULE_NAME['FBS'] => Shared\fbs::class,
        ];
        $reporting = ! isset($phrase['reporting']) || ! empty($phrase['reporting']);

        return (string)call_user_func_array(
            [new $modules[$this->moduleName](), '_'],
            [$table, $this->runtimeArgs, FbtRuntimeTransform::transform($phrase), $reporting]
        );
    }
}
