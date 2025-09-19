<?php

declare(strict_types=1);

namespace ErrorExplainer\Internal;

use ErrorExplainer\Config;
use ErrorExplainer\Contracts\ExplainerInterface;
use ErrorExplainer\Contracts\RendererInterface;
use ErrorExplainer\Contracts\StateDumperInterface;
use Throwable;

use function call_user_func;
use function in_array;
use function is_callable;

use const DEBUG_BACKTRACE_PROVIDE_OBJECT;
use const E_COMPILE_ERROR;
use const E_CORE_ERROR;
use const E_ERROR;
use const E_PARSE;

final class ErrorHandler
{
    /** @var callable|null */
    private $prevErrorHandler;

    /** @var callable|null */
    private $prevExceptionHandler;

    /**
     * @param callable|null $prevErrorHandler
     * @param callable|null $prevExceptionHandler
     */
    public function __construct(private readonly Config $config, $prevErrorHandler, $prevExceptionHandler, private readonly ExplainerInterface $explainer = new Explainer(), private readonly RendererInterface $renderer = new Renderer(), private readonly StateDumperInterface $stateDumper = new StateDumper())
    {
        $this->prevErrorHandler = $prevErrorHandler;
        $this->prevExceptionHandler = $prevExceptionHandler;
    }

    /**
     * @param int         $severity
     * @param string      $message
     * @param string|null $file
     * @param int|null    $line
     */
    public function handleError($severity, $message, $file = null, $line = null): bool
    {
        // Respect @-operator
        if (0 === error_reporting()) {
            return false;
        }

        if (!$this->config->enabled) {
            return false;
        }

        // Capture full backtrace with function arguments to allow state inspection
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
        // Remove current frame(s)
        array_shift($trace);

        $exp = $this->explainer->explain('error', (string) $message, (string) $file, (int) $line, $trace, (int) $severity, $this->config);
        // Attach extended state dump
        $exp['state'] = $this->stateDumper->collectState($trace);
        $this->renderer->render($exp, $this->config, 'error', false);

        // Chain to previous handler if exists
        if (is_callable($this->prevErrorHandler)) {
            try {
                call_user_func($this->prevErrorHandler, $severity, $message, $file, $line);
            } catch (Throwable) {
                // ignore
            }
        }

        return true; // handled
    }

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

        // Chain to previous handler if exists
        if (is_callable($this->prevExceptionHandler)) {
            try {
                call_user_func($this->prevExceptionHandler, $e);
            } catch (Throwable) {
                // ignore
            }
        }
    }

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

        // Build a backtrace at shutdown to aid debugging
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
        if ([] !== $trace) {
            array_shift($trace);
        }

        $exp = $this->explainer->explain('shutdown', $message, $file, $line, $trace, $severity, $this->config);
        $exp['state'] = $this->stateDumper->collectState($trace);
        $this->renderer->render($exp, $this->config, 'shutdown', true);
    }

    private function isFatal(int $type): bool
    {
        return in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
    }
}
