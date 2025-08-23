<?php

declare(strict_types=1);

namespace ErrorExplainer\Internal;

use ErrorExplainer\Config;
use ErrorExplainer\Contracts\ExplainerInterface;
use ErrorExplainer\Contracts\RendererInterface;
use ErrorExplainer\Contracts\StateDumperInterface;
use Throwable;

final class ErrorHandler
{
    /** @var callable|null */
    private $prevErrorHandler;
    /** @var callable|null */
    private $prevExceptionHandler;

    private Config $config;
    private ExplainerInterface $explainer;
    private RendererInterface $renderer;
    private StateDumperInterface $stateDumper;

    /**
     * @param Config $config
     * @param callable|null $prevErrorHandler
     * @param callable|null $prevExceptionHandler
     * @param ExplainerInterface|null $explainer
     * @param RendererInterface|null $renderer
     * @param StateDumperInterface|null $stateDumper
     */
    public function __construct(Config $config, $prevErrorHandler, $prevExceptionHandler, ?ExplainerInterface $explainer = null, ?RendererInterface $renderer = null, ?StateDumperInterface $stateDumper = null)
    {
        $this->config = $config;
        $this->prevErrorHandler = $prevErrorHandler;
        $this->prevExceptionHandler = $prevExceptionHandler;
        $this->explainer = $explainer ?? new Explainer();
        $this->renderer = $renderer ?? new Renderer();
        $this->stateDumper = $stateDumper ?? new StateDumper();
    }

    /**
     * @param int $severity
     * @param string $message
     * @param string|null $file
     * @param int|null $line
     */
    public function handleError($severity, $message, $file = null, $line = null): bool
    {
        // Respect @-operator
        if (error_reporting() === 0) {
            return false;
        }
        if (!$this->config->enabled) {
            return false;
        }

        // Capture full backtrace with function arguments to allow state inspection
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
        // Remove current frame(s)
        array_shift($trace);

        $exp = $this->explainer->explain('error', (string)$message, (string)$file, (int)$line, $trace, (int)$severity, $this->config);
        // Attach extended state dump
        $exp['state'] = $this->stateDumper->collectState($trace);
        $this->renderer->render($exp, $this->config, 'error', false);

        // Chain to previous handler if exists
        if (is_callable($this->prevErrorHandler)) {
            try {
                call_user_func($this->prevErrorHandler, $severity, $message, $file, $line);
            } catch (Throwable $e) {
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
            } catch (Throwable $chainEx) {
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
        if (!$err) {
            return;
        }
        $severity = isset($err['type']) ? (int)$err['type'] : 0;
        if (!self::isFatal($severity)) {
            return;
        }
        $message = isset($err['message']) ? (string)$err['message'] : 'Fatal error';
        $file = isset($err['file']) ? (string)$err['file'] : null;
        $line = isset($err['line']) ? (int)$err['line'] : null;

        // Build a backtrace at shutdown to aid debugging
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
        if (!empty($trace)) {
            array_shift($trace);
        }
        $exp = $this->explainer->explain('shutdown', $message, $file, $line, $trace, $severity, $this->config);
        $exp['state'] = $this->stateDumper->collectState($trace);
        $this->renderer->render($exp, $this->config, 'shutdown', true);
    }

    private static function isFatal(int $type): bool
    {
        return in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
    }
}
