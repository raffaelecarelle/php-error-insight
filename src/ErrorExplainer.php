<?php

declare(strict_types=1);

namespace ErrorExplainer;

use ErrorExplainer\Internal\ErrorHandler;

final class ErrorExplainer
{
    private static ?self $instance = null;

    private Config $config;
    private ErrorHandler $handler;

    private function __construct(Config $config, ErrorHandler $handler)
    {
        $this->config = $config;
        $this->handler = $handler;
    }

    /**
     * Register global handlers to intercept PHP errors and exceptions.
     * @param array $options
     * @return self
     */
    public static function register(array $options = []): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $config = Config::fromEnvAndArray($options);
        if (!$config->enabled) {
            // Still create instance but do not install handlers
            $dummy = new Internal\ErrorHandler($config, null, null);
            self::$instance = new self($config, $dummy);
            return self::$instance;
        }

        $prevError = set_error_handler(static function () {
            // placeholder to read previous, will be replaced immediately
        });
        if ($prevError !== null) {
            // restore so we can set our combined handler later
            restore_error_handler();
        }
        $prevEx = set_exception_handler(static function () {
            // placeholder to read previous, will be replaced immediately
        });
        if ($prevEx !== null) {
            restore_exception_handler();
        }

        $handler = new ErrorHandler($config, $prevError, $prevEx);

        set_error_handler([$handler, 'handleError']);
        set_exception_handler([$handler, 'handleException']);
        register_shutdown_function([$handler, 'handleShutdown']);

        self::$instance = new self($config, $handler);
        return self::$instance;
    }

    /**
     * Restore previous handlers if any.
     */
    public static function unregister(): void
    {
        if (self::$instance === null) {
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
