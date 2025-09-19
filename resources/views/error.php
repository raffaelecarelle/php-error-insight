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

$cloner = new \Symfony\Component\VarDumper\Cloner\VarCloner();
$dumper = new \Symfony\Component\VarDumper\Dumper\HtmlDumper();
?>
<!DOCTYPE html>
<html lang="<?= $e($docLang) ?>">
<head>
    <meta charset="UTF-8">
    <title>PHP Exception Viewer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Tailwind config + early theme initialization respecting user preference
        tailwind.config = { darkMode: 'class' };
        (function(){
            try {
                var saved = localStorage.getItem('errorView.theme');
                var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                var theme = saved || (prefersDark ? 'dark' : 'light');
                if (theme === 'dark') document.documentElement.classList.add('dark');
            } catch (e) { /* no-op */ }
        })();
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
            padding: 10px !important;
            border: 0 !important;
        }

        pre.sf-dump, pre.sf-dump .sf-dump-default {
            background: transparent !important;
        }

        /* Highlight class for variables/parameters */
        .hl-var { background: rgba(234,179,8,0.35); color: inherit; border-radius: 0.25rem; padding: 0 0.1rem; }
        :root.dark .hl-var { background: rgba(234,179,8,0.35); }

        /* Reduced motion */
        @media (prefers-reduced-motion: reduce) {
            * { animation-duration: 0.001ms !important; animation-iteration-count: 1 !important; transition-duration: 0.001ms !important; scroll-behavior: auto !important; }
        }

        /* Print styles */
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; color: #000 !important; }
            .dark & { color: #000 !important; }
            .shadow { box-shadow: none !important; }
            .bg-white, .dark\:bg-gray-800, .dark\:bg-gray-700 { background: #fff !important; }
            .text-gray-900, .dark\:text-gray-100 { color: #000 !important; }
            .frame-details { display: block !important; }
            a { color: #000; text-decoration: underline; }
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 font-sans">

<!-- Header -->
<div class="bg-red-600 text-white px-6 py-4 flex justify-between items-center shadow">
    <div>
        <h1 class="text-xl font-bold">🚨 <?= $e($title) ?> <?php if ($where !== ''): ?> in <code><?= $e($where) ?></code><?php endif; ?></h1>
    </div>

    <div class="flex space-x-2 no-print">
        <button id="copyBtn" class="bg-white text-red-600 px-3 py-1 rounded shadow hover:bg-gray-200">
            <?= $e($labels['headings']['copy'] ?? 'Copy title') ?>
        </button>
        <button id="toggleTheme" class="bg-white text-gray-700 px-3 py-1 rounded shadow hover:bg-gray-200" aria-pressed="false" title="Toggle theme">
            🌙/☀️
        </button>
    </div>
</div>

<main class="p-6 max-w-6xl mx-auto">

    <!-- Stack trace -->
    <?php if (!empty($frames)): ?>
        <section class="mb-6">
            <h2 class="text-lg font-semibold mb-2 flex items-center justify-between">
                <span><?= $e($labels['headings']['stack'] ?? 'Stack Trace') ?></span>
                <span class="flex items-center gap-2 no-print">
                    <input id="traceSearch" type="search" inputmode="search" placeholder="<?= $e($labels['labels']['search_trace'] ?? 'Cerca nel trace…') ?>" class="text-sm px-2 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800" aria-label="<?= $e($labels['labels']['search_trace'] ?? 'Cerca nel trace') ?>">
                    <button id="clearSearch" class="text-sm px-2 py-1 rounded border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="<?= $e($labels['labels']['clear_search'] ?? 'Pulisci ricerca') ?>">✕</button>
                    <button id="copyStackBtn" class="text-sm px-2 py-1 rounded border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="<?= $e($labels['labels']['copy_stack'] ?? 'Copia stack') ?>">📋 Stack</button>
                </span>
            </h2>
            <ul id="traceList" class="space-y-2">
                <?php foreach ($frames as $f): ?>
                    <?php $frameKey = ($f['idx'] ?? '') . '|' . ($f['loc'] ?? '') . '|' . ($f['sig'] ?? ''); ?>
                    <li class="bg-white dark:bg-gray-800 rounded shadow p-3" data-id="<?= $e($frameKey) ?>" data-search="<?= $e(strtolower(trim(($f['idx'] ?? '') . ' ' . ($f['loc'] ?? '') . ' ' . ($f['sig'] ?? '')))) ?>">
                        <button class="w-full text-left flex justify-between items-center toggle-frame" aria-expanded="false" aria-controls="details-<?= $e($f['idx']) ?>">
                            <span class="trace-line">#<?= $e($f['idx']) ?> <?= $e($f['loc'] ?? '') ?> – <code><?= $e($f['sig'] ?? '') ?></code></span>
                            <span class="indicator" aria-hidden="true">+</span>
                        </button>
                        <div id="details-<?= $e($f['idx']) ?>" class="frame-details hidden mt-2 text-sm bg-gray-50 dark:bg-gray-700 p-2 rounded" data-sig="<?= $e($f['sig'] ?? '') ?>">
                            <p><strong><?= $e($labels['labels']['code'] ?? 'Code') ?>:</strong></p>
                            <div class="code-view text-xs font-mono bg-white text-gray-900 rounded p-2 overflow-auto dark:bg-white dark:text-gray-900">
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
            <pre class="text-gray-100 rounded text-xs"><?php $dumper->dump($cloner->cloneVar($_SERVER ?? [])) ?></pre>
        </div>
        <div id="tab-env" class="tab-content hidden">
            <pre class="text-gray-100 rounded text-xs"><?php $dumper->dump($cloner->cloneVar($_ENV ?? [])) ?></pre>
        </div>
        <div id="tab-cookies" class="tab-content hidden">
            <pre class="text-gray-100 rounded text-xs"><?php $dumper->dump($cloner->cloneVar($_COOKIE ?? [])) ?></pre>
        </div>
        <div id="tab-session" class="tab-content hidden">
            <pre class="text-gray-100 rounded text-xs"><?php $dumper->dump($cloner->cloneVar($_SESSION ?? [])) ?></pre>
        </div>
        <div id="tab-get" class="tab-content hidden">
            <pre class="text-gray-100 rounded text-xs"><?php $dumper->dump($cloner->cloneVar($_GET ?? [])) ?></pre>
        </div>
        <div id="tab-post" class="tab-content hidden">
            <pre class="text-gray-100 rounded text-xs"><?php $dumper->dump($cloner->cloneVar($_POST ?? [])) ?></pre>
        </div>
        <div id="tab-files" class="tab-content hidden">
            <pre class="text-gray-100 rounded text-xs"><?php $dumper->dump($cloner->cloneVar($_FILES ?? [])) ?></pre>
        </div>
    </section>

    <!-- Dettagli (AI) -->
    <?php if ($subtitle !== '' && $verbose && $details !== ''): ?>
        <section class="bg-blue-50 dark:bg-blue-900 border-l-4 border-blue-500 p-4 rounded shadow mb-6">
            <h2 class="text-lg font-semibold mb-2 text-blue-700 dark:text-blue-300 flex items-center justify-between">
                <span>📝 <?= $e($labels['headings']['details'] ?? 'Details') ?></span>
                <button id="copyDetailsBtn" class="no-print text-xs px-2 py-1 rounded border border-blue-400 text-blue-800 dark:text-blue-100 bg-white/70 dark:bg-blue-800/40 hover:bg-white" aria-label="<?= $e($labels['labels']['copy_details'] ?? 'Copia dettagli') ?>">📋 <?= $e($labels['labels']['copy_details'] ?? 'Copia dettagli') ?></button>
            </h2>
            <pre id="detailsText" class="rounded text-xs whitespace-pre-wrap"><?= $e($details) ?></pre>
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
    (function(){
        const qs = s => document.querySelector(s);
        const qsa = s => Array.from(document.querySelectorAll(s));

        // Tabs
        qsa('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                qsa('.tab-btn').forEach(b => { b.classList.remove('border-b-2'); b.classList.remove('border-red-600'); });
                btn.classList.add('border-b-2');
                btn.classList.add('border-red-600');
                qsa('.tab-content').forEach(c => c.classList.add('hidden'));
                const t = document.getElementById('tab-' + btn.dataset.tab);
                if (t) t.classList.remove('hidden');
            });
        });

        // Theme toggle with persistence
        const toggleBtn = qs('#toggleTheme');
        if (toggleBtn) {
            const isDark = document.documentElement.classList.contains('dark');
            toggleBtn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            toggleBtn.addEventListener('click', () => {
                const nowDark = document.documentElement.classList.toggle('dark');
                toggleBtn.setAttribute('aria-pressed', nowDark ? 'true' : 'false');
                try { localStorage.setItem('errorView.theme', nowDark ? 'dark' : 'light'); } catch (e) {}
            });
        }

        // Frames expanded state persistence and toggling
        const stateKey = 'errorView.frames';
        let frameState = {};
        try { frameState = JSON.parse(localStorage.getItem(stateKey) || '{}') || {}; } catch (e) { frameState = {}; }

        function setExpanded(li, expanded) {
            const btn = li.querySelector('.toggle-frame');
            const details = li.querySelector('.frame-details');
            if (!btn || !details) return;
            if (expanded) details.classList.remove('hidden'); else details.classList.add('hidden');
            btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            const ind = btn.querySelector('.indicator');
            if (ind) ind.textContent = expanded ? '−' : '+';
            if (expanded) {
                const sig = details.getAttribute('data-sig') || '';
                const code = details.querySelector('.code-view');
                if (code) highlightParams(code, sig);
            }
        }

        function persistFrame(li, expanded) {
            const id = li.getAttribute('data-id');
            if (!id) return;
            frameState[id] = !!expanded;
            try { localStorage.setItem(stateKey, JSON.stringify(frameState)); } catch (e) {}
        }

        qsa('#traceList > li').forEach(li => {
            const btn = li.querySelector('.toggle-frame');
            if (btn) {
                btn.addEventListener('click', () => {
                    const details = li.querySelector('.frame-details');
                    const expanded = details.classList.contains('hidden');
                    const code = details.querySelector('.code-view');
                    if (code) {
                        code.querySelectorAll('span.hl-var').forEach(span => {
                            const text = document.createTextNode(span.textContent);
                            span.replaceWith(text);
                        });
                    }
                    setExpanded(li, expanded);
                    persistFrame(li, expanded);
                });
            }
            const id = li.getAttribute('data-id');
            if (id && frameState[id]) setExpanded(li, true);
        });

        // Search filtering
        const searchInput = qs('#traceSearch');
        const clearBtn = qs('#clearSearch');
        function applyFilter() {
            const q = (searchInput && searchInput.value ? searchInput.value : '').toLowerCase().trim();
            qsa('#traceList > li').forEach(li => {
                const hay = (li.getAttribute('data-search') || '');
                li.style.display = (q === '' || hay.indexOf(q) !== -1) ? '' : 'none';
            });
        }
        if (searchInput) searchInput.addEventListener('input', applyFilter);
        if (clearBtn) clearBtn.addEventListener('click', () => { if (searchInput) { searchInput.value = ''; applyFilter(); searchInput.focus(); } });

        // Copy title
        const copyBtn = qs('#copyBtn');
        if (copyBtn) {
            copyBtn.addEventListener('click', () => {
                const text = <?= $copyText ?>;
                const btnText = '<?= $e($labels['headings']['copy'] ?? 'Copy to clipboard') ?>';
                navigator.clipboard.writeText(text).then(() => {
                    copyBtn.textContent = '<?= $e($labels['headings']['copied'] ?? 'Copied!') ?>';
                    setTimeout(() => { copyBtn.textContent = btnText; }, 2000);
                });
            });
        }

        // Copy full stack
        const copyStackBtn = qs('#copyStackBtn');
        if (copyStackBtn) {
            copyStackBtn.addEventListener('click', () => {
                const lines = qsa('#traceList .trace-line').map(n => n.textContent.trim());
                const stackText = lines.join('\n');
                navigator.clipboard.writeText(stackText).then(() => {
                    const prev = copyStackBtn.textContent;
                    copyStackBtn.textContent = '<?= $e($labels['headings']['copied'] ?? 'Copied!') ?>';
                    setTimeout(() => { copyStackBtn.textContent = prev; }, 2000);
                });
            });
        }

        // Copy AI details
        const copyDetailsBtn = qs('#copyDetailsBtn');
        const detailsText = qs('#detailsText');
        if (copyDetailsBtn && detailsText) {
            copyDetailsBtn.addEventListener('click', () => {
                const txt = detailsText.textContent || '';
                navigator.clipboard.writeText(txt).then(() => {
                    const prev = copyDetailsBtn.textContent;
                    copyDetailsBtn.textContent = '<?= $e($labels['headings']['copied'] ?? 'Copied!') ?>';
                    setTimeout(() => { copyDetailsBtn.textContent = prev; }, 2000);
                });
            });
        }

        // Highlighting helpers
        function escapeRegExp(s){ return s.replace(/[.*+?^${}()|[\\]\\]/g, '\\$&'); }
        function highlightParams(codeEl, sig) {
            if (!sig) return;
            // unwrap existing highlights in this element
            codeEl.querySelectorAll('span.hl-var').forEach(span => {
                const text = document.createTextNode(span.textContent);
                span.replaceWith(text);
            });
            const m = sig.match(/\(([^)]*)\)/);
            if (!m) return;
            const inner = m[1];
            const vars = Array.from(new Set((inner.match(/\$[A-Za-z_][A-Za-z0-9_]*/g) || [])));
            if (vars.length === 0) return;
            let html = codeEl.innerHTML;
            vars.forEach(v => {
                const pattern = '(^|[^\\\\w$])(' + escapeRegExp(v) + ')(?![\\\\w$])';
                const re = new RegExp(pattern, 'g');
                html = html.replace(re, '$1<span class="hl-var">$2</span>');
            });
            codeEl.innerHTML = html;
        }
    })();
</script>
</body>
</html>
