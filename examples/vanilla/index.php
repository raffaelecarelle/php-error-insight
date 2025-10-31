<?php

declare(strict_types=1);

// Minimal example demonstrating PHP Error Explainer
// Run with: php examples/vanilla/index.php
// Optional AI config (env):
// called   PHP_ERROR_INSIGHT_BACKEND=none|local|api|openai|anthropic|google|gemini
//   PHP_ERROR_INSIGHT_MODEL=llama3:instruct|gpt-4o-mini|claude-3-5-sonnet-20240620|gemini-1.5-flash|...
//   PHP_ERROR_INSIGHT_API_URL=http://localhost:11434 (Ollama) | https://api.openai.com/v1/chat/completions (OpenAI) | https://api.anthropic.com/v1/messages (Anthropic) | https://generativelanguage.googleapis.com/v1/models (Google Gemini)
//   PHP_ERROR_INSIGHT_API_KEY=sk-... (OpenAI) | api-key (Anthropic) | api-key (Google Gemini)
//   PHP_ERROR_INSIGHT_OUTPUT=auto|text|html|json
//   PHP_ERROR_INSIGHT_VERBOSE=1
//   PHP_ERROR_INSIGHT_LANG=it|en
//   PHP_ERROR_INSIGHT_TEMPLATE=/absolute/path/to/custom/error.php

require __DIR__ . '/../../vendor/autoload.php'; // Ensure you ran `composer dump-autoload`
require __DIR__ . '/ErrorTest.php'; // Ensure you ran `composer dump-autoload`

use PhpErrorInsight\ErrorExplainer;

ErrorExplainer::register([
    'enabled' => true,
    'output' => 'auto',   // auto|text|html|json
    'verbose' => true,
    // Default to no AI to avoid network calls in example; configure via env to enable
    'backend' => getenv('PHP_ERROR_INSIGHT_BACKEND') ?: 'api',
    'model' => getenv('PHP_ERROR_INSIGHT_MODEL') ?: 'gpt-4o-mini',
    'language' => getenv('PHP_ERROR_INSIGHT_LANG') ?: 'en',
    'apiKey' => getenv('PHP_ERROR_INSIGHT_API_KEY') ?: null,
    'apiUrl' => getenv('PHP_ERROR_INSIGHT_API_URL') ?: null,
    'projectRoot' => dirname(__DIR__, 2),
]);


trigger_error((string) "function called.", E_USER_NOTICE);
trigger_error("function called.", E_USER_DEPRECATED);
trigger_error("function called.", E_USER_WARNING);
@trigger_error((string) "Should skipped.", E_USER_NOTICE); //should skip error
throw new Exception("exception thrown.");
ErrorTest::throw();