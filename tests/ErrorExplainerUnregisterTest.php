<?php

declare(strict_types=1);

namespace ErrorExplainer\Tests;

use ErrorExplainer\Config;
use ErrorExplainer\ErrorExplainer;
use PHPUnit\Framework\TestCase;

final class ErrorExplainerUnregisterTest extends TestCase
{
    protected function tearDown(): void
    {
        ErrorExplainer::unregister();
        parent::tearDown();
    }

    public function testRegisterDisabledDoesNotInstallHandlersAndUnregisterIsIdempotent(): void
    {
        // Register with enabled = false: must NOT install handlers
        $instance1 = ErrorExplainer::register([
            'enabled' => false,
            'output' => Config::OUTPUT_TEXT,
        ]);
        $this->assertInstanceOf(ErrorExplainer::class, $instance1);

        // Capture current error handler
        $currentErr = set_error_handler(static fn (): bool => false);
        restore_error_handler();

        // Verify ErrorExplainer is NOT the current error handler
        if ($currentErr !== null) {
            if (is_object($currentErr)) {
                $this->assertNotInstanceOf(
                    ErrorExplainer::class,
                    $currentErr,
                    'ErrorExplainer should not be installed as error handler when disabled'
                );
            } elseif ($currentErr instanceof \Closure) {
                $reflection = new \ReflectionFunction($currentErr);
                $scopeClass = $reflection->getClosureScopeClass();
                if ($scopeClass !== null) {
                    $this->assertStringNotContainsString(
                        'ErrorExplainer',
                        $scopeClass->getName(),
                        'ErrorExplainer should not be installed as error handler when disabled'
                    );
                }
            }
        }

        // Capture current exception handler
        $currentEx = set_exception_handler(static function (): void {
        });
        restore_exception_handler();

        // Verify ErrorExplainer is NOT the current exception handler
        if ($currentEx !== null) {
            if (is_object($currentEx)) {
                $this->assertNotInstanceOf(
                    ErrorExplainer::class,
                    $currentEx,
                    'ErrorExplainer should not be installed as exception handler when disabled'
                );
            } elseif ($currentEx instanceof \Closure) {
                $reflection = new \ReflectionFunction($currentEx);
                $scopeClass = $reflection->getClosureScopeClass();
                if ($scopeClass !== null) {
                    $this->assertStringNotContainsString(
                        'ErrorExplainer',
                        $scopeClass->getName(),
                        'ErrorExplainer should not be installed as exception handler when disabled'
                    );
                }
            }
        }

        // Unregister twice: should not error (idempotent test)
        ErrorExplainer::unregister();
        ErrorExplainer::unregister();

        // After unregister, verify handlers are still not ErrorExplainer
        $afterErr = set_error_handler(static fn (): bool => false);
        restore_error_handler();

        if ($afterErr !== null && is_object($afterErr)) {
            $this->assertNotInstanceOf(
                ErrorExplainer::class,
                $afterErr,
                'ErrorExplainer should not be installed after unregister'
            );
        }

        $this->assertTrue(true, 'Unregister is idempotent and does not cause errors');
    }
}
