<?php
// PHP Error Insight — Error View (templated from docs/index.html)
// Uses data from Renderer::buildViewData

$e = static function ($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};
$docLang = $docLang ?? 'en';
$title = $title ?? 'PHP Error Insight';
$subtitle = $subtitle ?? '';
$where = $where ?? '';
$severity = $severity ?? 'Error';
$summary = $summary ?? '';
$details = $details ?? '';
$suggestions = $suggestions ?? [];
$frames = $frames ?? [];
$labels = $labels ?? ['headings' => [], 'labels' => []];
$editorUrl = $editorUrl ?? '';
$verbose = $verbose ?? '';
$aiModel = $aiModel ?? '';
$fullTitle = trim(($title !== '' ? $title : 'Error') . ($where !== '' ? ' in ' . $where : ''));
$copyText = json_encode($fullTitle, JSON_UNESCAPED_UNICODE);

// pick first available editor link (for toolbar action)
$firstEditor = '';
foreach ($frames as $f) {
    if (!empty($f['editorHref'])) {
        $firstEditor = (string)$f['editorHref'];
        break;
    }
}
?>
<!doctype html>
<html lang="<?= $e($docLang) ?>" class="dark">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><?= $e($fullTitle) ?></title>
    <style>
        /* PHP Error Insight — Professional MVP Styles */
        :root {
            --bg: #0f1216;
            --panel: #161b22;
            --panel-2: #0d1117;
            --text: #e6edf3;
            --muted: #9fb0c0;
            --primary: #0ea5e9; /* cyan-500 */
            --primary-600: #0284c7;
            --accent: #f59e0b; /* amber */
            --danger: #ef4444; /* red */
            --ok: #22c55e; /* green */
            --border: #263241;
            --code-bg: #0b0f14;
            --mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            --sans: Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: var(--sans);
        }

        .wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }

        .header {
            background: linear-gradient(180deg, rgba(14, 165, 233, 0.12), rgba(14, 165, 233, 0) 60%);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px 20px 12px;
        }

        .header-top {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .badge {
            font-weight: 600;
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: var(--panel);
            color: var(--muted);
        }

        .badge.severity {
            background: rgba(239, 68, 68, .12);
            color: #fecaca;
            border-color: #7f1d1d;
        }

        .title {
            font-size: 20px;
            font-weight: 700;
            line-height: 1.3;
            margin: 6px 0 0;
        }

        .subtitle {
            color: var(--muted);
            font-size: 13px;
            margin-top: 4px;
        }

        .location {
            color: var(--muted);
            font-family: var(--mono);
            font-size: 12px;
            margin-top: 6px;
        }

        .toolbar {
            margin-top: 14px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .button {
            appearance: none;
            border: 1px solid var(--border);
            background: var(--panel);
            color: var(--text);
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .button:hover {
            background: #1f2630;
            border-color: #35506b;
        }

        .button.primary {
            background: var(--primary);
            border-color: var(--primary-600);
            color: #001018;
            font-weight: 600;
        }

        .button.primary:hover {
            background: var(--primary-600);
        }

        .grid {
            margin-top: 18px;
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 16px;
        }

        @media (max-width: 980px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }

        /* Layout tweak when AI features are disabled: place Env details on the right, PHP Error Insight Info on the left,
           and make Stack trace span full width on top. This applies only when .no-ai is on the grid container. */
        .grid.no-ai > [aria-labelledby="sec-stack"] {
            grid-column: 1 / -1;
        }
        .grid.no-ai > [aria-labelledby="sec-env-details"] {
            grid-column: 2;
        }
        .grid.no-ai > [aria-labelledby="sec-env"] {
            grid-column: 1;
        }

        .card {
            background: var(--panel-2);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        .card h3 {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: .02em;
            text-transform: uppercase;
            color: var(--muted);
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            background: var(--panel);
        }

        .card .content {
            padding: 12px 14px;
        }

        .card .content p {
            margin: 0 0 10px;
            color: var(--text);
            opacity: .95;
        }

        /* Toolbar inside cards (e.g., Stack trace actions) */
        .card > .toolbar {
            padding: 8px 12px 0;
        }

        .stack {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .frame {
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--panel);
            overflow: hidden;
        }

        .frame-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            cursor: pointer;
        }

        .frame-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        .sig {
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .loc {
            color: var(--muted);
            font-family: var(--mono);
            font-size: 12px;
        }

        .row-actions {
            display: flex;
            gap: 6px;
        }

        .code {
            background: var(--code-bg);
            border-top: 1px solid var(--border);
            font-family: var(--mono);
            font-size: 12px;
            overflow: auto;
        }

        .code pre {
            margin: 0;
            padding: 0px 6px;
            line-height: 1.45;
        }

        .code .line {
            display: flex;
            gap: 12px;
        }

        .code .gutter {
            width: 40px;
            text-align: right;
            color: #6b7d90;
            opacity: .75;
            user-select: none;
        }

        .code .src {
            white-space: pre;
            color: #dbe7f3;
        }

        .code .hl {
            background: rgba(14, 165, 233, 0.12);
        }

        .kv {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 6px 12px;
            font-family: var(--mono);
            font-size: 12px;
            color: var(--muted);
        }

        .kv .k {
            color: #9fb8cc;
        }

        .kv .v {
            color: #dbe7f3;
        }

        .suggestions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .suggestion {
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--panel);
            padding: 10px 12px;
        }

        .suggestion .title {
            font-size: 14px;
            margin: 0 0 6px;
        }

        .suggestion p {
            margin: 0;
            color: var(--text);
            opacity: .95;
        }

        .footer {
            margin: 22px 0;
            color: var(--muted);
            font-size: 12px;
            text-align: center;
        }

        /* Light theme */
        .light {
            --bg: #f5f7fa;
            --panel: #ffffff;
            --panel-2: #ffffff;
            --text: #0b1220;
            --muted: #334155;
            --border: #94a3b8;
            --code-bg: #ffffff;
        }

        .light .badge.severity {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .light .button {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .light .button:hover {
            background: #e2e8f0;
            border-color: #94a3b8;
        }

        /* Collapsible frames */
        .frame.collapsed .code {
            display: none;
        }

        .chev {
            display: inline-block;
            transform: rotate(90deg);
            transition: transform .15s ease;
            margin-right: 6px;
            color: var(--muted);
        }

        .frame.collapsed .chev {
            transform: rotate(0deg);
        }

        /* Higher contrast for light theme section header and locations */
        .light .card h3 {
            background: #f8fafc;
            color: #334155;
        }

        .light .loc {
            color: #334155;
        }

        /* Copy feedback */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0 0 0 0);
            white-space: nowrap;
            border: 0;
        }

        @keyframes pulseCopied {
            0% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.5);
            }
            100% {
                box-shadow: 0 0 0 10px rgba(34, 197, 94, 0);
            }
        }

        .button.copied {
            background: var(--ok);
            border-color: #16a34a;
            color: #04130a;
            animation: pulseCopied .6s ease;
        }

        /* Light theme code and table contrast tweaks */
        .light .code {
            background: #ffffff;
        }

        .light .code .src {
            color: #0b1220;
        }

        .light .code .gutter {
            color: #64748b;
        }

        .light .code .hl {
            background: rgba(14, 165, 233, 0.18);
        }

        .light .kv {
            color: #334155;
        }

        .light .kv .k {
            color: #475569;
        }

        .light .kv .v {
            color: #0b1220;
        }

        /* Extra styles for Renderer::renderCodeExcerpt() output */
        .code-excerpt {
            border-top: 1px solid var(--border);
        }

        .code-table {
            width: 100%;
            border-collapse: collapse;
        }

        .code-table td {
            padding: 6px 10px;
        }

        .line-number {
            width: 1%;
            white-space: nowrap;
            text-align: right;
            color: #9fb0c0;
        }

        .code-content {
            width: 99%;
            color: #dbe7f3;
        }

        .error-line .line-number, .error-line .code-content {
            background: rgba(239, 68, 68, .15);
            color: #fecaca;
            font-weight: 700;
        }

        .light .line-number {
            color: #64748b;
        }

        .light .code-content {
            color: #0b1220;
        }

        /* Tabs for Environment Details */
        .tabs {
            display: flex;
            gap: 8px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .tab {
            appearance: none;
            border: 1px solid transparent;
            border-bottom: none;
            background: transparent;
            color: var(--muted);
            padding: 8px 10px;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-size: 13px;
        }

        .tab:hover {
            color: var(--text);
            background: #1b2430;
            border-color: var(--border);
            border-bottom-color: transparent;
        }

        .tab.is-active {
            background: var(--panel);
            color: var(--text);
            border-color: var(--border);
            border-bottom-color: var(--panel);
            font-weight: 600;
        }

        .tabpanel {
            border: 1px solid var(--border);
            border-radius: 0 10px 10px 10px;
            background: var(--panel);
            padding: 0;
        }

        .tabpanel .code {
            border: 0;
        }

        .light .tab:hover {
            background: #e2e8f0;
        }

        .light .tab.is-active {
            background: #ffffff;
            border-color: #cbd5e1;
            border-bottom-color: #ffffff;
        }
    </style>
    <style>
        /* tiny icons via CSS only */
        .i-copy::before {
            content: "📋"
        }

        .i-sun::before {
            content: "☀️"
        }

        .i-moon::before {
            content: "🌙"
        }

        .i-stack::before {
            content: "📚"
        }

        .i-link::before {
            content: "🔗"
        }

        .i-title::before {
            content: "🏷️"
        }
    </style>
</head>
<body>
<div class="wrapper" id="app">
    <header class="header" aria-labelledby="page-title">
        <div class="header-top">
            <span class="badge severity" aria-label="<?= $e($labels['badge']['severity'] ?? 'Severity') ?>"><?= $e($severity) ?></span>
            <span class="badge">PHP Error Insight</span>
        </div>
        <h1 id="page-title" class="title"><?= $e($fullTitle) ?></h1>
        <?php if ($subtitle !== ''): ?>
            <div class="subtitle"><?= $e($subtitle) ?></div><?php endif; ?>
        <?php if ($where !== ''): ?>
            <div class="location"><?= $e($where) ?></div><?php endif; ?>

        <div class="toolbar" role="toolbar" aria-label="<?= $e($labels['aria']['page_actions'] ?? 'Page actions') ?>">
            <button class="button primary i-title" id="copyTitle"> <?= $e($labels['toolbar']['copy_title'] ?? 'Copy title') ?></button>
            <button class="button i-stack" id="copyStack"> <?= $e($labels['toolbar']['copy_stack'] ?? 'Copy stack') ?></button>
            <button class="button i-link" id="openEditor" <?= $firstEditor === '' ? 'disabled' : '' ?>> <?= $e($labels['toolbar']['open_in_editor'] ?? 'Open in your editor') ?></button>
            <button class="button i-moon" id="toggleTheme" aria-pressed="false" aria-label="<?= $e($labels['aria']['toggle_theme'] ?? 'Toggle theme') ?>"> <?= $e($labels['toolbar']['theme'] ?? 'Theme') ?></button>
        </div>
    </header>

    <?php $noAi = ($summary === '' && $details === '' && empty($suggestions)); ?>
    <main class="grid<?= $noAi ? ' no-ai' : '' ?>" aria-live="polite">
        <?php if ($summary !== ''): ?>
            <section class="card" aria-labelledby="sec-summary">
                <h3 id="sec-summary"><?= $e($labels['headings']['summary'] ?? 'Summary') ?></h3>
                <div class="content">
                    <p><?= $e($summary) ?></p>
                </div>
            </section>
        <?php endif; ?>

        <section class="card" aria-labelledby="sec-stack">
            <h3 id="sec-stack"><?= $e($labels['headings']['stack'] ?? 'Stack trace') ?></h3>
            <div class="toolbar" role="toolbar" aria-label="<?= $e($labels['aria']['stack_actions'] ?? 'Stack actions') ?>">
                <button class="button" id="expandAll"><?= $e($labels['stack']['expand_all'] ?? 'Expand all') ?></button>
                <button class="button" id="collapseAll"><?= $e($labels['stack']['collapse_all'] ?? 'Collapse all') ?></button>
            </div>
            <div class="content stack">
                <?php $i = 0 ?>
                <?php foreach ($frames as $f): $rel = (string)($f['rel'] ?? '');
                    $loc = (string)($f['loc'] ?? '');
                    $ln = (int)($f['line'] ?? 0);
                    $sig = (string)($f['sig'] ?? '');
                    $href = (string)($f['editorHref'] ?? '');
                    $copy = $rel !== '' && $ln ? ($rel . ':' . $ln) : $loc;
                    $isFirst = $i === 0; ?>
                    <article class="frame <?= !$isFirst ? 'collapsed' : '' ?>" aria-label="Frame <?= $e((string)($f['idx'] ?? '')) ?>">
                        <div class="frame-head" role="button" tabindex="0" aria-expanded="true">
                            <div class="frame-meta">
                                <span class="chev" aria-hidden="true">▶</span>
                                <div class="sig" title="<?= $e($rel !== '' ? ($rel . ($ln ? ':' . $ln : '')) : $loc) ?>"><?= $e($sig) ?></div>
                            </div>
                            <div class="row-actions">
                                <button class="button i-copy" data-copy="<?= $e($copy) ?>" aria-label="<?= $e($labels['aria']['copy_line'] ?? 'Copy line') ?>">
                                    <?= $e($labels['stack']['copy'] ?? 'Copy') ?>
                                </button>
                                <?php if ($href !== ''): ?>
                                    <a class="button i-link" href="<?= $e($href) ?>" title="<?= $e($labels['toolbar']['open_in_editor'] ?? 'Open in your editor') ?>">
                                        <?= $e($labels['stack']['open'] ?? 'Open') ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="code" role="region" aria-label="<?= $e($labels['aria']['code_excerpt'] ?? 'Code excerpt') ?>">
                            <?php if (!empty($f['codeHtml'])): ?>
                                <?= $f['codeHtml'] ?>
                            <?php else: ?>
                                <pre><code><em><?= $e($labels['messages']['no_excerpt'] ?? 'No excerpt available') ?></em></code></pre>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php $i++ ?>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($details !== ''): ?>
            <section class="card" aria-labelledby="sec-details">
                <h3 id="sec-details"><?= $e($labels['headings']['details'] ?? 'Details') ?></h3>
                <div class="content">
                    <p><?= nl2br($e($details)) ?></p>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($suggestions)): ?>
            <section class="card" aria-labelledby="sec-suggestions">
                <h3 id="sec-suggestions"><?= $e($labels['headings']['suggestions'] ?? 'Suggestions') ?></h3>
                <div class="content suggestions">
                    <?php foreach ($suggestions as $s): ?>
                        <div class="suggestion">
                            <p class="title"><?= $e($labels['labels']['suggestion'] ?? 'Suggestion') ?></p>
                            <p><?= $e((string)$s) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="card" aria-labelledby="sec-env">
            <h3 id="sec-env"><?= $e($labels['headings']['info'] ?? 'PHP Error Insight Info') ?></h3>
            <div class="content">
                <div class="kv">
                    <div class="k"><?= $e($labels['labels']['language'] ?? 'Language') ?></div>
                    <div class="v"><?php echo $docLang; ?></div>
                    <?php if ($aiModel !== ''): ?>
                        <div class="k"><?= $e($labels['labels']['ai_model'] ?? 'AI Model') ?></div>
                        <div class="v"><?php echo $aiModel ?></div>
                    <?php endif; ?>
                    <?php if ($editorUrl !== ''): ?>
                        <div class="k"><?= $e($labels['labels']['editor_url'] ?? 'Editor URL') ?></div>
                        <div class="v"><?php echo $editorUrl ?></div>
                    <?php endif; ?>
                    <div class="k"><?= $e($labels['labels']['verbose'] ?? 'Verbose') ?></div>
                    <div class="v"><?php echo (bool)$verbose ?></div>
                </div>
            </div>
        </section>

        <section class="card" aria-labelledby="sec-env-details">
            <h3 id="sec-env-details"><?= $e($labels['headings']['env_details'] ?? 'Environment Details') ?></h3>
            <div class="content">
                <div class="tabs" role="tablist" aria-label="<?= $e($labels['aria']['env_tabs'] ?? 'Environment tabs') ?>">
                    <button class="tab is-active" role="tab" aria-selected="true" aria-controls="tab-server"
                            id="tabbtn-server"><?= $e($labels['tabs']['server_request'] ?? 'Server/Request') ?>
                    </button>
                    <button class="tab" role="tab" aria-selected="false" aria-controls="tab-env" id="tabbtn-env"
                            tabindex="-1"><?= $e($labels['tabs']['env_vars'] ?? 'Env Vars') ?>
                    </button>
                    <button class="tab" role="tab" aria-selected="false" aria-controls="tab-cookies" id="tabbtn-cookies"
                            tabindex="-1"><?= $e($labels['tabs']['cookies'] ?? 'Cookies') ?>
                    </button>
                    <button class="tab" role="tab" aria-selected="false" aria-controls="tab-session" id="tabbtn-session"
                            tabindex="-1"><?= $e($labels['tabs']['session'] ?? 'Session') ?>
                    </button>
                    <button class="tab" role="tab" aria-selected="false" aria-controls="tab-get" id="tabbtn-get"
                            tabindex="-1"><?= $e($labels['tabs']['get'] ?? ($labels['labels']['get'] ?? 'GET')) ?>
                    </button>
                    <button class="tab" role="tab" aria-selected="false" aria-controls="tab-post" id="tabbtn-post"
                            tabindex="-1"><?= $e($labels['tabs']['post'] ?? ($labels['labels']['post'] ?? 'POST')) ?>
                    </button>
                    <button class="tab" role="tab" aria-selected="false" aria-controls="tab-files" id="tabbtn-files"
                            tabindex="-1"><?= $e($labels['tabs']['files'] ?? 'Files') ?>
                    </button>
                </div>

                <div id="tab-server" class="tabpanel" role="tabpanel" tabindex="0" aria-labelledby="tabbtn-server">
                    <div class="code dump" role="region" aria-label="<?= $e($labels['aria']['server_dump'] ?? 'Server / Request dump') ?>">
                        <pre><code><?= $e(var_export($_SERVER ?? [], true)) ?></code></pre>
                    </div>
                </div>

                <div id="tab-env" class="tabpanel" role="tabpanel" hidden tabindex="0" aria-labelledby="tabbtn-env">
                    <div class="code dump">
                        <pre><code><?= $e(var_export($_ENV ?? [], true)) ?></code></pre>
                    </div>
                </div>

                <div id="tab-cookies" class="tabpanel" role="tabpanel" hidden tabindex="0"
                     aria-labelledby="tabbtn-cookies">
                    <div class="code dump">
                        <pre><code><?= $e(var_export($_COOKIE ?? [], true)) ?></code></pre>
                    </div>
                </div>

                <div id="tab-session" class="tabpanel" role="tabpanel" hidden tabindex="0"
                     aria-labelledby="tabbtn-session">
                    <div class="code dump">
                        <pre><code><?= $e(var_export($_SESSION ?? [], true)) ?></code></pre>
                    </div>
                </div>

                <div id="tab-get" class="tabpanel" role="tabpanel" hidden tabindex="0" aria-labelledby="tabbtn-get">
                    <div class="code dump">
                        <pre><code><?= $e(var_export($_GET ?? [], true)) ?></code></pre>
                    </div>
                </div>

                <div id="tab-post" class="tabpanel" role="tabpanel" hidden tabindex="0" aria-labelledby="tabbtn-post">
                    <div class="code dump">
                        <pre><code><?= $e(var_export($_POST ?? [], true)) ?></code></pre>
                    </div>
                </div>

                <div id="tab-files" class="tabpanel" role="tabpanel" hidden tabindex="0" aria-labelledby="tabbtn-files">
                    <div class="code dump">
                        <pre><code><?= $e(var_export($_FILES ?? [], true)) ?></code></pre>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <div class="footer"><?= $e($labels['messages']['rendered_by'] ?? 'Rendered by PHP Error Insight') ?></div>
</div>

<script>
    const $ = sel => document.querySelector(sel);
    const $$ = sel => Array.from(document.querySelectorAll(sel));

    // Accessible live region for copy feedback
    const live = document.createElement('div');
    live.setAttribute('aria-live', 'polite');
    live.setAttribute('aria-atomic', 'true');
    live.className = 'sr-only';
    document.body.appendChild(live);

    function copy(text) {
        try {
            const input = document.createElement('textarea')
            input.value = text
            document.body.appendChild(input)
            input.select()
            document.execCommand('copy')
            document.body.removeChild(input)
            return Promise.resolve();
        } catch (e) {
            return Promise.resolve();
        }
    }

    function flashCopied(el, labelCopied = <?= json_encode($labels['js']['copied'] ?? 'Copied!') ?>) {
        if (!el) return;
        const prevText = el.textContent;
        const prevAria = el.getAttribute('aria-label');
        el.classList.add('copied');
        if (prevAria) el.setAttribute('aria-label', labelCopied);
        el.textContent = ' ' + labelCopied;
        live.textContent = labelCopied;
        setTimeout(() => {
            el.classList.remove('copied');
            if (prevAria) el.setAttribute('aria-label', prevAria);
            el.textContent = prevText;
        }, 1200);
    }

    $('#copyTitle')?.addEventListener('click', (e) => {
        const btnEl = e.currentTarget;
        const titleText = $('#page-title')?.textContent?.trim() || document.title;
        copy(titleText).then(() => flashCopied(btnEl, <?= json_encode($labels['js']['title_copied'] ?? 'Title copied!') ?>)).catch(() => {
        });
    });

    $('#copyStack')?.addEventListener('click', (e) => {
        const btnEl = e.currentTarget;
        const lines = $$('.frame .sig').map(e => e.textContent?.trim()).join("\n");
        copy(lines).then(() => flashCopied(btnEl, <?= json_encode($labels['js']['stack_copied'] ?? 'Stack copied!') ?>)).catch(() => {
        });
    });

    $$('.row-actions .button.i-copy').forEach(b => {
        b.addEventListener('click', (e) => {
            const btnEl = e.currentTarget;
            const txt = b.getAttribute('data-copy') || '';
            copy(txt).then(() => flashCopied(btnEl, <?= json_encode($labels['js']['copied'] ?? 'Copied!') ?>)).catch(() => {
            });
            e.stopPropagation();
        });
    });

    // Open in editor (first available)
    (function () {
        const href = <?= json_encode($firstEditor, JSON_UNESCAPED_SLASHES) ?>;
        const btn = $('#openEditor');
        if (btn && href) {
            btn.addEventListener('click', () => {
                try {
                    window.location.href = href;
                } catch (e) {
                }
            });
        } else if (btn) {
            btn.disabled = true;
        }
    })();

    const root = document.documentElement;
    const themeBtn = $('#toggleTheme');
    let light = false;
    themeBtn?.addEventListener('click', () => {
        light = !light;
        themeBtn.setAttribute('aria-pressed', String(light));
        root.classList.toggle('light', light);
        themeBtn.classList.toggle('i-sun', light);
        themeBtn.classList.toggle('i-moon', !light);
    });

    // Collapsible stack frames
    function setCollapsed(frame, collapsed) {
        frame.classList.toggle('collapsed', collapsed);
        const head = frame.querySelector('.frame-head');
        head?.setAttribute('aria-expanded', String(!collapsed));
    }

    function toggleFrame(frame) {
        const isCollapsed = frame.classList.contains('collapsed');
        setCollapsed(frame, !isCollapsed);
    }

    $$('.frame').forEach(frame => {
        const head = frame.querySelector('.frame-head');
        head?.addEventListener('click', (e) => {
            if ((e.target instanceof Element) && e.target.closest('.row-actions')) return;
            toggleFrame(frame);
        });
        head?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleFrame(frame);
            }
        });
    });

    $('#expandAll')?.addEventListener('click', () => $$('.frame').forEach(f => setCollapsed(f, false)));
    $('#collapseAll')?.addEventListener('click', () => $$('.frame').forEach(f => setCollapsed(f, true)));
</script>
<script>
    // Accessible Tabs for Environment Details (same behavior as docs)
    (function () {
        const list = document.querySelector('.tabs');
        if (!list) return;
        const tabs = Array.from(list.querySelectorAll('.tab'));
        const panels = Array.from(document.querySelectorAll('.tabpanel'));
        const idxOf = (btn) => Math.max(0, tabs.indexOf(btn));

        function activate(index) {
            tabs.forEach((t, i) => {
                const selected = i === index;
                t.classList.toggle('is-active', selected);
                t.setAttribute('aria-selected', String(selected));
                t.tabIndex = selected ? 0 : -1;
                const id = t.getAttribute('aria-controls');
                const panel = id ? document.getElementById(id) : null;
                if (panel) {
                    selected ? panel.removeAttribute('hidden') : panel.setAttribute('hidden', '');
                }
            });
            tabs[index]?.focus();
        }

        tabs.forEach((btn) => {
            btn.addEventListener('click', () => activate(idxOf(btn)));
            btn.addEventListener('keydown', (e) => {
                const i = idxOf(btn);
                let ni = i;
                if (e.key === 'ArrowRight') {
                    ni = (i + 1) % tabs.length;
                    e.preventDefault();
                } else if (e.key === 'ArrowLeft') {
                    ni = (i - 1 + tabs.length) % tabs.length;
                    e.preventDefault();
                } else if (e.key === 'Home') {
                    ni = 0;
                    e.preventDefault();
                } else if (e.key === 'End') {
                    ni = tabs.length - 1;
                    e.preventDefault();
                }
                if (ni !== i) activate(ni);
            });
        });
    })();
</script>
</body>
</html>
