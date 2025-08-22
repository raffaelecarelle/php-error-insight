<?php

// Minimal example demonstrating PHP Error Explainer
// Run with: php examples/vanilla/index.php
// Optional AI config (env):
//   ERROR_EXPLAINER_BACKEND=none|local|api|openai|anthropic|google|gemini
//   ERROR_EXPLAINER_MODEL=llama3:instruct|gpt-4o-mini|claude-3-5-sonnet-20240620|gemini-1.5-flash|...
//   ERROR_EXPLAINER_API_URL=http://localhost:11434 (Ollama) | https://api.openai.com/v1/chat/completions (OpenAI) | https://api.anthropic.com/v1/messages (Anthropic) | https://generativelanguage.googleapis.com/v1/models (Google Gemini)
//   ERROR_EXPLAINER_API_KEY=sk-... (OpenAI) | api-key (Anthropic) | api-key (Google Gemini)
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
