<?php

namespace fbt\Transform;

use fbt\Services\CollectFbtsService;
use fbt\Transform\FbtTransform\Translate\IntlVariations;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;

class NodeVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node)
    {
        if ($node instanceof FuncCall
            && $node->name instanceof Name
            && $node->name->toString() === 'fbt') {
            if (isset($node->args[2]) && $node->args[2]->value instanceof Node\Expr\Array_) {
                foreach ($node->args[2]->value->items as $attribute) {
                    if ($attribute->key->value === 'subject') {
                        if (! ($attribute->value instanceof String_)) {
                            $attribute->value = new LNumber(IntlVariations::GENDER_MALE);
                        }
                    }
                }
            }
        }
        if ($node instanceof StaticCall
            && $node->class instanceof Name
            && in_array($node->class->toString(), ['fbt', 'fbt\fbt'])) {
            switch ($node->name->toString()) {
                case "param":
                    if (! CollectFbtsService::matchFbtCalls($node->args[1]->value)
                        && ! ($node->args[1]->value instanceof String_)) {
                        $node->args[1] = new String_('123');
                    }

                    break;
                case "plural":
                case "pronoun":
                    if (! ($node->args[1]->value instanceof LNumber)) {
                        $node->args[1] = new LNumber(1);
                    }

                    break;
                case "name":
                    if (! ($node->args[1]->value instanceof String_)) {
                        $node->args[1] = new String_('name');
                    }
                    if (! ($node->args[2]->value instanceof LNumber)) {
                        $node->args[2] = new LNumber(1);
                    }

                    break;
            }
        }
    }
}
