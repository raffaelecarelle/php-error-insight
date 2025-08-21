<?php

declare(strict_types=1);

namespace ErrorExplainer;

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

    public function __construct(array $options = [])
    {
        if (array_key_exists('enabled', $options)) {
            $this->enabled = (bool)$options['enabled'];
        }
        if (array_key_exists('backend', $options)) {
            $this->backend = (string)$options['backend'];
        }
        if (array_key_exists('model', $options)) {
            $this->model = $options['model'] !== null ? (string)$options['model'] : null;
        }
        if (array_key_exists('language', $options)) {
            $this->language = (string)$options['language'];
        }
        if (array_key_exists('output', $options)) {
            $this->output = (string)$options['output'];
        }
        if (array_key_exists('verbose', $options)) {
            $this->verbose = (bool)$options['verbose'];
        }
        if (array_key_exists('apiKey', $options)) {
            $this->apiKey = $options['apiKey'] !== null ? (string)$options['apiKey'] : null;
        }
        if (array_key_exists('apiUrl', $options)) {
            $this->apiUrl = $options['apiUrl'] !== null ? (string)$options['apiUrl'] : null;
        }
    }

    public static function fromEnvAndArray(array $options = []): self
    {
        $env = [
            'enabled'  => self::envBool('ERROR_EXPLAINER_ENABLED', true),
            'backend'  => getenv('ERROR_EXPLAINER_BACKEND') ?: 'none',
            'model'    => getenv('ERROR_EXPLAINER_MODEL') ?: null,
            'language' => getenv('ERROR_EXPLAINER_LANG') ?: 'it',
            'output'   => getenv('ERROR_EXPLAINER_OUTPUT') ?: self::OUTPUT_AUTO,
            'verbose'  => self::envBool('ERROR_EXPLAINER_VERBOSE', false),
            'apiKey'   => getenv('ERROR_EXPLAINER_API_KEY') ?: null,
            'apiUrl'   => getenv('ERROR_EXPLAINER_API_URL') ?: null,
        ];
        // Options override env
        $merged = array_merge($env, $options);

        return new self($merged);
    }

    private static function envBool(string $var, bool $default): bool
    {
        $val = getenv($var);
        if ($val === false || $val === null) {
            return $default;
        }
        $val = strtolower((string)$val);
        return in_array($val, ['1', 'true', 'yes', 'on'], true);
    }
}
