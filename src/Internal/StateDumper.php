<?php

declare(strict_types=1);

namespace ErrorExplainer\Internal;

final class StateDumper
{
    /**
     * Collect extended state information at the time of an error/exception.
     *
     * @param array<int, array<string, mixed>> $traceFromHandler A debug_backtrace()-like array. Can be null.
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
        $frames = is_array($traceFromHandler) && !empty($traceFromHandler) ? $traceFromHandler : $rawTrace;
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
                    var_dump($decl);
                }
                $xdebugText = (string)ob_get_clean();
            } catch (\Throwable $e) {
                // best effort
                if (ob_get_level() > 0) {
                    @ob_end_clean();
                }
            }
        }

        // Note: get_defined_vars() here reflects only the scope within this function, but included for completeness
        $definedVars = get_defined_vars();
        // Avoid putting large entries in definedVars
        unset($definedVars['traceFromHandler'], $definedVars['frames'], $definedVars['currentObject']);

        // Prepare sanitized globals to avoid recursive reference explosion
        $globalsAll = [];
        if (isset($GLOBALS) && is_array($GLOBALS)) {
            $globalsAll = $GLOBALS;
            if (isset($globalsAll['GLOBALS'])) {
                $globalsAll['GLOBALS'] = '[omitted recursive $GLOBALS]';
            }
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
     * @return array<string, mixed>
     */
    private static function introspectObject(object $obj): array
    {
        $class = get_class($obj);
        $vars = [];
        try {
            $vars = get_object_vars($obj);
        } catch (\Throwable $e) {
            $vars = [];
        }

        $refInfo = [
            'class' => $class,
            'properties' => [],
            'methods' => [],
        ];
        try {
            $ref = new \ReflectionClass($obj);
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
        } catch (\Throwable $e) {
            // ignore
        }

        return [
            'class' => $class,
            'vars' => $vars,
            'reflection' => $refInfo,
        ];
    }
}
