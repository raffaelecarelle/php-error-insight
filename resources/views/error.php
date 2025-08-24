<?php
// Dynamic template based on attached HTML; wired to Renderer::buildViewData variables
$e = static function ($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$docLang = $docLang ?? 'it';
$title = $title ?? 'PHP Exception Viewer';
$subtitle = $subtitle ?? '';
$where = $where ?? '';
$verbose = $verbose ?? false;
$details = $details ?? '';
$suggestions = $suggestions ?? [];
$frames = $frames ?? [];
$labels = $labels ?? ['headings' => [], 'labels' => []];
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
    <style>
        /* Symfony VarDumper overrides to match the error page (Tailwind-based) */
        .sf-dump, .sf-dump pre {
            background-color: #111827 !important; /* gray-900 */
            color: #e5e7eb !important; /* gray-200 */
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace !important;
            font-size: 0.75rem !important; /* text-xs */
            line-height: 1.25rem !important;
            border-radius: 0.5rem !important; /* rounded */
            padding: 0.5rem 0.75rem !important; /* p-2 px-3 */
            overflow: auto;
        }
        .sf-dump { box-shadow: none !important; border: 1px solid rgba(255,255,255,0.08) !important; }
        .sf-dump a { color: #93c5fd !important; text-decoration: underline; }
        .sf-dump .sf-dump-num { color: #60a5fa !important; } /* blue-400 */
        .sf-dump .sf-dump-str { color: #34d399 !important; } /* emerald-400 */
        .sf-dump .sf-dump-key { color: #f472b6 !important; } /* pink-400 */
        .sf-dump .sf-dump-note { color: #fbbf24 !important; } /* amber-300 */
        .sf-dump .sf-dump-ref { color: #a78bfa !important; } /* violet-400 */
        .sf-dump .sf-dump-meta { color: #9ca3af !important; } /* gray-400 */
        .sf-dump .sf-dump-public { color: #10b981 !important; } /* emerald-500 */
        .sf-dump .sf-dump-protected { color: #f59e0b !important; } /* amber-500 */
        .sf-dump .sf-dump-private { color: #ef4444 !important; } /* red-500 */
        .sf-dump .sf-dump-index { color: #93c5fd !important; } /* blue-300 */
        .sf-dump .sf-dump-ellipsis { color: #9ca3af !important; }
        .sf-dump .sf-dump-toggle { color: #e5e7eb !important; text-decoration: none !important; }

        /* Light mode variants */
        :root:not(.dark) .sf-dump, :root:not(.dark) .sf-dump pre {
            background-color: #f3f4f6 !important; /* gray-100 */
            color: #111827 !important; /* gray-900 */
            border: 1px solid rgba(0,0,0,0.06) !important;
        }
        :root:not(.dark) .sf-dump a { color: #1d4ed8 !important; }
        :root:not(.dark) .sf-dump .sf-dump-num { color: #1d4ed8 !important; } /* blue-700 */
        :root:not(.dark) .sf-dump .sf-dump-str { color: #047857 !important; } /* green-700 */
        :root:not(.dark) .sf-dump .sf-dump-key { color: #9d174d !important; } /* pink-800 */
        :root:not(.dark) .sf-dump .sf-dump-note { color: #92400e !important; } /* amber-700 */
        :root:not(.dark) .sf-dump .sf-dump-ref { color: #6d28d9 !important; } /* violet-700 */
        :root:not(.dark) .sf-dump .sf-dump-meta { color: #4b5563 !important; } /* gray-600 */

        /* When a dump is wrapped in a <pre>, make the inner sf-dump appear as the display block */
        pre > .sf-dump {
            background: transparent !important;
            padding: 10px !important;\
            border: 0 !important;
        }

        pre.sf-dump, pre.sf-dump .sf-dump-default {
            background: transparent !important;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 font-sans">

<!-- Header -->
<div class="bg-red-600 text-white px-6 py-4 flex justify-between items-center shadow">
    <div>
        <h1 class="text-xl font-bold">🚨 <?= $e($title) ?> <?php if ($where !== ''): ?> in <code><?= $e($where) ?></code><?php endif; ?></h1>
    </div>

    <div class="flex space-x-2">
        <button id="copyBtn" class="bg-white text-red-600 px-3 py-1 rounded shadow hover:bg-gray-200">
            <?= $e($labels['headings']['copy'] ?? 'Copy to clipboard') ?>
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
                            <p><strong><?= $e($labels['labels']['code'] ?? 'Code') ?>:</strong></p>
                            <div class="text-xs font-mono bg-white text-gray-900 rounded p-2 overflow-auto dark:bg-white dark:text-gray-900">
                                <?= $f['codeHtml'] ?? '' ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <!-- Tabs -->
    <section class="mb-6">
        <h2 class="text-lg font-semibold mb-2"><?= $e($labels['headings']['env_details'] ?? 'Environment Details') ?></h2>
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
            <pre class="text-gray-100 rounded text-xs"><?php dump($_SERVER ?? []) ?></pre>
        </div>
        <div id="tab-env" class="tab-content hidden">
            <pre class="text-gray-100 rounded text-xs"><?php dump($_ENV ?? []) ?></pre>
        </div>
        <div id="tab-cookies" class="tab-content hidden">
            <pre class="text-gray-100 rounded text-xs"><?php dump($_COOKIE ?? []) ?></pre>
        </div>
        <div id="tab-session" class="tab-content hidden">
            <pre class="text-gray-100 rounded text-xs"><?php dump($_SESSION ?? []) ?></pre>
        </div>
        <div id="tab-get" class="tab-content hidden">
            <pre class="text-gray-100 rounded text-xs"><?php dump($_GET ?? []) ?></pre>
        </div>
        <div id="tab-post" class="tab-content hidden">
            <pre class="text-gray-100 rounded text-xs"><?php dump($_POST ?? []) ?></pre>
        </div>
        <div id="tab-files" class="tab-content hidden">
            <pre class="text-gray-100 rounded text-xs"><?php dump($_FILES ?? []) ?></pre>
        </div>
    </section>

    <!-- Dettagli (AI) -->
    <?php if ($subtitle !== '' && $verbose && $details !== ''): ?>
        <section class="bg-blue-50 dark:bg-blue-900 border-l-4 border-blue-500 p-4 rounded shadow mb-6">
            <h2 class="text-lg font-semibold mb-2 text-blue-700 dark:text-blue-300">📝 <?= $e($labels['headings']['details'] ?? 'Details') ?></h2>
            <pre class="rounded text-xs whitespace-pre-wrap"><?= $e($details) ?></pre>
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
        const btnText = '<?= $e($labels['headings']['copy'] ?? 'Copy to clipboard') ?>';
        navigator.clipboard.writeText(text).then(() => {
            document.getElementById('copyBtn').textContent = '<?= $e($labels['headings']['copied'] ?? 'Copied!') ?>';
            setTimeout(() => {
                document.getElementById('copyBtn').textContent = btnText;
            }, 3000);
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
