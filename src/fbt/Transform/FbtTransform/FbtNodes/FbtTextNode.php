<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

use dobron\DomForge\Node;

/**
 * Represents the text literals present within <fbt> or fbt() callsites.
 *
 * I.e.
 *
 *  fbt(
 *    'Hello', // <-- FbtTextNode
 *    'description'
 *  )
 */
class FbtTextNode extends FbtNode
{
    public const TYPE = FbtNodeType::TEXT;

    /**
     * js~php diff: text literal (the equivalent of `this.node.value`)
     * @var string
     */
    private $text;

    public function __construct(array $params)
    {
        $this->text = $params['text'];
        parent::__construct($params);
    }

    /**
     * Create a new class instance given a text literal.
     */
    public static function fromText(string $moduleName, string $text, ?Node $node = null): self
    {
        return new self([
            'moduleName' => $moduleName,
            'node' => $node,
            'text' => $text,
        ]);
    }

    public function getOptions(array $validExtraOptions = []): ?array
    {
        return null;
    }

    public function getArgsForStringVariationCalc(): array
    {
        return [];
    }

    public function getText(StringVariationArgsMap $argsMap): string
    {
        return $this->text;
    }

    public function getFbtRuntimeArg(): ?array
    {
        return null;
    }

    public function __toJSONForTestsOnly(): array
    {
        return parent::__toJSONForTestsOnly() + ['text' => $this->text];
    }
}
