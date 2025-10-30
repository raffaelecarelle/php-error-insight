<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

/**
 * ConsoleStyler defines style tags and helps produce tagged strings.
 *
 * No raw ANSI sequences are emitted here. Use Symfony Console Output to render.
 */
final class ConsoleStyler
{
    /**
     * Register our custom styles into a Symfony Output formatter.
     */
    public function registerStyles(OutputFormatterInterface $formatter): void
    {
        $formatter->setStyle('pe-yellow', new OutputFormatterStyle('yellow'));
        $formatter->setStyle('pe-green', new OutputFormatterStyle('green'));
        $formatter->setStyle('pe-blue', new OutputFormatterStyle('blue'));
        $formatter->setStyle('pe-dim', new OutputFormatterStyle('gray'));
        $formatter->setStyle('pe-boldwhite', new OutputFormatterStyle('white', null, ['bold']));
        $formatter->setStyle('pe-header-red', new OutputFormatterStyle('white', 'red', ['bold']));
        $formatter->setStyle('pe-header-yellow', new OutputFormatterStyle('white', 'yellow', ['bold']));
        $formatter->setStyle('pe-header-blue', new OutputFormatterStyle('white', 'blue', ['bold']));
        $formatter->setStyle('pe-gutter-hl', new OutputFormatterStyle('white', 'red', ['bold']));
    }

    private function tag(string $name, string $s): string
    {
        return '<' . $name . '>' . $s . '</' . $name . '>';
    }

    public function yellow(string $s): string
    {
        return $this->tag('pe-yellow', $s);
    }

    public function green(string $s): string
    {
        return $this->tag('pe-green', $s);
    }

    public function blue(string $s): string
    {
        return $this->tag('pe-blue', $s);
    }

    public function dim(string $s): string
    {
        return $this->tag('pe-dim', $s);
    }

    public function boldWhite(string $s): string
    {
        return $this->tag('pe-boldwhite', $s);
    }

    /**
     * Bold white text on severity background (red/yellow/blue), code must be 41/43/44.
     */
    public function headerOnBgCode(string $bgCode, string $s): string
    {
        $tag = match ($bgCode) {
            '41' => 'pe-header-red',
            '43' => 'pe-header-yellow',
            default => 'pe-header-blue',
        };

        return $this->tag($tag, $s);
    }

    /**
     * White bold text on red background, used for gutter current line number.
     */
    public function gutterHighlight(string $s): string
    {
        return $this->tag('pe-gutter-hl', $s);
    }
}
