<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Adapter\Render;

use PhpErrorInsight\Config;
use PhpErrorInsight\Contracts\RendererInterface;
use PhpErrorInsight\Internal\CodeHighlighter;
use PhpErrorInsight\Internal\ConsoleStyler;
use PhpErrorInsight\Internal\Model\Explanation;
use PhpErrorInsight\Internal\Util\ArrayUtil;
use PhpErrorInsight\Internal\Util\EnvUtil;
use PhpErrorInsight\Internal\Util\FileUtil;
use PhpErrorInsight\Internal\Util\MathUtil;
use PhpErrorInsight\Internal\Util\OutputUtil;
use PhpErrorInsight\Internal\Util\PathUtil;
use PhpErrorInsight\Internal\Util\StringUtil;
use PhpErrorInsight\Internal\Util\TerminalUtil;
use Symfony\Component\Console\Formatter\OutputFormatter;

use function array_key_exists;

class CliRendererAdapter implements RendererInterface
{
    public function __construct(
        private readonly OutputUtil $out = new OutputUtil(),
        private readonly EnvUtil $env = new EnvUtil(),
        private readonly StringUtil $str = new StringUtil(),
        private readonly ArrayUtil $arr = new ArrayUtil(),
        private readonly PathUtil $path = new PathUtil(),
        private readonly TerminalUtil $terminal = new TerminalUtil(),
        private readonly FileUtil $file = new FileUtil(),
        private readonly MathUtil $math = new MathUtil(),
    ) {
    }

    public function render(array $explanation, Config $config, string $kind, bool $isShutdown): void
    {
        $exp = Explanation::fromArray($explanation);

        $formatter = new OutputFormatter($this->terminal->supportsAnsi());
        $styler = new ConsoleStyler();
        $styler->registerStyles($formatter, $config->consoleColors ?? null);

        $highlighter = new CodeHighlighter();
        $tokenOverrides = null;
        if (null !== $config->consoleColors && array_key_exists('tokens', $config->consoleColors) && [] !== $config->consoleColors['tokens']) {
            /** @var array<string, array{0:string,1:string|null,2:array<string>}> $tokenOverrides */
            $tokenOverrides = $config->consoleColors['tokens'];
        }

        $highlighter->registerStyles($formatter, $tokenOverrides);

        $severity = $exp->severityLabel;
        $message = '' !== $exp->message() ? $exp->message() : $exp->title;
        $file = $exp->file();
        $line = $exp->line();
        $suggestions = $exp->suggestions;

        // Severity background for titles (white text on colored background)
        $sevLower = $this->str->toLower($severity);
        $sevKey = 'info';
        if (
            $this->str->contains($sevLower, 'exception')
            || $this->str->contains($sevLower, 'error')
            || $this->str->contains($sevLower, 'fatal')
            || $this->str->contains($sevLower, 'critical')
        ) {
            $sevKey = 'error';
        } elseif ($this->str->contains($sevLower, 'warning')) {
            $sevKey = 'warning';
        }

        $bgName = null;
        if (
            $this->arr->isArray($config->consoleColors ?? null)
            && isset($config->consoleColors['severity'])
            && $this->arr->isArray($config->consoleColors['severity'])
        ) {
            $map = $config->consoleColors['severity'];
            $bg = $map[$sevKey] ?? null;
            if ('' !== $bg && $this->str->isString($bg)) {
                $bgName = $this->str->toLower($bg);
            }
        }

        if (null === $bgName) {
            $bgName = match ($sevKey) {
                'error' => 'red',
                'warning' => 'yellow',
                default => 'blue',
            };
        }

        $headerOnBg = static function (string $s) use ($styler, $bgName): string {
            return $styler->headerOnBgName($bgName, $s); // bold white on severity background
        };

        // Headers: severity label and message
        echo $formatter->format($headerOnBg(' ' . $severity . ' ')) . "\n\n";
        if ('' !== $this->str->trim($message)) {
            echo $formatter->format($styler->title($message)) . "\n\n";
        }

        // Suggestions
        if ([] !== $suggestions) {
            echo $formatter->format($styler->suggestion('Suggestions:')) . "\n"; // keep literal for tests
            foreach ($suggestions as $sug) {
                if (!$this->str->isString($sug)) {
                    continue;
                }

                echo $formatter->format($styler->suggestion('  - ' . $sug)) . "\n";
            }

            echo "\n";
        }

        // Stack with code excerpt for the first location (original file:line)
        if ('' !== $file && $line > 0) {
            $where = $file . ':' . $line;
            if (null !== $config->projectRoot && '' !== $config->projectRoot && '0' !== $config->projectRoot) {
                $where = $this->path->makeRelative($where, $config->projectRoot);
            }

            echo $formatter->format('at ' . $styler->location($where)) . "\n";
            $excerpt = $this->renderCodeExcerptText($file, $line, 5);
            if ('' !== $excerpt) {
                // format excerpt tags as well
                echo $formatter->format($excerpt) . "\n\n";
            }
        }

        // Render frames without code
        if ([] !== $exp->trace->frames) {
            $i = 1;
            foreach ($exp->trace->frames as $frame) {
                $lineOut = $this->str->sprintf('%d %s %s', $i, $frame->location(), $frame->signature());
                echo $formatter->format($styler->stack($lineOut)) . "\n";
                ++$i;
            }
        }

        if ($config->verbose) {
            // Footer diagnostic info
            $lang = '' !== $config->language && '0' !== $config->language ? $config->language : 'it';
            $model = (string) $config->model;
            $info = 'lang=' . $lang;
            if ('' !== $model) {
                $info .= ' model=' . $model;
            }

            echo "\n" . $formatter->format($styler->dim($info)) . "\n";
        }

        if ($this->env->isCliLike()) {
            $this->out->writeln();
        }
    }

    private function renderCodeExcerptText(?string $file, ?int $line, int $radius = 5): string
    {
        if (null === $file || '' === $file || '0' === $file || (null === $line || 0 === $line) || !$this->file->isFile($file) || $line < 1) {
            return '';
        }

        $source = $this->file->getContents($file);
        if ('' === $source) {
            return '';
        }

        // Normalize newlines and visualize tabs as 4 spaces for alignment in terminals
        $source = $this->str->replaceMany($source, ["\r\n", "\r", "\t"], ["\n", "\n", '    ']);

        $highlighter = new CodeHighlighter();
        $tokenLines = $highlighter->tokenizeToLines($source);
        if ([] === $tokenLines) {
            return '';
        }

        $styler = new ConsoleStyler();
        $total = $this->arr->count($tokenLines);
        $start = $this->math->max(1, $line - $radius);
        $end = $this->math->min($total, $line + $radius);
        $numWidth = $this->str->length((string) $end);

        $out = [];
        for ($ln = $start; $ln <= $end; ++$ln) {
            $prefix = $ln === $line ? $styler->yellow('➜') : ' ';
            $num = $this->str->padLeft((string) $ln, $numWidth, ' ');
            $gutter = $ln === $line ? $styler->gutterHighlight($num) : $styler->gutterNumber($num);

            // Build colored code for this line from tokens
            $colored = $highlighter->colorTokenLineText($tokenLines[$ln - 1]);

            $sep = $styler->gutterSeparator('|');
            $out[] = $this->str->sprintf('%s %s %s %s', $prefix, $gutter, $sep, $colored);
        }

        return $this->str->implode("\n", $out);
    }
}
