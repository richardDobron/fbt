<?php

namespace fbt\Services;

use fbt\Exceptions\FbtInvalidConfigurationException;
use fbt\Exceptions\FbtParserException;
use fbt\FbtConfig;
use function fbt\rsearch;

use fbt\Runtime\Shared\FbtHooks;

use fbt\Transform\NodeVisitor;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Identifier;
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
    /**
     * @var int
     */
    protected $files = 0;
    /**
     * @var int
     */
    protected $errors = 0;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor(new NodeVisitor());
        $this->printer = new Standard();
        $this->nodeFinder = new NodeFinder();
    }

    /**
     * @param string $path
     * @param string $src
     * @param null|string $fbtCommonPath
     * @param bool $cleanCache
     * @return void
     * @throws \Throwable
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     * @throws \fbt\Exceptions\FbtParserException
     */
    public function collectFromFiles(string $path, string $src, $fbtCommonPath, bool $cleanCache)
    {
        $fbtDir = $path . '/';
        $file = $fbtDir . '.source_strings.json';

        if (! is_dir($fbtDir)) {
            mkdir($fbtDir, 0755, true);
        }

        if ($cleanCache && file_exists($file)) {
            unlink($file);
        }

        FbtConfig::set('path', $path);
        FbtConfig::set('fbtCommonPath', $fbtCommonPath);

        foreach (rsearch($src, '/.php$/') as $path) {
            $this->collectFromOneFile(file_get_contents($path), $path);
        }

        FbtHooks::storePhrases();
    }

    protected function compileCode(Expr $fbtFunctionClassCall): string
    {
        $code = $this->printer->prettyPrintExpr($fbtFunctionClassCall);

        return preg_replace('/(\\\*|\b)(fbt\\\+)fbt/', 'fbt', $code);
    }

    public static function matchFbtCalls(Node $node): bool
    {
        return ($node instanceof FuncCall
                && $node->name instanceof Name
                && $node->name->toString() === 'fbt')
            || (
                $node instanceof StaticCall
                && $node->class instanceof Name
                && $node->name instanceof Identifier
                && in_array($node->class->toString(), ['fbt', 'fbt\\fbt'])
                && $node->name->toString() === 'c'
            );
    }

    /**
     * @throws \Throwable
     * @throws \fbt\Exceptions\FbtParserException
     */
    protected function collectFromOneFile(string $source, string $path): bool
    {
        if (! preg_match('/fbt(::c)?\s*\(/', $source)) {
            echo "\033[0;37m$path \033[0m" . PHP_EOL;

            return false;
        }

        $this->files++;

        $ast = $this->parser->parse($source);
        $ast = $this->traverser->traverse($ast);

        /** @var StaticCall[] $fbtFunctionCalls */
        $fbtFunctionCalls = $this->nodeFinder->find($ast, function (Node $node) {
            return self::matchFbtCalls($node);
        });

        echo "\033[15m$path \033[0m" . PHP_EOL;

        foreach ($fbtFunctionCalls as $fbtFunctionCall) {
            $code = $this->compileCode($fbtFunctionCall);
            $line = $fbtFunctionCall->getLine();

            try {
                if ($fbtFunctionCall->args[0]->value instanceof Ternary) {
                    throw new FbtParserException("Unexpected node type: Ternary. fbt()'s first argument should be a string literal, a construct like fbt::param() or an array of those called in file.php(1).");
                }

                @eval(<<<CODE
use fbt\\fbt;
use function fbt\\createElement;

\$fbt = $code;
\$fbt->_trace([
    'file' => '$path',
    'line' => $line,
]);
(string)\$fbt;
CODE
                );
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $message = preg_replace('/^(.+?) on line .+$/', '$1', $message);
                $message = preg_replace('/(called in ).+?\(\d+\)/', '$1' . basename($path) . '(' . $line . ')', $message);

                if (! strstr($message, 'called in')) {
                    $message .= ' called in ' . basename($path) . '(' . $line . ')';
                }

                echo "\033[33m" . $message . "\033[0m" . PHP_EOL;

                $this->errors++;
            }
        }

        return true;
    }

    public function __destruct()
    {
        $hashToTexts = array_merge(...array_column(FbtHooks::$sourceStrings['phrases'], 'hashToText'));

        echo PHP_EOL;
        echo "Fbt collection has been completed!" . PHP_EOL . PHP_EOL;

        echo "Source strings: " . count($hashToTexts) . " in " . $this->files . " file(s)" . PHP_EOL;

        if ($this->errors) {
            echo "\033[33mErrors: " . $this->errors . "\033[0m" . PHP_EOL;
        }
    }
}
