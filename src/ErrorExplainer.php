<?php

declare(strict_types=1);

namespace ErrorExplainer;

use ErrorExplainer\Internal\ErrorHandler;

final class ErrorExplainer
{
    private static ?self $instance = null;

    private function __construct(private readonly Config $config)
    {
    }

    /**
     * Register global handlers to intercept PHP errors and exceptions.
     *
     * @param array<string, mixed> $options
     */
    public static function register(array $options = []): self
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }

        $config = Config::fromEnvAndArray($options);
        if (!$config->enabled) {
            // Still create instance but do not install handlers
            $dummy = new ErrorHandler($config, null, null);
            self::$instance = new self($config);

            return self::$instance;
        }

        $prevError = set_error_handler(static fn(int $errno, string $errstr, string $errfile, int $errline): bool =>
            // placeholder to read previous, will be replaced immediately
            false);
        if (null !== $prevError) {
            // restore so we can set our combined handler later
            restore_error_handler();
        }

        $prevEx = set_exception_handler(static function (): void {
            // placeholder to read previous, will be replaced immediately
        });
        if (null !== $prevEx) {
            restore_exception_handler();
        }

        $handler = new ErrorHandler($config, $prevError, $prevEx);

        set_error_handler($handler->handleError(...));
        set_exception_handler($handler->handleException(...));
        register_shutdown_function([$handler, 'handleShutdown']);

        self::$instance = new self($config);

        return self::$instance;
    }

    /**
     * Restore previous handlers if any.
     */
    public static function unregister(): void
    {
        if (!self::$instance instanceof self) {
            return;
        }

        // Best-effort restore
        @restore_error_handler();
        @restore_exception_handler();
        self::$instance = null;
    }

    public static function getConfig(): ?Config
    {
        return self::$instance?->config;
    }
}
