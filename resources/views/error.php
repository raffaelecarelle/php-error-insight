<?php
/**
 * External template for HTML error page (Tailwind based)
 * Expected variables (extracted by Renderer):
 * - string $docLang
 * - string $title
 * - string $severity
 * - string $where
 * - string $summary
 * - bool   $verbose
 * - string $details
 * - array  $suggestions
 * - array  $frames (idx, sig, loc, localsDump, argsDump, codeHtml)
 * - array  $labels (headings{}, labels{})
 * - array  $stateDumps (object, globals_all, defined_vars, raw_trace, xdebug)
 */

// Basic escaping helper
$e = static function ($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
// Provide safe defaults when file is opened without expected scope (static analyzers)
$docLang = $docLang ?? 'it';
$title = $title ?? 'PHP Error Explainer';
$subtitle = $subtitle ?? '';
$severity = $severity ?? 'Error';
$where = $where ?? '';
$summary = $summary ?? '';
$verbose = $verbose ?? false;
$details = $details ?? '';
$suggestions = $suggestions ?? [];
$frames = $frames ?? [];
$labels = $labels ?? ['headings' => [],'labels' => []];
$stateDumps = $stateDumps ?? [];
?>
<!DOCTYPE html>
<html lang="<?= $e($docLang) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= $e($title) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* minimal styles specifically for code excerpt lines from pre-generated HTML */
    .code .ln{display:inline-block;width:3em;padding:0 8px;color:#94a3b8;background:#0b1220;text-align:right;user-select:none}
    .code .line{display:block;padding:0 8px}
    .code .hl{background:#1f2937}
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
  </style>
</head>
<body class="bg-slate-50 text-slate-900">
  <div class="max-w-5xl mx-auto p-6">
    <div class="bg-white shadow-sm ring-1 ring-slate-200 rounded-xl overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
        <div class="flex items-center justify-between gap-4">
          <div class="min-w-0">
            <h1 class="text-lg font-bold truncate"><?= $e($title) ?></h1>
            <?php if ($subtitle !== ''): ?>
              <div class="text-xs text-slate-600"><?= $e($subtitle) ?></div>
            <?php endif; ?>
          </div>
          <span class="text-xs text-slate-500"><?= $e($severity) ?></span>
        </div>
      </div>
      <div class="px-6 py-5 space-y-6">
        <?php if ($summary !== ''): ?>
          <p class="text-base"><?= $e($summary) ?></p>
        <?php endif; ?>

        <?php if (!empty($frames)): ?>
          <section>
            <h2 class="text-sm font-semibold text-slate-700 mb-2"><?= $e($labels['headings']['stack'] ?? 'Stack trace') ?></h2>
            <div class="divide-y divide-slate-200 border border-slate-200 rounded-lg overflow-hidden">
              <?php foreach ($frames as $i => $f): ?>
                <details class="group">
                  <summary class="flex items-center gap-3 p-3 cursor-pointer hover:bg-indigo-50/50">
                    <span class="w-8 text-right text-xs text-slate-500">#<?= $e($f['idx']) ?></span>
                    <span class="flex-1 truncate text-sm mono"><?= $e($f['sig']) ?></span>
                    <?php if (!empty($f['loc'])): ?>
                      <span class="text-xs text-slate-500 mono"><?= $e($f['loc']) ?></span>
                    <?php endif; ?>
                  </summary>
                  <div class="p-4 bg-white">
                    <div class="grid md:grid-cols-2 gap-4">
                      <div class="space-y-3">
                        <div>
                          <div class="text-xs font-semibold text-slate-600 mb-1"><?= $e($labels['labels']['locals'] ?? 'Locals') ?></div>
                          <pre class="mono text-xs bg-slate-50 ring-1 ring-slate-200 rounded p-2 overflow-auto"><?= $e($f['localsDump']) ?></pre>
                        </div>
                        <div>
                          <div class="text-xs font-semibold text-slate-600 mb-1"><?= $e($labels['labels']['arguments'] ?? 'Arguments') ?></div>
                          <pre class="mono text-xs bg-slate-50 ring-1 ring-slate-200 rounded p-2 overflow-auto"><?= $e($f['argsDump']) ?></pre>
                        </div>
                      </div>
                      <div>
                        <div class="text-xs font-semibold text-slate-600 mb-1"><?= $e($labels['labels']['code'] ?? 'Code excerpt') ?></div>
                        <div class="code mono text-[12px] bg-slate-900 text-slate-200 rounded ring-1 ring-slate-800 overflow-auto">
                          <?= $f['codeHtml'] /* already escaped per line */ ?>
                        </div>
                      </div>
                    </div>

                    <?php if (!empty($f['state'] ?? [])): ?>
                      <div class="mt-4 space-y-3">
                        <div class="text-xs font-semibold text-slate-700 mb-1"><?= $e($labels['headings']['state'] ?? 'State') ?></div>
                        <?php if (!empty($f['state']['object'] ?? '')): ?>
                          <div>
                            <div class="text-xs font-semibold text-slate-600 mb-1"><?= $e($labels['labels']['object'] ?? 'Current object') ?></div>
                            <pre class="mono text-xs bg-slate-50 ring-1 ring-slate-200 rounded p-2 overflow-auto"><?= $e($f['state']['object']) ?></pre>
                          </div>
                        <?php endif; ?>
                        <?php if (!empty($f['state']['globals_all'] ?? '')): ?>
                          <div>
                            <div class="text-xs font-semibold text-slate-600 mb-1"><?= $e($labels['labels']['globals_all'] ?? 'All globals') ?></div>
                            <pre class="mono text-xs bg-slate-50 ring-1 ring-slate-200 rounded p-2 overflow-auto"><?= $e($f['state']['globals_all']) ?></pre>
                          </div>
                        <?php endif; ?>
                        <?php if (!empty($f['state']['defined_vars'] ?? '')): ?>
                          <div>
                            <div class="text-xs font-semibold text-slate-600 mb-1"><?= $e($labels['labels']['defined_vars'] ?? 'Defined vars (scope)') ?></div>
                            <pre class="mono text-xs bg-slate-50 ring-1 ring-slate-200 rounded p-2 overflow-auto"><?= $e($f['state']['defined_vars']) ?></pre>
                          </div>
                        <?php endif; ?>
                        <?php if (!empty($f['state']['raw_trace'] ?? '')): ?>
                          <div>
                            <div class="text-xs font-semibold text-slate-600 mb-1"><?= $e($labels['labels']['raw_trace'] ?? 'Raw trace') ?></div>
                            <pre class="mono text-xs bg-slate-50 ring-1 ring-slate-200 rounded p-2 overflow-auto"><?= $e($f['state']['raw_trace']) ?></pre>
                          </div>
                        <?php endif; ?>
                        <?php if (!empty($f['state']['xdebug'] ?? '')): ?>
                          <div>
                            <div class="text-xs font-semibold text-slate-600 mb-1"><?= $e($labels['labels']['xdebug'] ?? 'Xdebug') ?></div>
                            <pre class="mono text-xs bg-slate-50 ring-1 ring-slate-200 rounded p-2 overflow-auto"><?= $e($f['state']['xdebug']) ?></pre>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </details>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if ($verbose && $details !== ''): ?>
          <section>
            <h2 class="text-sm font-semibold text-slate-700 mb-2"><?= $e($labels['headings']['details'] ?? 'Details') ?></h2>
            <pre class="mono text-sm whitespace-pre-wrap bg-slate-50 ring-1 ring-slate-200 rounded p-3 overflow-auto"><?= $e($details) ?></pre>
          </section>
        <?php endif; ?>

        <?php if (!empty($suggestions)): ?>
          <section>
            <h2 class="text-sm font-semibold text-slate-700 mb-2"><?= $e($labels['headings']['suggestions'] ?? 'Suggestions') ?></h2>
            <ul class="list-disc list-inside text-sm space-y-1">
              <?php foreach ($suggestions as $s): ?>
                <li><?= $e($s) ?></li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
