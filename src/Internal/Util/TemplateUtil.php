<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use const EXTR_SKIP;

/**
 * TemplateUtil isolates PHP's include/extract mechanics for rendering views.
 * Why: keeps high-level renderer free from language-level constructs.
 */
final class TemplateUtil
{
    /**
     * Include a PHP template file in an isolated scope with provided variables.
     *
     * @param array<string,mixed> $data
     */
    public function includeWithData(string $template, array $data): void
    {
        (static function (string $__template, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__template;
        })($template, $data);
    }
}
