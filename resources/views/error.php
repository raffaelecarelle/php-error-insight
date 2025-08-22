<?php
// Dynamic template based on attached HTML; wired to Renderer::buildViewData variables
$e = static function ($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$docLang = $docLang ?? 'it';
$title = $title ?? 'PHP Exception Viewer';
$subtitle = $subtitle ?? '';
$severity = $severity ?? 'Error';
$where = $where ?? '';
$summary = $summary ?? '';
$verbose = $verbose ?? false;
$details = $details ?? '';
$suggestions = $suggestions ?? [];
$frames = $frames ?? [];
$labels = $labels ?? ['headings' => [], 'labels' => []];

$dumpLines = static function ($arr): string {
    if (!is_array($arr)) return '';
    $lines = [];
    foreach ($arr as $k => $v) {
        $vv = $v;
        if (is_array($v) || is_object($v)) {
            $vv = json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PARTIAL_OUTPUT_ON_ERROR);
        } elseif (is_bool($v)) {
            $vv = $v ? 'true' : 'false';
        } elseif ($v === null) {
            $vv = 'null';
        }
        $lines[] = (string)$k . ' = ' . (string)$vv;
    }
    return implode("\n", $lines);
};

$serverDump = $dumpLines(isset($_SERVER) ? $_SERVER : []);
$envDump = $dumpLines(isset($_ENV) ? $_ENV : []);
$cookieDump = $dumpLines(isset($_COOKIE) ? $_COOKIE : []);
$sessionArr = (function () { return (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE && isset($_SESSION)) ? (array)$_SESSION : []; })();
$sessionDump = $dumpLines($sessionArr);
$getDump = $dumpLines(isset($_GET) ? $_GET : []);
$postArr = isset($_POST) ? $_POST : [];
if (is_array($postArr)) {
    foreach ($postArr as $k => &$v) {
        if (is_string($k) && preg_match('/pass(word)?|secret|token|key/i', (string)$k)) {
            $v = '********';
        }
    }
    unset($v);
}
$postDump = $dumpLines($postArr);
$filesDump = $dumpLines(isset($_FILES) ? $_FILES : []);
$copyText = json_encode(trim(($title !== '' ? $title : 'Error') . ($where !== '' ? ' in ' . $where : '')), JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="<?= $e($docLang) ?>">
<head>
    <meta charset="UTF-8">
    <title>PHP Exception Viewer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 font-sans">

<!-- Header -->
<div class="bg-red-600 text-white px-6 py-4 flex justify-between items-center shadow">
    <div>
        <h1 class="text-xl font-bold">🚨 Eccezione non gestita</h1>
        <p class="text-sm">
            <?= $e($title) ?>
            <?php if ($where !== ''): ?> in <code><?= $e($where) ?></code><?php endif; ?>
        </p>
    </div>
    <div class="flex space-x-2">
        <button id="copyBtn" class="bg-white text-red-600 px-3 py-1 rounded shadow hover:bg-gray-200">
            Copy error
        </button>
        <button id="toggleTheme" class="bg-white text-gray-700 px-3 py-1 rounded shadow hover:bg-gray-200">
            🌙/☀️
        </button>
    </div>
</div>

<main class="p-6 max-w-6xl mx-auto">

    <!-- Stack trace -->
    <?php if (!empty($frames)): ?>
        <section class="mb-6">
            <h2 class="text-lg font-semibold mb-2"><?= $e($labels['headings']['stack'] ?? 'Stack Trace') ?></h2>
            <ul class="space-y-2">
                <?php foreach ($frames as $f): ?>
                    <li class="bg-white dark:bg-gray-800 rounded shadow p-3">
                        <button class="w-full text-left flex justify-between items-center toggle-frame">
                            <span>#<?= $e($f['idx']) ?> <?= $e($f['loc'] ?? '') ?> – <code><?= $e($f['sig'] ?? '') ?></code></span>
                            <span>+</span>
                        </button>
                        <div class="hidden mt-2 text-sm bg-gray-50 dark:bg-gray-700 p-2 rounded">
                            <p><strong><?= $e($labels['labels']['locals'] ?? 'Locals') ?>:</strong></p>
                            <pre class="bg-gray-900 text-green-200 p-2 rounded text-xs"><?= $e(($f['localsDump'] ?? '') !== '' ? $f['localsDump'] : '∅') ?></pre>
                            <?php if (!empty($f['argsDump'] ?? '')): ?>
                                <p class="mt-2"><strong><?= $e($labels['labels']['arguments'] ?? 'Arguments') ?>:</strong></p>
                                <pre class="bg-gray-900 text-green-200 p-2 rounded text-xs"><?= $e($f['argsDump']) ?></pre>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <!-- Tabs -->
    <section class="mb-6">
        <h2 class="text-lg font-semibold mb-2">Dettagli Ambiente</h2>
        <div class="border-b border-gray-300 dark:border-gray-700 flex flex-wrap space-x-2 mb-4">
            <button class="tab-btn font-medium py-2 px-3 border-b-2 border-red-600" data-tab="server">Server/Request</button>
            <button class="tab-btn font-medium py-2 px-3" data-tab="env">Env Vars</button>
            <button class="tab-btn font-medium py-2 px-3" data-tab="cookies">Cookies</button>
            <button class="tab-btn font-medium py-2 px-3" data-tab="session">Session</button>
            <button class="tab-btn font-medium py-2 px-3" data-tab="get">GET</button>
            <button class="tab-btn font-medium py-2 px-3" data-tab="post">POST</button>
            <button class="tab-btn font-medium py-2 px-3" data-tab="files">Files</button>
        </div>

        <div id="tab-server" class="tab-content">
            <pre class="bg-gray-900 text-gray-100 p-3 rounded text-xs"><?= $e($serverDump) ?></pre>
        </div>
        <div id="tab-env" class="tab-content hidden">
            <pre class="bg-gray-900 text-gray-100 p-3 rounded text-xs"><?= $e($envDump) ?></pre>
        </div>
        <div id="tab-cookies" class="tab-content hidden">
            <pre class="bg-gray-900 text-gray-100 p-3 rounded text-xs"><?= $e($cookieDump) ?></pre>
        </div>
        <div id="tab-session" class="tab-content hidden">
            <pre class="bg-gray-900 text-gray-100 p-3 rounded text-xs"><?= $e($sessionDump) ?></pre>
        </div>
        <div id="tab-get" class="tab-content hidden">
            <pre class="bg-gray-900 text-gray-100 p-3 rounded text-xs"><?= $e($getDump) ?></pre>
        </div>
        <div id="tab-post" class="tab-content hidden">
            <pre class="bg-gray-900 text-gray-100 p-3 rounded text-xs"><?= $e($postDump) ?></pre>
        </div>
        <div id="tab-files" class="tab-content hidden">
            <pre class="bg-gray-900 text-gray-100 p-3 rounded text-xs"><?= $e($filesDump) ?></pre>
        </div>
    </section>

    <!-- Dettagli (AI) -->
    <?php if ($subtitle !== '' && $verbose && $details !== ''): ?>
        <section class="bg-blue-50 dark:bg-blue-900 border-l-4 border-blue-500 p-4 rounded shadow mb-6">
            <h2 class="text-lg font-semibold mb-2 text-blue-700 dark:text-blue-300">📝 <?= $e($labels['headings']['details'] ?? 'Details') ?></h2>
            <pre class="bg-gray-900 text-gray-100 p-3 rounded text-xs whitespace-pre-wrap"><?= $e($details) ?></pre>
        </section>
    <?php endif; ?>

    <!-- Suggerimenti (AI) -->
    <?php if ($subtitle !== '' && !empty($suggestions)): ?>
        <section class="bg-green-50 dark:bg-green-900 border-l-4 border-green-500 p-4 rounded shadow">
            <h2 class="text-lg font-semibold mb-2 text-green-700 dark:text-green-300">💡 <?= $e($labels['headings']['suggestions'] ?? 'Suggestions') ?></h2>
            <ul class="list-disc pl-6 text-sm">
                <?php foreach ($suggestions as $s): ?>
                    <li><?= $e($s) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</main>

<script>
    // Toggle stack trace frames
    document.querySelectorAll('.toggle-frame').forEach(btn => {
        btn.addEventListener('click', () => {
            const details = btn.nextElementSibling;
            details.classList.toggle('hidden');
            btn.querySelector('span:last-child').textContent =
                details.classList.contains('hidden') ? '+' : '−';
        });
    });

    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(function(b) {
                b.classList.remove('border-b-2')
                b.classList.remove('border-red-600')
            });
            btn.classList.add('border-b-2');
            btn.classList.add('border-red-600');
            document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
            document.getElementById('tab-' + btn.dataset.tab).classList.remove('hidden');
        });
    });

    // Copy to clipboard
    document.getElementById('copyBtn').addEventListener('click', () => {
        const text = <?= $copyText ?>;
        navigator.clipboard.writeText(text).then(() => {
            alert("Errore copiato negli appunti!");
        });
    });

    // Dark/Light toggle
    const toggleBtn = document.getElementById('toggleTheme');
    toggleBtn.addEventListener('click', () => {
        document.documentElement.classList.toggle('dark');
    });
</script>
</body>
</html>
