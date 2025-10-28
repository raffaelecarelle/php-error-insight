<?php

declare(strict_types=1);

// Simple endpoint to simulate a web SAPI request handled by PHP built-in server (cli-server)
// It registers PHP Error Insight and triggers a user warning so that the library renders the error.

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use PhpErrorInsight\Config;
use PhpErrorInsight\ErrorExplainer;

// Force a non-JSON preferred output to verify that JSON is still enforced by the Content-Type heuristic
ErrorExplainer::register([
    'output' => Config::OUTPUT_HTML, // would normally render HTML, but should be forced to JSON
    'language' => 'en',
    'backend' => 'none',
    'verbose' => false,
]);

// Trigger an error; the library will catch and render it
trigger_error('Forced JSON via Content-Type', \E_USER_WARNING);
