<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal;

use PhpErrorInsight\Internal\Util\ArrayUtil;
use PhpErrorInsight\Internal\Util\StringUtil;
use PhpErrorInsight\Internal\Util\TokenizerUtil;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

use function is_array;

use const T_CLASS_C;
use const T_CLOSE_TAG;
use const T_COMMENT;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_DIR;
use const T_DNUMBER;
use const T_DOC_COMMENT;
use const T_ENCAPSED_AND_WHITESPACE;
use const T_FILE;
use const T_FUNC_C;
use const T_INLINE_HTML;
use const T_LINE;
use const T_LNUMBER;
use const T_METHOD_C;
use const T_NS_C;
use const T_OBJECT_OPERATOR;
use const T_OPEN_TAG;
use const T_OPEN_TAG_WITH_ECHO;
use const T_PAAMAYIM_NEKUDOTAYIM;
use const T_STRING;
use const T_TRAIT_C;
use const T_VARIABLE;
use const T_WHITESPACE;

/**
 * CodeHighlighter encapsulates PHP tokenization and console styling for code excerpts.
 *
 * It produces Symfony Console style-tagged strings (e.g. <pe-tok-string>...</pe-tok-string>)
 * and can register a theme into an OutputFormatter.
 */
final class CodeHighlighter
{
    public function __construct(
        private readonly ArrayUtil $arr = new ArrayUtil(),
        private readonly StringUtil $str = new StringUtil(),
        private readonly TokenizerUtil $tokenizer = new TokenizerUtil(),
    ) {
    }

    /**
     * Register token styles for the chosen theme into a Symfony Output formatter.
     *
     * Styles are registered with tag names:
     *   pe-tok-string, pe-tok-comment, pe-tok-keyword, pe-tok-default, pe-tok-html
     *   plus: pe-tok-variable, pe-tok-function, pe-tok-method
     *
     * @param array<string, array{0?:string,1?:string|null,2?:array<string>}>|null $overrides
     */
    public function registerStyles(OutputFormatterInterface $formatter, ?array $overrides = null): void
    {
        $palette = $this->getPalette();

        if ($this->arr->isArray($overrides)) {
            // Merge overrides into palette (shallow per key)
            foreach ($overrides as $k => $spec) {
                if ($this->arr->isArray($spec)) {
                    $palette[$k] = [
                        $spec[0] ?? ($palette[$k][0] ?? 'white'),
                        $spec[1] ?? ($palette[$k][1] ?? null),
                        $spec[2] ?? ($palette[$k][2] ?? []),
                    ];
                }
            }
        }

        $formatter->setStyle('pe-tok-string', new OutputFormatterStyle(
            $palette['string'][0] ?? 'green',
            $palette['string'][1] ?? null,
            $palette['string'][2] ?? []
        ));
        $formatter->setStyle('pe-tok-comment', new OutputFormatterStyle(
            $palette['comment'][0] ?? 'gray',
            $palette['comment'][1] ?? null,
            $palette['comment'][2] ?? []
        ));
        $formatter->setStyle('pe-tok-keyword', new OutputFormatterStyle(
            $palette['keyword'][0] ?? 'magenta',
            $palette['keyword'][1] ?? null,
            $palette['keyword'][2] ?? ['bold']
        ));
        $formatter->setStyle('pe-tok-default', new OutputFormatterStyle(
            $palette['default'][0] ?? 'white',
            $palette['default'][1] ?? null,
            $palette['default'][2] ?? []
        ));
        $formatter->setStyle('pe-tok-html', new OutputFormatterStyle(
            $palette['html'][0] ?? 'cyan',
            $palette['html'][1] ?? null,
            $palette['html'][2] ?? ['bold']
        ));
        $formatter->setStyle('pe-tok-variable', new OutputFormatterStyle(
            $palette['variable'][0] ?? 'yellow',
            $palette['variable'][1] ?? null,
            $palette['variable'][2] ?? []
        ));
        $formatter->setStyle('pe-tok-function', new OutputFormatterStyle(
            $palette['function'][0] ?? 'blue',
            $palette['function'][1] ?? null,
            $palette['function'][2] ?? ['bold']
        ));
        $formatter->setStyle('pe-tok-method', new OutputFormatterStyle(
            $palette['method'][0] ?? 'blue',
            $palette['method'][1] ?? null,
            $palette['method'][2] ?? ['underscore']
        ));
    }

    /**
     * Return theme palette: token-category => [fg, bg, options[]].
     * We pick console-friendly approximations of the Dracula palette.
     *
     * @return array<string, array{0:string,1:string|null,2:array<string>}>
     */
    private function getPalette(): array
    {
        return [
            'default' => ['white', null, []],
            'comment' => ['white', null, []],
            'string' => ['yellow', null, []],
            'keyword' => ['magenta', null, ['bold']],
            'html' => ['cyan', null, ['bold']],
            'variable' => ['cyan', null, []],
            'function' => ['blue', null, ['bold']],
            'method' => ['green', null, ['underscore']],
        ];
    }

    /**
     * Tokenize full PHP source and split into lines of [tokenType, tokenValue] pairs.
     *
     * @return array<int, array<int, array{0:string,1:string}>>
     */
    public function tokenizeToLines(string $source): array
    {
        $tokens = $this->tokenizer->tokenize($source);

        // Build categorized tokens with minimal context (look-behind and look-ahead)
        $categorized = [];
        $count = $this->arr->count($tokens);
        for ($i = 0; $i < $count; ++$i) {
            $t = $tokens[$i];
            if ($this->arr->isArray($t)) {
                [$id, $text] = $t;
                $cat = $this->mapPhpTokenToCategory($id);

                // Variables
                if (T_VARIABLE === $id) {
                    $cat = 'variable';
                }

                // Identify function/method calls: T_STRING followed by '(' ignoring whitespace/comments
                if (T_STRING === $id) {
                    // find next non-whitespace/comment token
                    $j = $i + 1;
                    while ($j < $count) {
                        $tn = $tokens[$j];
                        if ($this->arr->isArray($tn) && (T_WHITESPACE === $tn[0] || T_COMMENT === $tn[0] || T_DOC_COMMENT === $tn[0])) {
                            ++$j;
                            continue;
                        }

                        break;
                    }

                    $nextIsParen = ($j < $count) && ('(' === $tokens[$j]);

                    if ($nextIsParen) {
                        // check previous non-whitespace/comment token for -> or :: to distinguish method vs function
                        $k = $i - 1;
                        while ($k >= 0) {
                            $tp = $tokens[$k];
                            if ($this->arr->isArray($tp) && (T_WHITESPACE === $tp[0] || T_COMMENT === $tp[0] || T_DOC_COMMENT === $tp[0])) {
                                --$k;
                                continue;
                            }

                            break;
                        }

                        $isMethod = ($k >= 0 && (('->' === $tokens[$k]) || ($this->arr->isArray($tokens[$k]) && T_OBJECT_OPERATOR === $tokens[$k][0]) || ('::' === $tokens[$k]) || (is_array($tokens[$k]) && T_PAAMAYIM_NEKUDOTAYIM === $tokens[$k][0])));
                        $cat = $isMethod ? 'method' : 'function';
                    }
                }

                $categorized[] = [$cat, $text];
            } else {
                $char = $t;
                $cat = ('"' === $char) ? 'string' : 'keyword';
                $categorized[] = [$cat, $char];
            }
        }

        // Compact consecutive same-category tokens
        $output = [];
        $currentType = null;
        $buffer = '';
        foreach ($categorized as $pair) {
            [$newType, $text] = $pair;
            if (null === $currentType) {
                $currentType = $newType;
            }

            if ($currentType !== $newType) {
                $output[] = [$currentType, $buffer];
                $buffer = '';
                $currentType = $newType;
            }

            $buffer .= $text;
        }

        if (null !== $currentType) {
            $output[] = [$currentType, $buffer];
        }

        // Split to lines
        $lines = [];
        $line = [];
        foreach ($output as $tok) {
            [$type, $value] = $tok;
            $chunks = $this->str->explode("\n", $value);
            foreach ($chunks as $i2 => $chunk) {
                if ($i2 > 0) {
                    $lines[] = $line;
                    $line = [];
                }

                if ('' === $chunk) {
                    continue;
                }

                $line[] = [$type, $chunk];
            }
        }

        $lines[] = $line;

        return $lines;
    }

    /**
     * Map numeric PHP token to simplified categories used by the theme.
     */
    public function mapPhpTokenToCategory(int $tok): string
    {
        // Strings
        if (T_ENCAPSED_AND_WHITESPACE === $tok || T_CONSTANT_ENCAPSED_STRING === $tok) {
            return 'string';
        }

        // Comments
        if (T_COMMENT === $tok || T_DOC_COMMENT === $tok) {
            return 'comment';
        }

        // Inline HTML
        if (T_INLINE_HTML === $tok) {
            return 'html';
        }

        // Whitespace should inherit default color (no highlighting)
        if (T_WHITESPACE === $tok) {
            return 'default';
        }

        // Variables
        if (T_VARIABLE === $tok) {
            return 'variable';
        }

        // Identifiers, numbers and common scalars → default; operators/others → keyword
        return match ($tok) {
            T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG,
            T_STRING,
            T_DIR, T_FILE, T_METHOD_C, T_DNUMBER, T_LNUMBER, T_NS_C, T_LINE, T_CLASS_C, T_FUNC_C, T_TRAIT_C => 'default',
            default => 'keyword',
        };
    }

    /**
     * Convert a token line to a string with Symfony Console style tags.
     * Token categories are mapped to styles registered via registerStyles().
     *
     * @param array<int, array{0:string,1:string}> $tokenLine
     */
    public function colorTokenLineText(array $tokenLine): string
    {
        if ([] === $tokenLine) {
            return '';
        }

        $out = '';
        foreach ($tokenLine as $token) {
            [$type, $value] = $token;
            $style = match ($type) {
                'string' => 'pe-tok-string',
                'comment' => 'pe-tok-comment',
                'keyword' => 'pe-tok-keyword',
                'html' => 'pe-tok-html',
                'variable' => 'pe-tok-variable',
                'function' => 'pe-tok-function',
                'method' => 'pe-tok-method',
                default => 'pe-tok-default',
            };
            // We intentionally do not escape < > here because code may include them for HTML; Symfony style tags are
            // well-formed as we control tag boundaries.
            $out .= '<' . $style . '>' . $value . '</' . $style . '>';
        }

        return $out;
    }
}
