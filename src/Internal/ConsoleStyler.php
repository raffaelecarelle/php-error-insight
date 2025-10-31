<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal;

use PhpErrorInsight\Internal\Util\ArrayUtil;
use PhpErrorInsight\Internal\Util\StringUtil;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

use function is_string;

/**
 * ConsoleStyler defines style tags and helps produce tagged strings.
 *
 * No raw ANSI sequences are emitted here. Use Symfony Console Output to render.
 */
final class ConsoleStyler
{
    public function __construct(
        private readonly ArrayUtil $arr = new ArrayUtil(),
        private readonly StringUtil $str = new StringUtil(),
    ) {
    }

    /**
     * Register our custom styles into a Symfony Output formatter.
     *
     * @param array<string,mixed>|null $config styles override structure (Config::$consoleColors)
     */
    public function registerStyles(OutputFormatterInterface $formatter, ?array $config = null): void
    {
        $styles = $this->arr->isArray($config) && isset($config['styles']) && $this->arr->isArray($config['styles']) ? $config['styles'] : [];

        // Helper to read [fg, bg, options[]]
        $spec = function (array $styles, string $key, array $def): array {
            $v = $styles[$key] ?? null;
            if (!$this->arr->isArray($v)) {
                return $def;
            }

            return [
                (string) ($v[0] ?? $def[0]),
                null !== ($v[1] ?? null) ? (string) $v[1] : ($def[1] ?? null),
                $this->arr->isArray($v[2] ?? null) ? $v[2] : ($def[2] ?? []),
            ];
        };

        // Core text styles (defaults preserve current behavior)
        [$fg, $bg, $opt] = $spec($styles, 'yellow', ['yellow', null, []]);
        $formatter->setStyle('pe-yellow', new OutputFormatterStyle($fg, $bg, $opt));
        [$fg, $bg, $opt] = $spec($styles, 'green', ['green', null, []]);
        $formatter->setStyle('pe-green', new OutputFormatterStyle($fg, $bg, $opt));
        [$fg, $bg, $opt] = $spec($styles, 'blue', ['blue', null, []]);
        $formatter->setStyle('pe-blue', new OutputFormatterStyle($fg, $bg, $opt));
        [$fg, $bg, $opt] = $spec($styles, 'dim', ['gray', null, []]);
        $formatter->setStyle('pe-dim', new OutputFormatterStyle($fg, $bg, $opt));
        [$fg, $bg, $opt] = $spec($styles, 'boldwhite', ['white', null, ['bold']]);
        $formatter->setStyle('pe-boldwhite', new OutputFormatterStyle($fg, $bg, $opt));

        // Title, Suggestion, Stack, Location semantic tags (new; default map to old colors)
        [$fg, $bg, $opt] = $spec($styles, 'title', ['white', null, ['bold']]);
        $formatter->setStyle('pe-title', new OutputFormatterStyle($fg, $bg, $opt));
        [$fg, $bg, $opt] = $spec($styles, 'suggestion', ['green', null, []]);
        $formatter->setStyle('pe-suggestion', new OutputFormatterStyle($fg, $bg, $opt));
        [$fg, $bg, $opt] = $spec($styles, 'stack', ['yellow', null, []]);
        $formatter->setStyle('pe-stack', new OutputFormatterStyle($fg, $bg, $opt));
        [$fg, $bg, $opt] = $spec($styles, 'location', ['blue', null, []]);
        $formatter->setStyle('pe-location', new OutputFormatterStyle($fg, $bg, $opt));

        // Gutter styles
        [$fg, $bg, $opt] = $spec($styles, 'gutter_hl', ['white', 'red', ['bold']]);
        $formatter->setStyle('pe-gutter-hl', new OutputFormatterStyle($fg, $bg, $opt));
        [$fg, $bg, $opt] = $spec($styles, 'gutter_num', ['gray', null, []]);
        $formatter->setStyle('pe-gutter-num', new OutputFormatterStyle($fg, $bg, $opt));
        [$fg, $bg, $opt] = $spec($styles, 'gutter_sep', ['gray', null, []]);
        $formatter->setStyle('pe-gutter-sep', new OutputFormatterStyle($fg, $bg, $opt));

        // Severity backgrounds: build tags like pe-header-<color>
        $sev = $this->arr->isArray($config) && isset($config['severity']) && $this->arr->isArray($config['severity']) ? $config['severity'] : [];
        $bgColors = ['red', 'yellow', 'blue'];
        foreach (['error', 'warning', 'info'] as $k) {
            $v = $sev[$k] ?? null;
            if (is_string($v) && '' !== $v) {
                $bgColors[] = $this->str->toLower($v);
            }
        }

        $bgColors = $this->arr->unique($bgColors);
        foreach ($bgColors as $c) {
            $formatter->setStyle('pe-header-' . $c, new OutputFormatterStyle('white', $c, ['bold']));
        }
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

    /**
     * Default gutter line number (non-highlighted lines).
     */
    public function gutterNumber(string $s): string
    {
        return $this->tag('pe-gutter-num', $s);
    }

    /**
     * Gutter vertical separator style.
     */
    public function gutterSeparator(string $s): string
    {
        return $this->tag('pe-gutter-sep', $s);
    }

    // Convenience wrappers for semantic styles
    public function title(string $s): string
    {
        return $this->tag('pe-title', $s);
    }

    public function suggestion(string $s): string
    {
        return $this->tag('pe-suggestion', $s);
    }

    public function stack(string $s): string
    {
        return $this->tag('pe-stack', $s);
    }

    public function location(string $s): string
    {
        return $this->tag('pe-location', $s);
    }

    /**
     * Bold white text on named background color (e.g., 'red','yellow','blue', ...).
     */
    public function headerOnBgName(string $bgName, string $s): string
    {
        return $this->tag('pe-header-' . $this->str->toLower($bgName), $s);
    }
}
