<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Model;

use PhpErrorInsight\Internal\Util\SessionUtil;

use function is_array;
use function is_string;

use const E_COMPILE_ERROR;
use const E_COMPILE_WARNING;
use const E_CORE_ERROR;
use const E_CORE_WARNING;
use const E_DEPRECATED;
use const E_ERROR;
use const E_NOTICE;
use const E_PARSE;
use const E_RECOVERABLE_ERROR;
use const E_STRICT;
use const E_USER_DEPRECATED;
use const E_USER_ERROR;
use const E_USER_NOTICE;
use const E_USER_WARNING;
use const E_WARNING;

final class Explanation
{
    /**
     * @param array{message?:string,file?:string,line?:int}|array{} $original
     * @param list<string>                                          $suggestions
     */
    private function __construct(
        public readonly array $original,
        public readonly array $suggestions,
        public readonly string $severityLabel,
        public readonly string $title,
        public readonly string $details,
        public readonly Trace $trace
    ) {
    }

    /**
     * @param array<int, string>                  $suggestions
     * @param array<int,array<string,mixed>>|null $trace
     */
    public static function make(string $title, ?int $severity, string $message, string $file, ?int $line, ?array $trace, array $suggestions = [], string $details = ''): self
    {
        return self::fromArray([
            'title' => $title,
            'details' => $details,
            'suggestions' => $suggestions,
            'severityLabel' => self::severityToString($severity ?? E_USER_ERROR),
            'original' => [
                'message' => $message,
                'file' => $file,
                'line' => $line,
            ],
            'trace' => $trace,
            'globals' => [
                'get' => $_GET,
                'post' => $_POST,
                'cookie' => $_COOKIE,
                'session' => (new SessionUtil())->getSessionOrEmpty(),
            ],
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'details' => $this->details,
            'suggestions' => $this->suggestions,
            'severityLabel' => $this->severityLabel,
            'original' => [
                'message' => $this->message(),
                'file' => $this->file(),
                'line' => $this->line(),
            ],
            'trace' => $this->trace,
            'globals' => [
                'get' => $_GET,
                'post' => $_POST,
                'cookie' => $_COOKIE,
                'session' => (new SessionUtil())->getSessionOrEmpty(),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $original = isset($data['original']) && is_array($data['original']) ? $data['original'] : [];
        $title = isset($original['message']) && is_string($original['message']) && '' !== $original['message']
            ? $original['message']
            : (string) ($data['title'] ?? '');

        $traceArr = isset($data['trace']) && is_array($data['trace']) ? $data['trace'] : [];

        return new self(
            $original,
            isset($data['suggestions']) && is_array($data['suggestions']) ? $data['suggestions'] : [],
            (string) ($data['severityLabel'] ?? 'Error'),
            $title,
            (string) ($data['details'] ?? ''),
            Trace::fromArray($traceArr),
        );
    }

    public function file(): string
    {
        return $this->original['file'] ?? '';
    }

    public function line(): int
    {
        return $this->original['line'] ?? 0;
    }

    public function message(): string
    {
        return $this->original['message'] ?? '';
    }

    private static function severityToString(int $severity): string
    {
        return match ($severity) {
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'PARSE',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'E Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated',
            default => $severity,
        };
    }
}
