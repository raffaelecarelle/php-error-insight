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
$details = $details ?? '';
$suggestions = $suggestions ?? [];
$frames = $frames ?? [];
$labels = $labels ?? ['headings' => [], 'labels' => []];
$editorUrl = $editorUrl ?? '';
$verbose = $verbose ?? '';
$aiModel = $aiModel ?? '';
$exceptionClass = $exceptionClass ?? '';
$fullTitle = trim(($title !== '' ? $title : 'Generic Error'));

// pick first available editor link (for toolbar action)
$firstEditor = '';
foreach ($frames as $f) {
    if (!empty($f['editorHref'])) {
        $firstEditor = (string)$f['editorHref'];
        break;
    }
}

$cloner = new \Symfony\Component\VarDumper\Cloner\VarCloner();
$dumper = new \Symfony\Component\VarDumper\Dumper\HtmlDumper();
$dumper->setStyles([
    'default' => 'background-color:transparent; color:inherit; line-height:1.5; font:12px var(--mono); word-wrap: break-word; white-space: pre-wrap;',
    'num' => 'font-weight:bold; color:#d19a66',
    'const' => 'font-weight:bold; color:#61afef',
    'str' => 'font-weight:bold; color:#98c379',
    'note' => 'color:#9fb0c0',
    'ref' => 'color:#7c8d9f',
    'public' => 'color:#e5c07b',
    'protected' => 'color:#c678dd',
    'private' => 'color:#e06c75',
    'meta' => 'color:#56b6c2',
    'key' => 'color:#61afef',
    'index' => 'color:#9fb0c0',
]);
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
            --primary: #0ea5e9;
            --primary-600: #0284c7;
            --ok: #22c55e;
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
            margin: 20px 0 0;
        }

        .subtitle {
            color: var(--muted);
            font-size: 13px;
            margin-top: 4px;
        }

        .location {
            color: var(--muted);
            font-family: var(--mono), serif;
            font-size: 12px;
            margin-top: 6px;
        }

        .toolbar {
            margin-top: 20px;
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
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }

        .grid-col-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 16px;
            min-width: 0;
        }

        .grid-col-right {
            flex: 3;
            display: flex;
            flex-direction: column;
            gap: 16px;
            min-width: 0;
        }

        @media (max-width: 980px) {
            .grid {
                flex-direction: column;
            }
            .grid-col-left,
            .grid-col-right {
                flex: 1;
            }
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
            grid-template-columns: 95px 1fr;
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

        .light .card h3 {
            background: #f8fafc;
            color: #334155;
        }

        .light .loc {
            color: #334155;
        }

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

        .light .tab:hover {
            background: #e2e8f0;
        }

        .light .tab.is-active {
            background: #ffffff;
            border-color: #cbd5e1;
            border-bottom-color: #ffffff;
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

        .light .card h3 {
            background: #f8fafc;
            color: #334155;
        }

        .light .loc {
            color: #334155;
        }

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

        .light .tab:hover {
            background: #e2e8f0;
        }

        .light .tab.is-active {
            background: #ffffff;
            border-color: #cbd5e1;
            border-bottom-color: #ffffff;
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

        /* Input Arguments accordion inside a frame */
        .args-accordion {
            border-top: 1px solid var(--border);
            background: var(--panel);
        }
        .args-head {
            /* inherits base styles from .frame-head */
            padding: 8px 12px;
            background: var(--panel);
            border-top: 1px solid var(--border);
            cursor: pointer;
        }
        .args-head .sig {
            font-weight: 600;
            color: var(--muted);
        }
        .args-head:hover {
            background: #1b2430;
        }
        .args-head:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }
        .args-head .chev { transform: rotate(0deg); }
        .args-head[aria-expanded="true"] .chev { transform: rotate(90deg); }
        .args-content { border-top: 1px solid var(--border); }
        /* Ensure args are visible even when the frame is collapsed; visibility controlled by [hidden] */
        .frame.collapsed .args-content { display: block; }
        .args-content[hidden] { display: none !important; }

        /* Light theme tweaks */
        .light .args-head { background: #f8fafc; }
        .light .args-head:hover { background: #e2e8f0; }
        .light .args-head .sig { color: #334155; }
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

        /* Symfony VarDumper styling integration */
        .dump pre.sf-dump, .sf-dump-default {
            background: transparent !important;
            border: 0 !important;
            margin: 0 !important;
            padding: 12px !important;
            line-height: 1.5 !important;
            font-size: 12px !important;
            font-family: var(--mono) !important;
        }

        .dump .sf-dump {
            color: var(--text) !important;
        }

        /* Dark theme colors for VarDumper */
        .dark .sf-dump-str { color: #98c379 !important; }
        .dark .sf-dump-num { color: #d19a66 !important; }
        .dark .sf-dump-const { color: #61afef !important; }
        .dark .sf-dump-note { color: #9fb0c0 !important; }
        .dark .sf-dump-ref { color: #7c8d9f !important; }
        .dark .sf-dump-public { color: #e5c07b !important; }
        .dark .sf-dump-protected { color: #c678dd !important; }
        .dark .sf-dump-private { color: #e06c75 !important; }
        .dark .sf-dump-meta { color: #56b6c2 !important; }
        .dark .sf-dump-key { color: #61afef !important; }
        .dark .sf-dump-index { color: #9fb0c0 !important; }
        .dark .sf-dump-ellipsis { color: #9fb0c0 !important; }
        .dark .sf-dump-ns { color: #9fb0c0 !important; user-select: none; }

        /* Light theme colors for VarDumper */
        .light .sf-dump-str { color: #22863a !important; }
        .light .sf-dump-num { color: #005cc5 !important; }
        .light .sf-dump-const { color: #005cc5 !important; }
        .light .sf-dump-note { color: #6a737d !important; }
        .light .sf-dump-ref { color: #6a737d !important; }
        .light .sf-dump-public { color: #e36209 !important; }
        .light .sf-dump-protected { color: #6f42c1 !important; }
        .light .sf-dump-private { color: #d73a49 !important; }
        .light .sf-dump-meta { color: #005cc5 !important; }
        .light .sf-dump-key { color: #005cc5 !important; }
        .light .sf-dump-index { color: #6a737d !important; }
        .light .sf-dump-ellipsis { color: #6a737d !important; }
        .light .sf-dump-ns { color: #6a737d !important; user-select: none; }

        /* VarDumper expand/collapse toggle styling */
        .sf-dump-compact .sf-dump-toggle {
            color: var(--primary) !important;
            cursor: pointer;
        }

        .sf-dump-compact .sf-dump-toggle:hover {
            color: var(--primary-600) !important;
        }

        /* VarDumper array/object brackets */
        .dark .sf-dump-public.sf-dump-highlight,
        .dark .sf-dump-protected.sf-dump-highlight,
        .dark .sf-dump-private.sf-dump-highlight {
            background: rgba(14, 165, 233, 0.15) !important;
        }

        .light .sf-dump-public.sf-dump-highlight,
        .light .sf-dump-protected.sf-dump-highlight,
        .light .sf-dump-private.sf-dump-highlight {
            background: rgba(14, 165, 233, 0.20) !important;
        }

        /* Search highlighting in dumps */
        .sf-dump-search-wrapper {
            background: var(--panel) !important;
            border: 1px solid var(--border) !important;
            padding: 8px !important;
            margin-bottom: 8px !important;
            border-radius: 6px !important;
        }

        .sf-dump-search-wrapper input {
            background: var(--code-bg) !important;
            border: 1px solid var(--border) !important;
            color: var(--text) !important;
            padding: 6px 10px !important;
            border-radius: 6px !important;
            font-family: var(--mono) !important;
            font-size: 12px !important;
        }

        .sf-dump-search-wrapper input:focus {
            outline: 2px solid var(--primary) !important;
            outline-offset: 0 !important;
        }

        /* Better spacing for nested structures */
        .sf-dump .sf-dump-compact {
            display: inline-block;
        }

        /* Scrollbar styling for dump sections */
        .dump::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .dump::-webkit-scrollbar-track {
            background: var(--code-bg);
        }

        .dump::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }

        .dump::-webkit-scrollbar-thumb:hover {
            background: var(--muted);
        }

        .light .dump::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .light .dump::-webkit-scrollbar-thumb {
            background: #cbd5e1;
        }

        .light .dump::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>
<body>
<div class="wrapper" id="app">
    <header class="header" aria-labelledby="page-title">
        <div class="header-top">
            <span class="badge severity" aria-label="<?= $e($labels['badge']['severity'] ?? 'Severity') ?>"><?= $e($severity) ?></span>
            <?php if($exceptionClass !== ''): ?>
                <span class="badge"><?= $e($exceptionClass) ?></span>
            <?php endif ?>
        </div>
        <h1 id="page-title" class="title"><?= $e($fullTitle) ?></h1>
        <?php if ($subtitle !== ''): ?>
            <div class="subtitle"><?= $e($subtitle) ?></div><?php endif; ?>
        <?php if ($where !== ''): ?>
            <div class="location">at <?= $e($where) ?></div><?php endif; ?>

        <div class="toolbar" role="toolbar" aria-label="<?= $e($labels['aria']['page_actions'] ?? 'Page actions') ?>">
            <button class="button primary i-title" id="copyTitle"> <?= $e($labels['toolbar']['copy_title'] ?? 'Copy title') ?></button>
            <button class="button i-stack" id="copyStack"> <?= $e($labels['toolbar']['copy_stack'] ?? 'Copy stack') ?></button>
            <?php if($firstEditor !== ''): ?>
                <button class="button i-link" id="openEditor"> <?= $e($labels['toolbar']['open_in_editor'] ?? 'Open in your editor') ?></button>
            <?php endif; ?>
            <button class="button i-moon" id="toggleTheme" aria-pressed="false" aria-label="<?= $e($labels['aria']['toggle_theme'] ?? 'Toggle theme') ?>"> <?= $e($labels['toolbar']['theme'] ?? 'Theme') ?></button>
        </div>
    </header>

    <main class="grid" aria-live="polite">
        <div class="grid-col-left">
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
        </div>

        <div class="grid-col-right">
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
                            <?php if (!empty($f['args'])): ?>
                                <div class="args-accordion">
                                    <div class="frame-head args-head" role="button" tabindex="0"
                                         aria-expanded="false" aria-controls="args-<?= $i ?>">
                                        <div class="frame-meta">
                                            <span class="chev" aria-hidden="true">▶</span>
                                            <div class="sig">
                                                <?= $e($labels['aria']['frame_args'] ?? 'Function arguments') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="args-<?= $i ?>" class="code args-content" role="region"
                                         aria-label="<?= $e($labels['aria']['frame_args'] ?? 'Function arguments') ?>" hidden>
                                        <pre><code><?= $e($dumper->dump($cloner->cloneVar($f['args'] ?? []))) ?></code></pre>
                                    </div>
                                </div>
                            <?php endif; ?>
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
                            <pre><code><?= $e($dumper->dump($cloner->cloneVar($_SERVER ?? []))) ?></code></pre>
                        </div>
                    </div>

                    <div id="tab-env" class="tabpanel" role="tabpanel" hidden tabindex="0" aria-labelledby="tabbtn-env">
                        <div class="code dump">
                            <pre><code><?= $e($dumper->dump($cloner->cloneVar($_ENV ?? []))) ?></code></pre>
                        </div>
                    </div>

                    <div id="tab-cookies" class="tabpanel" role="tabpanel" hidden tabindex="0"
                         aria-labelledby="tabbtn-cookies">
                        <div class="code dump">
                            <pre><code><?= $e($dumper->dump($cloner->cloneVar($_COOKIE ?? []))) ?></code></pre>
                        </div>
                    </div>

                    <div id="tab-session" class="tabpanel" role="tabpanel" hidden tabindex="0"
                         aria-labelledby="tabbtn-session">
                        <div class="code dump">
                            <pre><code><?= $e($dumper->dump($cloner->cloneVar($_SESSION ?? []))) ?></code></pre>
                        </div>
                    </div>

                    <div id="tab-get" class="tabpanel" role="tabpanel" hidden tabindex="0" aria-labelledby="tabbtn-get">
                        <div class="code dump">
                            <pre><code><?= $e($dumper->dump($cloner->cloneVar($_GET ?? []))) ?></code></pre>
                        </div>
                    </div>

                    <div id="tab-post" class="tabpanel" role="tabpanel" hidden tabindex="0" aria-labelledby="tabbtn-post">
                        <div class="code dump">
                            <pre><code><?= $e($dumper->dump($cloner->cloneVar($_POST ?? []))) ?></code></pre>
                        </div>
                    </div>

                    <div id="tab-files" class="tabpanel" role="tabpanel" hidden tabindex="0" aria-labelledby="tabbtn-files">
                        <div class="code dump">
                            <pre><code><?= $e($dumper->dump($cloner->cloneVar($_FILES ?? []))) ?></code></pre>
                        </div>
                    </div>
                </div>
            </section>
        </div>
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
    const THEME_KEY = 'phpesTheme';

    function applyTheme(isLight) {
        // Update ARIA and classes
        themeBtn?.setAttribute('aria-pressed', String(isLight));
        root.classList.toggle('light', isLight);
        themeBtn?.classList.toggle('i-sun', isLight);
        themeBtn?.classList.toggle('i-moon', !isLight);
    }

    // Initialize from localStorage (remember user's last choice)
    let light = false;
    try {
        light = (localStorage.getItem(THEME_KEY) === 'light');
    } catch (e) {
        // localStorage might be unavailable; fall back to default (dark)
    }
    applyTheme(light);

    // Toggle and persist preference
    themeBtn?.addEventListener('click', () => {
        light = !light;
        applyTheme(light);
        try {
            if (light) {
                localStorage.setItem(THEME_KEY, 'light');
            } else {
                localStorage.removeItem(THEME_KEY);
            }
        } catch (e) {
            // ignore storage errors
        }
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

    function setArgsAccordionOpenAll(open) {
        $$('.args-accordion').forEach(acc => {
            const head = acc.querySelector('.args-head');
            const content = acc.querySelector('.args-content');
            if (!head || !content) return;
            head.setAttribute('aria-expanded', String(open));
            if (open) {
                content.removeAttribute('hidden');
            } else {
                content.setAttribute('hidden', '');
            }
        });
    }

    $('#expandAll')?.addEventListener('click', () => {
        $$('.frame').forEach(f => setCollapsed(f, false));
        setArgsAccordionOpenAll(true);
    });
    $('#collapseAll')?.addEventListener('click', () => {
        $$('.frame').forEach(f => setCollapsed(f, true));
        setArgsAccordionOpenAll(false);
    });

    // Arguments accordion behavior
    $$('.args-accordion').forEach(acc => {
        const head = acc.querySelector('.args-head');
        const content = acc.querySelector('.args-content');
        if (!head || !content) return;
        function setOpen(open) {
            head.setAttribute('aria-expanded', String(open));
            if (open) {
                content.removeAttribute('hidden');
            } else {
                content.setAttribute('hidden', '');
            }
        }
        head.addEventListener('click', (e) => {
            // Prevent toggling the whole frame when clicking the args header
            e.stopPropagation();
            const open = head.getAttribute('aria-expanded') === 'true';
            setOpen(!open);
        });
        head.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();
                const open = head.getAttribute('aria-expanded') === 'true';
                setOpen(!open);
            }
        });
    });
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