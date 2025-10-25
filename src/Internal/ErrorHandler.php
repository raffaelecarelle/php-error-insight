<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal;

use PhpErrorInsight\Config;
use PhpErrorInsight\Contracts\ExplainerInterface;
use PhpErrorInsight\Contracts\RendererInterface;
use PhpErrorInsight\Contracts\StateDumperInterface;
use Throwable;

use function call_user_func;
use function in_array;
use function is_callable;

use const DEBUG_BACKTRACE_PROVIDE_OBJECT;
use const E_COMPILE_ERROR;
use const E_CORE_ERROR;
use const E_ERROR;
use const E_PARSE;
use const E_RECOVERABLE_ERROR;
use const E_USER_ERROR;

/**
 * Coordinates the flow between error capture, explanation building, state dumping and rendering.
 *
 * Why a dedicated class:
 * - Keeps global handler callbacks thin while enabling dependency injection for testability and extension.
 * - Decouples concerns (explain, render, dump) through interfaces so custom implementations can be plugged in.
 */
final class ErrorHandler
{
    /** @var callable|null */
    private $prevErrorHandler;

    /** @var callable|null */
    private $prevExceptionHandler;

    public function __construct(
        private readonly Config $config,
        ?callable $prevErrorHandler = null,
        ?callable $prevExceptionHandler = null,
        private readonly ExplainerInterface $explainer = new Explainer(),
        private readonly RendererInterface $renderer = new Renderer(),
        private readonly StateDumperInterface $stateDumper = new StateDumper()
    ) {
        // Why we store previous handlers: to preserve host application semantics by chaining.
        $this->prevErrorHandler = $prevErrorHandler;
        $this->prevExceptionHandler = $prevExceptionHandler;
    }

    /**
     * Global error handler callback.
     *
     * Why the early exits: respect the @-operator and disabled mode to avoid unexpected side effects.
     */
    public function handleError(int $severity, string $message, ?string $file = null, ?int $line = null): bool
    {
        // Respect @-operator:
        // - PHP < 8.0: error_reporting() returns 0 when @ is used
        // - PHP >= 8.0: error_reporting() returns E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR | E_PARSE (4437)
        $errorReporting = error_reporting();
        $suppressedMask = E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR | E_PARSE;

        if (0 === $errorReporting || $errorReporting === $suppressedMask) {
            return true; // Error was silenced with @, handle it by doing nothing
        }

        if (!$this->config->enabled) {
            return false;
        }

        // Capture full backtrace with function arguments to allow state inspection.
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
        // Remove current frame(s) to focus on the userland call stack.
        array_shift($trace);

        $exp = $this->explainer->explain('error', $message, (string) $file, (int) $line, $trace, $severity, $this->config);
        // Attach extended state dump so the renderer can surface useful context.
        $exp['state'] = $this->stateDumper->collectState($trace);
        $this->renderer->render($exp, $this->config, 'error', false);

        // Best-effort chain to any previous handler to keep compatibility with the host app.
        $this->chainPreviousErrorHandler($severity, $message, $file, $line);

        return true; // signal that the error has been handled
    }

    /**
     * Global exception handler callback.
     *
     * Why rethrow when disabled without previous handler: mimic default PHP behavior when nothing else can handle it.
     */
    public function handleException(Throwable $e): void
    {
        if (!$this->config->enabled) {
            if (is_callable($this->prevExceptionHandler)) {
                call_user_func($this->prevExceptionHandler, $e);

                return;
            }

            // default behavior if no previous
            throw $e; // rethrow
        }

        $exp = $this->explainer->explain('exception', $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTrace(), null, $this->config);
        $exp['state'] = $this->stateDumper->collectState($e->getTrace());
        $this->renderer->render($exp, $this->config, 'exception', false);

        // Best-effort chain to any previous handler
        $this->chainPreviousExceptionHandler($e);
    }

    /**
     * Shutdown handler used to catch fatal errors that bypass the normal error mechanism.
     */
    public function handleShutdown(): void
    {
        if (!$this->config->enabled) {
            return;
        }

        $err = error_get_last();
        if (null === $err) {
            return;
        }

        $severity = $err['type'];
        if (!$this->isFatal($severity)) {
            return;
        }

        $message = $err['message'];
        $file = $err['file'];
        $line = $err['line'];

        // Build a backtrace at shutdown to aid debugging; may be partial but still useful.
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
        if ([] !== $trace) {
            array_shift($trace);
        }

        $exp = $this->explainer->explain('shutdown', $message, $file, $line, $trace, $severity, $this->config);
        $exp['state'] = $this->stateDumper->collectState($trace);
        $this->renderer->render($exp, $this->config, 'shutdown', true);
    }

    /**
     * Determines whether a PHP error type is fatal at shutdown time.
     */
    private function isFatal(int $type): bool
    {
        return in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
    }

    /**
     * Chain to the previous error handler if any, protecting the main flow from its exceptions.
     */
    private function chainPreviousErrorHandler(int $severity, string $message, ?string $file, ?int $line): void
    {
        if (!is_callable($this->prevErrorHandler)) {
            return;
        }

        try {
            call_user_func($this->prevErrorHandler, $severity, $message, $file, $line);
        } catch (Throwable) {
            // Intentionally ignored: failing a previous handler should not compromise ours.
        }
    }

    /**
     * Chain to the previous exception handler if any, shielding from its failures.
     */
    private function chainPreviousExceptionHandler(Throwable $e): void
    {
        if (!is_callable($this->prevExceptionHandler)) {
            return;
        }

        try {
            call_user_func($this->prevExceptionHandler, $e);
        } catch (Throwable) {
            // Intentionally ignored.
        }
    }
}
