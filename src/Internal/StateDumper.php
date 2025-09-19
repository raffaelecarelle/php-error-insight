<?php

declare(strict_types=1);

namespace ErrorExplainer\Internal;

use ErrorExplainer\Contracts\StateDumperInterface;
use ReflectionClass;
use Throwable;

use function array_key_exists;
use function function_exists;
use function is_array;
use function is_object;

use const DEBUG_BACKTRACE_PROVIDE_OBJECT;

final class StateDumper implements StateDumperInterface
{
    /**
     * Instance variant to adhere to the StateDumperInterface while reusing the static implementation.
     *
     * @param array<int, array<string, mixed>>|null $traceFromHandler
     *
     * @return array<string, mixed>
     */
    public function collectState(?array $traceFromHandler = null): array
    {
        return self::collect($traceFromHandler);
    }

    /**
     * Collect extended state information at the time of an error/exception.
     *
     * @param array<int, array<string, mixed>> $traceFromHandler A debug_backtrace()-like array. Can be null.
     *
     * @return array<string, mixed>
     */
    public static function collect(?array $traceFromHandler = null): array
    {
        // Full raw trace (fresh) as requested
        $rawTrace = function_exists('debug_backtrace')
            ? debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT)
            : [];

        // Try to identify the most relevant current object: the first frame with 'object' either
        // from provided trace (preferred) or from the fresh raw trace.
        $frames = is_array($traceFromHandler) && [] !== $traceFromHandler ? $traceFromHandler : $rawTrace;
        $currentObject = null;
        foreach ($frames as $f) {
            if (is_array($f) && isset($f['object']) && is_object($f['object'])) {
                $currentObject = $f['object'];
                break;
            }
        }

        $objectInfo = null;
        if (is_object($currentObject)) {
            $objectInfo = self::introspectObject($currentObject);
        }

        // Capture Xdebug info (if available) without breaking headers by buffering the output
        $xdebugText = '';
        if (function_exists('xdebug_print_function_stack') || function_exists('xdebug_get_declared_vars')) {
            try {
                ob_start();
                if (function_exists('xdebug_print_function_stack')) {
                    @xdebug_print_function_stack();
                }

                if (function_exists('xdebug_get_declared_vars')) {
                    $decl = @xdebug_get_declared_vars();
                    dump($decl);
                }

                $xdebugText = (string) ob_get_clean();
            } catch (Throwable) {
                // best effort
                if (ob_get_level() > 0) {
                    @ob_end_clean();
                }
            }
        }

        // Note: get_defined_vars() here reflects only the scope within this function, but included for completeness
        $definedVars = get_defined_vars();
        // Clean and filter noisy/internal entries from definedVars
        $definedVars = self::filterDefinedVars($definedVars);

        $globalsAll = $GLOBALS;
        if (isset($globalsAll['GLOBALS'])) {
            $globalsAll['GLOBALS'] = '[omitted recursive $GLOBALS]';
        }

        return [
            'globalsAll' => $globalsAll,
            'definedVars' => $definedVars,
            'rawTrace' => $rawTrace,
            'object' => $objectInfo,
            'xdebugText' => $xdebugText,
        ];
    }

    /**
     * Keep only important keys and provide short summaries for complex values.
     *
     * @param array<string,mixed> $vars
     *
     * @return array<string,mixed>
     */
    private static function filterDefinedVars(array $vars): array
    {
        // Remove internal keys and superglobals-like entries
        $exclude = [
            'traceFromHandler', 'frames', 'currentObject', 'objectInfo', 'xdebugText', 'rawTrace',
            'globalsAll', 'definedVars', 'GLOBALS', '_GET', '_POST', '_COOKIE', '_SERVER', '_ENV', '_FILES', '_REQUEST', '_SESSION',
        ];
        foreach ($exclude as $k) {
            if (array_key_exists($k, $vars)) {
                unset($vars[$k]);
            }
        }

        $out = [];
        $maxItems = 20; // show at most N variables
        $i = 0;
        foreach ($vars as $k => $v) {
            if ($i >= $maxItems) {
                $out['…'] = '…(more vars omitted)';
                break;
            }

            // Keep values as-is so Renderer/VarDumper can present them nicely
            $out[$k] = $v;
            ++$i;
        }

        ksort($out);

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function introspectObject(object $obj): array
    {
        $class = $obj::class;
        $vars = [];
        try {
            $vars = get_object_vars($obj);
        } catch (Throwable) {
            $vars = [];
        }

        $refInfo = [
            'class' => $class,
            'properties' => [],
            'methods' => [],
        ];
        try {
            $ref = new ReflectionClass($obj);
            $props = $ref->getProperties();
            foreach ($props as $p) {
                $refInfo['properties'][] = ($p->isPublic() ? 'public ' : ($p->isProtected() ? 'protected ' : 'private '))
                    . ($p->isStatic() ? 'static ' : '')
                    . '$' . $p->getName();
            }

            $meths = $ref->getMethods();
            foreach ($meths as $m) {
                $refInfo['methods'][] = ($m->isPublic() ? 'public ' : ($m->isProtected() ? 'protected ' : 'private '))
                    . ($m->isStatic() ? 'static ' : '')
                    . $m->getName() . '()';
            }
        } catch (Throwable) {
            // ignore
        }

        return [
            'class' => $class,
            'vars' => $vars,
            'reflection' => $refInfo,
        ];
    }
}
