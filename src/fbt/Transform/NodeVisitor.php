<?php

namespace fbt\Transform;

use fbt\Services\CollectFbtsService;
use fbt\Lib\IntlVariations;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;

/**
 * js~php diff: the collector evaluates the fbt callsites, so runtime values
 * (variables, function calls, ...) are replaced by literals of the expected type.
 */
class NodeVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node)
    {
        if (CollectFbtsService::matchFbtCalls($node) && ! $node instanceof StaticCall) {
            // fbt(text, desc, ['subject' => ...]), new fbt(...)
            $this->replaceOptions($node, 2, [
                'subject' => new LNumber(IntlVariations::GENDER_MALE),
            ]);
        }

        if (! $node instanceof StaticCall
            || ! $node->class instanceof Name
            || ! CollectFbtsService::isFbtClassName($node->class->toString())
            || ! $node->name instanceof Node\Identifier) {
            return null;
        }

        switch ($node->name->toString()) {
            case 'param':
                if (isset($node->args[1])
                    && ! CollectFbtsService::matchFbtCalls($node->args[1]->value)
                    && ! $node->args[1]->value instanceof String_) {
                    $node->args[1] = new Node\Arg(new String_('123'));
                }
                $this->replaceOptions($node, 2, [
                    'number' => new LNumber(1),
                    'gender' => new LNumber(IntlVariations::GENDER_MALE),
                ]);

                break;
            case 'enum':
                // The value only selects a string variation, so any key of the range works
                if (isset($node->args[0], $node->args[1]) && ! $node->args[0]->value instanceof String_) {
                    $range = $node->args[1]->value;
                    $key = null;
                    if ($range instanceof Array_ && ! empty($range->items)) {
                        $item = $range->items[0];
                        $key = $item->key ?? $item->value;
                    }

                    $node->args[0] = new Node\Arg(
                        $key instanceof String_ || $key instanceof LNumber
                            ? new String_((string)$key->value)
                            : new StaticCall(new Name\FullyQualified(CollectFbtsService::class), 'firstKey', [new Node\Arg($range)])
                    );
                }

                break;
            case 'plural':
                if (isset($node->args[1]) && ! $node->args[1]->value instanceof LNumber) {
                    $node->args[1] = new Node\Arg(new LNumber(1));
                }
                $this->replaceOptions($node, 2, [
                    'value' => new String_('1'),
                ]);

                break;
            case 'pronoun':
                if (isset($node->args[1]) && ! $node->args[1]->value instanceof LNumber) {
                    $node->args[1] = new Node\Arg(new LNumber(1));
                }

                break;
            case 'name':
                if (isset($node->args[1]) && ! $node->args[1]->value instanceof String_) {
                    $node->args[1] = new Node\Arg(new String_('name'));
                }
                if (isset($node->args[2]) && ! $node->args[2]->value instanceof LNumber) {
                    $node->args[2] = new Node\Arg(new LNumber(IntlVariations::GENDER_MALE));
                }

                break;
        }

        return null;
    }

    /**
     * @param FuncCall|StaticCall|New_ $node
     */
    private function replaceOptions(Node $node, int $argIndex, array $literals): void
    {
        $options = $node->args[$argIndex]->value ?? null;
        if (! $options instanceof Array_) {
            return;
        }

        foreach ($options->items as $item) {
            if ($item === null || ! $item->key instanceof String_ || ! isset($literals[$item->key->value])) {
                continue;
            }

            $value = $item->value;
            $isLiteral = $value instanceof String_
                || $value instanceof LNumber
                || ($value instanceof ConstFetch && in_array(strtolower($value->name->toString()), ['true', 'false'], true));

            if (! $isLiteral) {
                $item->value = $literals[$item->key->value];
            }
        }
    }
}
