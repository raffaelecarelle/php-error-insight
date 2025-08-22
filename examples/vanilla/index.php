<?php

// Minimal example demonstrating PHP Error Explainer
// Run with: php examples/vanilla/index.php
// Optional AI config (env):
//   PHP_ERROR_INSIGHT_BACKEND=none|local|api|openai|anthropic|google|gemini
//   PHP_ERROR_INSIGHT_MODEL=llama3:instruct|gpt-4o-mini|claude-3-5-sonnet-20240620|gemini-1.5-flash|...
//   PHP_ERROR_INSIGHT_API_URL=http://localhost:11434 (Ollama) | https://api.openai.com/v1/chat/completions (OpenAI) | https://api.anthropic.com/v1/messages (Anthropic) | https://generativelanguage.googleapis.com/v1/models (Google Gemini)
//   PHP_ERROR_INSIGHT_API_KEY=sk-... (OpenAI) | api-key (Anthropic) | api-key (Google Gemini)
//   PHP_ERROR_INSIGHT_OUTPUT=auto|text|html|json
//   PHP_ERROR_INSIGHT_VERBOSE=1
//   PHP_ERROR_INSIGHT_LANG=it|en
//   PHP_ERROR_INSIGHT_TEMPLATE=/absolute/path/to/custom/error.php

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
