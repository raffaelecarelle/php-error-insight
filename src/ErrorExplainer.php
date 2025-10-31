<?php

declare(strict_types=1);

namespace PhpErrorInsight;

use PhpErrorInsight\Internal\ErrorHandler;

/**
 * Library facade used to register/unregister global error handlers.
 *
 * Why it exists:
 * - Concentrates the bootstrap logic so consumers do not need to know about internal classes.
 * - Ensures idempotent registration and safe unregistration, which is critical in test suites
 *   and long-running processes where duplicate handlers can lead to hard-to-debug behavior.
 */
final class ErrorExplainer
{
    /**
     * Single global instance guard.
     *
     * We do not expose the handler itself; keeping a boolean would be brittle.
     * Holding config here also enables getConfig() for diagnostic purposes.
     */
    private static ?self $instance = null;

    private function __construct(private readonly Config $config)
    {
    }

    /**
     * Register global handlers to intercept PHP errors and exceptions.
     *
     * Why this design:
     * - We must read any previously installed handlers to chain them later.
     * - We avoid changing handlers when the feature is disabled to preserve host behavior.
     * - We keep registration idempotent to avoid stacking multiple handlers.
     *
     * @param array<string, mixed> $options user options that override environment variables
     */
    public static function register(array $options = []): self
    {
        if (self::$instance instanceof self) {
            return self::$instance; // idempotent: no double install
        }

        $config = Config::fromEnvAndArray($options);
        if (!$config->enabled) {
            // When disabled, we explicitly avoid installing any handlers to prevent side effects.
            // Still create and keep an instance so consumers can query the effective configuration.
            $unused = new ErrorHandler($config, null, null); // primes dependencies without global effects
            self::$instance = new self($config);

            return self::$instance;
        }

        // Temporarily set handlers just to capture any previously installed callbacks; then restore.
        $prevError = set_error_handler(static fn (int $errno, string $errstr, string $errfile, int $errline): bool => false
            // placeholder to retrieve the previous handler only
        );

        if (null !== $prevError) {
            restore_error_handler(); // restore immediately, we will replace with a composed handler below
        }

        $prevEx = set_exception_handler(static function (): void {
            // placeholder to read previous, will be replaced immediately
        });

        if (null !== $prevEx) {
            restore_exception_handler();
        }

        // Create our handler composed with the previous ones (if any) to respect existing behavior.
        $handler = new ErrorHandler($config, $prevError, $prevEx);

        set_error_handler($handler->handleError(...));
        set_exception_handler($handler->handleException(...));
        register_shutdown_function([$handler, 'handleShutdown']);

        self::$instance = new self($config);

        return self::$instance;
    }

    /**
     * Restore previous handlers if any.
     *
     * Why the @-operator: in hostile environments or tests, there may be nothing to restore; suppressing
     * warnings avoids polluting output while we clean up. The method remains idempotent by design.
     */
    public static function unregister(): void
    {
        if (!self::$instance instanceof self) {
            return; // nothing to do
        }

        // Best-effort restore
        @restore_error_handler();
        @restore_exception_handler();
        self::$instance = null;
    }

    /**
     * Expose the effective configuration for diagnostics and rendering decisions.
     */
    public static function getConfig(): ?Config
    {
        return self::$instance?->config;
    }
}
