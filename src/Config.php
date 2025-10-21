<?php

declare(strict_types=1);

namespace ErrorExplainer;

use function array_key_exists;
use function in_array;

final class Config
{
    public const OUTPUT_AUTO = 'auto';

    public const OUTPUT_HTML = 'html';

    public const OUTPUT_TEXT = 'text';

    public const OUTPUT_JSON = 'json';

    public bool $enabled = true;

    public string $backend = 'none'; // none|local|api

    public ?string $model = null;

    public string $language = 'it';

    public string $output = self::OUTPUT_AUTO; // auto|html|text|json

    public bool $verbose = false;

    public ?string $apiKey = null;

    public ?string $apiUrl = null;

    public ?string $template = null; // path to external HTML template

    // New options for linking files and computing relative paths
    public ?string $projectRoot = null; // absolute project root to make file paths relative

    public ?string $editorUrl = null; // template like "vscode://file/%file:%line" or "phpstorm://open?file=%file&line=%line"

    // Optional container-to-host path mapping for editor links
    public ?string $containerPath = null; // e.g., "/app"

    public ?string $hostPath = null;      // e.g., "/Users/me/project"

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        if (array_key_exists('enabled', $options)) {
            $this->enabled = (bool) $options['enabled'];
        }

        if (array_key_exists('backend', $options)) {
            $this->backend = (string) $options['backend'];
        }

        if (array_key_exists('model', $options)) {
            $this->model = null !== $options['model'] ? (string) $options['model'] : null;
        }

        if (array_key_exists('language', $options)) {
            $this->language = (string) $options['language'];
        }

        if (array_key_exists('output', $options)) {
            $this->output = (string) $options['output'];
        }

        if (array_key_exists('verbose', $options)) {
            $this->verbose = (bool) $options['verbose'];
        }

        if (array_key_exists('apiKey', $options)) {
            $this->apiKey = null !== $options['apiKey'] ? (string) $options['apiKey'] : null;
        }

        if (array_key_exists('apiUrl', $options)) {
            $this->apiUrl = null !== $options['apiUrl'] ? (string) $options['apiUrl'] : null;
        }

        if (array_key_exists('template', $options)) {
            $this->template = null !== $options['template'] ? (string) $options['template'] : null;
        }

        if (array_key_exists('projectRoot', $options)) {
            $this->projectRoot = null !== $options['projectRoot'] ? (string) $options['projectRoot'] : null;
        }

        if (array_key_exists('editorUrl', $options)) {
            $this->editorUrl = null !== $options['editorUrl'] ? (string) $options['editorUrl'] : null;
        }

        if (array_key_exists('containerPath', $options)) {
            $this->containerPath = null !== $options['containerPath'] ? rtrim((string) $options['containerPath'], '/\\') : null;
        }

        if (array_key_exists('hostPath', $options)) {
            $this->hostPath = null !== $options['hostPath'] ? rtrim((string) $options['hostPath'], '/\\') : null;
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function fromEnvAndArray(array $options = []): self
    {
        $env = [
            'enabled' => self::envBool('PHP_ERROR_INSIGHT_ENABLED', true),
            'backend' => getenv('PHP_ERROR_INSIGHT_BACKEND') ?: 'none',
            'model' => getenv('PHP_ERROR_INSIGHT_MODEL') ?: null,
            'language' => getenv('PHP_ERROR_INSIGHT_LANG') ?: 'it',
            'output' => getenv('PHP_ERROR_INSIGHT_OUTPUT') ?: self::OUTPUT_AUTO,
            'verbose' => self::envBool('PHP_ERROR_INSIGHT_VERBOSE', false),
            'apiKey' => getenv('PHP_ERROR_INSIGHT_API_KEY') ?: null,
            'apiUrl' => getenv('PHP_ERROR_INSIGHT_API_URL') ?: null,
            'template' => getenv('PHP_ERROR_INSIGHT_TEMPLATE') ?: null,
            // projectRoot is no longer loaded from env; leave null and compute at runtime
            'projectRoot' => null,
            'editorUrl' => getenv('PHP_ERROR_INSIGHT_EDITOR') ?: null,
            // Back-compat: if new mapping is not provided, fallback to legacy envs
            'containerPath' => null,
            'hostPath' => null,
        ];

        // Parse new single mapping env var: HOST:CONTAINER
        $mapping = getenv('PHP_ERROR_INSIGHT_PROJECT_VOLUME_MAPPING');
        if (false !== $mapping && '' !== $mapping) {
            $mapping = (string) $mapping;
            // Allow values like "/host:/container"; split on first ':' only
            $pos = strpos($mapping, ':');
            if (false !== $pos) {
                $host = substr($mapping, 0, $pos);
                $cont = substr($mapping, $pos + 1);
                $env['hostPath'] = '' !== $host ? rtrim($host, '\\/') : null;
                $env['containerPath'] = '' !== $cont ? rtrim($cont, '\\/') : null;
            }
        } else {
            // Legacy envs support (will be removed in a future major)
            $legacyContainer = getenv('PHP_ERROR_INSIGHT_CONTAINER_PATH') ?: null;
            $legacyHost = getenv('PHP_ERROR_INSIGHT_HOST_PATH') ?: null;
            if ('' !== $legacyContainer) {
                $env['containerPath'] = rtrim((string) $legacyContainer, '\\/');
            }

            if ('' !== $legacyHost) {
                $env['hostPath'] = rtrim((string) $legacyHost, '\\/');
            }
        }

        // Options override env
        $merged = array_merge($env, $options);

        return new self($merged);
    }

    private static function envBool(string $var, bool $default): bool
    {
        $val = getenv($var);
        if (false === $val) {
            return $default;
        }

        $val = strtolower((string) $val);

        return in_array($val, ['1', 'true', 'yes', 'on'], true);
    }
}
