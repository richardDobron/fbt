<?php

namespace fbt\Services;

use fbt\Exceptions\FbtParserException;
use fbt\FbtConfig;

use function fbt\rsearch;

use fbt\Runtime\Shared\FbtHooks;
use fbt\Transform\FbtTransform\fbtHash;
use fbt\Transform\FbtTransform\FbtTransform;
use fbt\Transform\NodeVisitor;
use fbt\Util\JsJson;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\InlineHTML;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class CollectFbtsService
{
    /**
     * Used to help detect the usage of the fbt/fbs API inside a file
     * (FbtConstants.js `ModuleNameRegExp`)
     */
    private const FBT_REGEX = '/<fb[st]\b|fb[st](::c)?\s*\(/';

    public const PACKAGER_TYPES = [
        'TEXT' => 'text',
        'PHRASE' => 'phrase',
        'BOTH' => 'both',
        'NONE' => 'none',
    ];

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
    /**
     * @var resource|null
     */
    protected $log;

    public function __construct()
    {
        $parserFactory = new ParserFactory();
        // nikic/php-parser 5 removed create(), 4.18+ has both
        $this->parser = method_exists($parserFactory, "createForNewestSupportedVersion")
            ? $parserFactory->createForNewestSupportedVersion()
            : $parserFactory->create(ParserFactory::PREFER_PHP7);
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor(new NodeVisitor());
        $this->printer = new Standard();
        $this->nodeFinder = new NodeFinder();
        $this->log = defined('STDOUT') ? STDOUT : null;
    }

    /**
     * @throws \Throwable
     * @throws \fbt\Exceptions\FbtInvalidConfigurationException
     * @throws \fbt\Exceptions\FbtParserException
     */
    public function collectFromFiles(string $path, string $src, ?string $fbtCommonPath, bool $cleanCache): void
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
        FbtHooks::$collectCallsites = true;

        $this->collectFromSources([$src]);

        FbtHooks::storePhrases();
    }

    /**
     * Collects the fbt callsites of the given files
     * or directories, or of the source code read from STDIN, and
     * returns the output of collect-fbts.
     *
     * @param string[] $paths
     * @param array{packager?: string, terse?: bool, genFbtNodes?: bool, stdin?: string|null} $options
     *
     * @return array{phrases: array, childParentMappings: object, fbtElementNodes?: array}
     * @throws \Throwable
     */
    public function collect(array $paths, array $options = []): array
    {
        $this->log = defined('STDERR') ? STDERR : null;

        // The phrases are written to the output, not to the source strings
        FbtHooks::register('onTerminating', function () {
            return false;
        });
        FbtHooks::$collectCallsites = true;
        FbtTransform::$phrases = [];
        FbtTransform::$childToParent = [];
        FbtTransform::$fbtElementNodes = [];
        FbtTransform::$collectFbtElementNodes = ! empty($options['genFbtNodes']);

        try {
            if (isset($options['stdin'])) {
                $this->collectFromOneFile($options['stdin'], 'stdin');
            }
            $this->collectFromSources($paths);

            return self::buildCollectFbtOutput(
                FbtTransform::$phrases,
                FbtTransform::$childToParent,
                FbtTransform::$fbtElementNodes,
                $options
            );
        } finally {
            FbtTransform::$phrases = [];
            FbtTransform::$childToParent = [];
            FbtTransform::$fbtElementNodes = [];
            FbtTransform::$collectFbtElementNodes = false;
        }
    }

    /**
     * @param string[] $paths - files or directories
     *
     * @throws \Throwable
     */
    protected function collectFromSources(array $paths): void
    {
        foreach ($paths as $src) {
            if (is_file($src)) {
                $this->collectFromOneFile(
                    substr($src, -6) === '.latte' ? $this->compileLatte($src) : file_get_contents($src),
                    $src
                );

                continue;
            }

            foreach (rsearch($src, '/.php$/') as $path) {
                $this->collectFromOneFile(file_get_contents($path), $path);
            }

            if (class_exists(\Latte\Engine::class)) {
                foreach (rsearch($src, "/.latte$/") as $path) {
                    $this->collectFromOneFile($this->compileLatte($path), $path);
                }
            }
        }
    }

    /**
     * Applies the packagers (collectFbtUtils.js `buildCollectFbtOutput`)
     *
     * js~php diff: phrases are collected with the hashes of the text packager
     */
    public static function buildCollectFbtOutput(array $phrases, array $childParentMappings, array $fbtElementNodes, array $options = []): array
    {
        $packager = $options['packager'] ?? self::PACKAGER_TYPES['TEXT'];
        if (! in_array($packager, self::PACKAGER_TYPES, true)) {
            throw new \InvalidArgumentException('Unrecognized packager option');
        }

        $phrases = array_map(function (array $phrase) use ($packager, $options) {
            if ($packager === self::PACKAGER_TYPES['PHRASE'] || $packager === self::PACKAGER_TYPES['NONE']) {
                unset($phrase['hashToLeaf']);
            }

            if ($packager === self::PACKAGER_TYPES['PHRASE'] || $packager === self::PACKAGER_TYPES['BOTH']) {
                $phrase = [
                    'hash_key' => fbtHash::fbtHashKey($phrase['jsfbt']['t']),
                    'hash_code' => fbtHash::fbtJenkinsHash($phrase['jsfbt']['t']),
                ] + $phrase;
            }

            if (! empty($options['terse'])) {
                unset($phrase['jsfbt']);
            } else {
                $phrase['jsfbt']['t'] = JsJson::toJsObject($phrase['jsfbt']['t']);
            }

            return $phrase;
        }, $phrases);

        $output = [
            'phrases' => $phrases,
            'childParentMappings' => JsJson::toJsObject($childParentMappings),
        ];

        if (! empty($options['genFbtNodes'])) {
            $output['fbtElementNodes'] = $fbtElementNodes;
        }

        return $output;
    }

    /**
     * js~php diff: equivalent of `JSON.stringify(output, null, ' ')`
     */
    public static function toJSON(array $output, bool $pretty): string
    {
        $json = json_encode($output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | ($pretty ? JSON_PRETTY_PRINT : 0));

        return $pretty
            ? preg_replace_callback('/^( {4})+/m', function (array $matches) {
                return str_repeat(' ', (int)(strlen($matches[0]) / 4));
            }, $json)
            : $json;
    }

    public function getErrorCount(): int
    {
        return $this->errors;
    }

    /**
     * @internal
     */
    public static function firstKey(array $range): string
    {
        foreach ($range as $key => $_) {
            return (string)$key;
        }

        return '';
    }

    protected function compileLatte(string $path): string
    {
        try {
            $latte = new \Latte\Engine();

            return $latte->compile($path);
        } catch (\Exception $e) {
            return '';
        }
    }

    protected function compileCode(Expr $fbtFunctionClassCall): string
    {
        $code = $this->printer->prettyPrintExpr($fbtFunctionClassCall);

        return preg_replace('/(\\\*|\b)(fbt\\\+)fbt/', 'fbt', $code);
    }

    public static function isFbtClassName(string $name): bool
    {
        return in_array(ltrim($name, '\\'), ['fbt', 'fbs', 'fbt\\fbt', 'fbt\\fbs'], true);
    }

    public static function matchFbtCalls(Node $node): bool
    {
        if ($node instanceof FuncCall && $node->name instanceof Name) {
            return in_array($node->name->getLast(), ['fbt', 'fbs'], true);
        }

        if ($node instanceof StaticCall && $node->class instanceof Name && $node->name instanceof Identifier) {
            return self::isFbtClassName($node->class->toString()) && $node->name->toString() === 'c';
        }

        return $node instanceof New_ && $node->class instanceof Name && self::isFbtClassName($node->class->toString());
    }

    private function log(string $message): void
    {
        if ($this->log === null) {
            return;
        }

        // The standard output is written by echo (it can be buffered)
        if (defined('STDOUT') && $this->log === STDOUT) {
            echo $message . PHP_EOL;
        } else {
            fwrite($this->log, $message . PHP_EOL);
        }
    }

    /**
     * @throws \Throwable
     * @throws \fbt\Exceptions\FbtParserException
     */
    protected function collectFromOneFile(string $source, string $path): bool
    {
        if (! preg_match(self::FBT_REGEX, $source)) {
            $this->log("\033[0;37m$path \033[0m");

            return false;
        }

        $this->files++;

        try {
            $ast = $this->traverser->traverse($this->parser->parse($source) ?? []);
        } catch (\Throwable $e) {
            $this->reportError($e, $path, 0);

            return true;
        }

        $this->log("\033[15m$path \033[0m");

        /** @var Node[] $nodes */
        $nodes = $this->nodeFinder->find($ast, function (Node $node) {
            return self::matchFbtCalls($node)
                || (($node instanceof InlineHTML || $node instanceof String_) && preg_match('/<fb[st]\b/', $node->value));
        });

        foreach ($nodes as $node) {
            $line = $node->getLine();
            $trace = [
                'file' => $path,
                'line' => $line,
                'line_end' => $node->getEndLine(),
            ];

            // Every callsite is collected (like upstream collect-fbts)
            \fbt\fbt::_purgeCache();

            try {
                if ($node instanceof InlineHTML || $node instanceof String_) {
                    $this->collectFromMarkup($node->value, $trace);

                    continue;
                }

                if (isset($node->args[0]) && $node->args[0]->value instanceof Ternary) {
                    throw new FbtParserException("Unexpected node type: Ternary. fbt()'s first argument should be a string literal, a construct like fbt::param() or an array of those called in file.php(1).");
                }

                $fbt = self::evaluate($this->compileCode($node));
                if ($fbt instanceof \fbt\fbt) {
                    $fbt->_trace($trace);
                    (string)$fbt;
                }
            } catch (\Throwable $e) {
                $this->reportError($e, $path, $line);
            }
        }

        return true;
    }

    /**
     * @throws \Throwable
     */
    private function collectFromMarkup(string $markup, array $trace): void
    {
        if (preg_match_all('/<(fbt|fbs|Fbt)\b.*?<\/\1>/s', $markup, $matches)) {
            foreach ($matches[0] as $element) {
                FbtTransform::transform($element, $trace);
            }
        }
    }

    /**
     * @return mixed
     */
    private static function evaluate(string $code)
    {
        return @eval('use fbt\fbt; use fbt\fbs; use function fbt\createElement; return ' . $code . ';');
    }

    private function reportError(\Throwable $e, string $path, int $line): void
    {
        $message = $e->getMessage();
        $message = preg_replace('/^(.+?) on line .+$/', '$1', $message);
        $message = preg_replace('/(called in ).+?\(\d+\)/', '$1' . basename($path) . '(' . $line . ')', $message);

        if (! strstr($message, 'called in')) {
            $message .= ' called in ' . basename($path) . '(' . $line . ')';
        }

        $this->log("\033[33m" . $message . "\033[0m");

        $this->errors++;
    }

    public function __destruct()
    {
        if ($this->log === null) {
            return;
        }

        $hashToLeaf = array_merge([], ...array_column(FbtHooks::$sourceStrings['phrases'], 'hashToLeaf'));

        $this->log('');
        $this->log("Fbt collection has been completed!" . PHP_EOL);

        if (defined('STDOUT') && $this->log === STDOUT) {
            $this->log("Source strings: " . count($hashToLeaf) . " in " . $this->files . " file(s)");
        }

        if ($this->errors) {
            $this->log("\033[33mErrors: " . $this->errors . "\033[0m");
        }
    }
}
