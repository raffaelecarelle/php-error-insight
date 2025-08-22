<?php

// Minimal example demonstrating PHP Error Explainer
// Run with: php examples/vanilla/index.php
// Optional AI config (env):
//   ERROR_EXPLAINER_BACKEND=none|local|api
//   ERROR_EXPLAINER_MODEL=llama2|gpt-4o-mini|...
//   ERROR_EXPLAINER_API_URL=http://localhost:11434 (for Ollama) or https://api.openai.com/v1/chat/completions
//   ERROR_EXPLAINER_API_KEY=sk-... (for OpenAI)
//   ERROR_EXPLAINER_OUTPUT=auto|text|html|json
//   ERROR_EXPLAINER_VERBOSE=1
//   ERROR_EXPLAINER_LANG=it|en
//   ERROR_EXPLAINER_TEMPLATE=/absolute/path/to/custom/error.php

require __DIR__ . '/../../vendor/autoload.php'; // Ensure you ran `composer dump-autoload`

use ErrorExplainer\ErrorExplainer;

ErrorExplainer::register([
    'enabled' => true,
    'output' => 'html',   // auto|text|html|json
    'verbose' => true,
    'backend'  => 'api',
    'model'    => 'gpt-4o-mini',
    'language' => 'en',
    'apiKey'   => 'xxxxx',]);

// Trigger a notice: undefined variable
echo $undefinedVar;

// Trigger an exception
throw new RuntimeException('Example exception to demonstrate ErrorExplainer');
