<?php

namespace fbt\Services;

use fbt\Exceptions\FbtParserException;
use fbt\FbtConfig;
use PhpParser\Node\Identifier;

use function fbt\rsearch;

use fbt\Runtime\Shared\FbtHooks;
use fbt\Transform\NodeVisitor;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class CollectFbtsService
{
    /**
     * @var \PhpParser\Parser
     */
    protected $parser;
    /**
     * @var NodeTraverser
     */
    protected $traverser;
    /**
     * @var NodeFinder
     */
    protected $nodeFinder;
    /**
     * @var Standard
     */
    protected $printer;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor(new NodeVisitor());
        $this->printer = new Standard();
        $this->nodeFinder = new NodeFinder();
    }

    /**
     * @throws \Throwable
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     * @throws \fbt\Exceptions\FbtParserException
     */
    public function collectFromFiles(string $path, string $src, string $fbtCommonPath)
    {
        $fbtDir = $path . '/';

        if (! is_dir($fbtDir)) {
            mkdir($fbtDir, 0755, true);
        }

        FbtConfig::set('path', $path);
        FbtConfig::set('fbtCommonPath', $fbtCommonPath);

        foreach (rsearch($src, '/.php$/') as $path) {
            $this->collectFromOneFile(file_get_contents($path), $path);
        }

        FbtHooks::storePhrases();
    }

    /**
     * @throws \Throwable
     * @throws \fbt\Exceptions\FbtParserException
     */
    protected function collectFromOneFile(string $source, string $path)
    {
        if (! preg_match('/fbt(::c)?\s*\(/', $source)) {
            return;
        }

        $ast = $this->parser->parse($source);
        $ast = $this->traverser->traverse($ast);

        /** @var StaticCall[] $translateFunctionCalls */
        $translateFunctionCalls = $this->nodeFinder->find($ast, function (Node $node) {
            return ($node instanceof FuncCall
                    && $node->name instanceof Name
                    && $node->name->toString() === 'fbt')
                || ($node instanceof StaticCall
                    && $node->class instanceof Name
                    && $node->name instanceof Identifier
                    && in_array($node->class->toString(), ['fbt', 'fbt\\fbt'])
                    && $node->name->toString() === 'c');
        });

        foreach ($translateFunctionCalls as $translateFunctionCall) {
            $code = $this->printer->prettyPrintExpr($translateFunctionCall);

            try {
                if ($translateFunctionCall->args[0]->value instanceof Ternary) {
                    throw new FbtParserException("Unexpected node type: Ternary. fbt()'s first argument should be a string literal, a construct like fbt::param() or an array of those called in file.php(1).");
                }

                @eval(<<<CODE
use fbt\\fbt;
use function fbt\\createElement;

(string)$code;
CODE
                );
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $message = preg_replace('/^(.+?) on line .+$/', '$1', $message);

                echo preg_replace('/(called in ).+?\(\d+\)/', '$1' . $path . '(' . $translateFunctionCall->getStartLine() . ')', $message);

                echo PHP_EOL . PHP_EOL;
            }
        }
    }
}
