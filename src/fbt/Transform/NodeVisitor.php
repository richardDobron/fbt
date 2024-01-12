<?php

namespace fbt\Transform;

use fbt\Services\CollectFbtsService;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;

class NodeVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node)
    {
        if ($node instanceof StaticCall
            && $node->class instanceof Name
            && in_array($node->class->toString(), ['fbt', 'fbt\fbt'])) {
            switch ($node->name->toString()) {
                case "param":
                    if (! CollectFbtsService::matchFbtCalls($node->args[1]->value)
                        && ! ($node->args[1]->value instanceof String_)) {
                        $node->args[1] = new String_('value');
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
