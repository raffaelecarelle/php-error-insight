<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Adapter\Render;

use PhpErrorInsight\Config;
use PhpErrorInsight\Contracts\RendererInterface;
use PhpErrorInsight\Internal\Model\Explanation;
use PhpErrorInsight\Internal\Translator;
use PhpErrorInsight\Internal\Util\ArrayUtil;
use PhpErrorInsight\Internal\Util\EnvUtil;
use PhpErrorInsight\Internal\Util\FileUtil;
use PhpErrorInsight\Internal\Util\HttpUtil;
use PhpErrorInsight\Internal\Util\PathUtil;
use PhpErrorInsight\Internal\Util\StringUtil;
use PhpErrorInsight\Internal\Util\TemplateUtil;
use RuntimeException;

use const ENT_QUOTES;

class HtmlRendererAdapter implements RendererInterface
{
    public function __construct(
        private readonly FileUtil $file = new FileUtil(),
        private readonly HttpUtil $http = new HttpUtil(),
        private readonly EnvUtil $env = new EnvUtil(),
        private readonly StringUtil $str = new StringUtil(),
        private readonly ArrayUtil $arr = new ArrayUtil(),
        private readonly PathUtil $path = new PathUtil(),
    ) {
    }

    public function render(Explanation $explanation, Config $config, string $kind, bool $isShutdown): void
    {
        if (!$this->env->isCliLike() && !$this->http->headersSent()) {
            $this->sendHtmlHeaders();
        }

        $template = $this->getTemplatePath($config);
        $data = $this->buildViewData($explanation, $config);

        if (!$this->file->isFile($template)) {
            throw new RuntimeException($this->str->sprintf('Template file "%s" not found', $template));
        }

        (new TemplateUtil())->includeWithData($template, $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildViewData(Explanation $explanation, Config $config): array
    {
        $docLang = '' !== $config->language && '0' !== $config->language ? $config->language : 'en';

        $original = $explanation->original ?? [];
        $origMessage = $explanation->message();
        $file = $original['file'] ?? '';
        $line = isset($original['line']) ? (string) $original['line'] : '';
        $where = $this->str->trim($file . ('' !== $line ? ":{$line}" : ''));

        // Title must be the error/warning message (fallback to previous title if missing)
        $aiTitle = Translator::t($config, 'title.ai');
        $subtitle = $explanation->title === $aiTitle ? $aiTitle : '';
        $severity = $explanation->severityLabel;
        $details = $explanation->details;
        $exceptionClass = $explanation->exceptionClass;
        $suggestions = $explanation->suggestions;

        $projectRoot = (null !== $config->projectRoot && '' !== $config->projectRoot && '0' !== $config->projectRoot)
            ? $config->projectRoot
            : $this->env->getCwd();

        if ('' !== $projectRoot) {
            $projectRoot = $this->path->rtrimSep($this->path->real($projectRoot));
        }

        $editorTpl = (null !== $config->editorUrl && '' !== $config->editorUrl && '0' !== $config->editorUrl)
            ? $config->editorUrl
            : $this->env->getEnv('PHP_ERROR_INSIGHT_EDITOR');

        $hostProjectRoot = (null !== $config->hostProjectRoot && '' !== $config->hostProjectRoot && '0' !== $config->hostProjectRoot)
            ? $config->hostProjectRoot
            : $this->env->getEnv('PHP_ERROR_INSIGHT_HOST_ROOT');

        if ('' !== $hostProjectRoot) {
            $hostProjectRoot = $this->path->rtrimSep($this->path->real($hostProjectRoot));
        }

        $frames = [];
        $idx = 0;
        foreach ($explanation->trace->frames as $frame) {
            $ff = $frame->file ?? '';
            $ll = $frame->line ?? 0;
            $frames[] = [
                'idx' => $idx,
                'sig' => $frame->signature(),
                'loc' => $frame->location(),
                'file' => $ff,
                'line' => $ll,
                'args' => $frame->args,
                'rel' => $this->path->makeRelative($ff, $projectRoot),
                'editorHref' => $this->path->toEditorHref($editorTpl, $ff, $ll, $projectRoot, $hostProjectRoot),
                'codeHtml' => $this->renderCodeExcerpt($ff, $ll),
            ];
            ++$idx;
        }

        $labels = $this->getLabels($config);

        // Attach full state to each frame and prepend origin as frame #0 (so it's part of the stack)
        $framesOut = [];
        if ('' !== $where) {
            $ff = $file;
            $ll = (int) $line;
            $framesOut[] = [
                'idx' => 0,
                'sig' => '(origin)',
                'loc' => $where,
                'file' => $ff,
                'line' => $ll,
                'args' => [],
                'rel' => $this->path->makeRelative($ff, $projectRoot),
                'editorHref' => $this->path->toEditorHref($editorTpl, $ff, $ll, $projectRoot, $hostProjectRoot),
                'codeHtml' => $this->renderCodeExcerpt($ff, $ll),
            ];
        }

        $startIdx = $this->arr->count($framesOut);
        foreach ($frames as $f) {
            // ensure numeric idx and shift by startIdx
            $f['idx'] += $startIdx;
            $framesOut[] = $f;
        }

        return [
            'docLang' => $docLang,
            'title' => $origMessage,
            'subtitle' => $subtitle,
            'severity' => $severity,
            'where' => $where,
            'verbose' => $config->verbose,
            'details' => $details,
            'suggestions' => $suggestions,
            'frames' => $framesOut,
            'labels' => $labels,
            'projectRoot' => $projectRoot,
            'editorUrl' => (string) $config->editorUrl,
            'aiModel' => (string) $config->model,
            'exceptionClass' => $exceptionClass,
        ];
    }

    private function renderCodeExcerpt(?string $file, ?int $line): string
    {
        if ($line < 1 || !$this->file->isFile($file)) {
            return '';
        }

        $lines = $this->file->fileLines($file);

        if ([] === $lines) {
            return '';
        }

        $total = $this->arr->count($lines);
        $start = max(1, $line - 5);
        $end = min($total, $line + 5);
        $numWidth = $this->str->length((string) $end);

        $output = '<div class="code-excerpt"><table class="code-table">';

        for ($ln = $start; $ln <= $end; ++$ln) {
            $codeLine = $lines[$ln - 1];
            $lineNum = $this->str->padLeft((string) $ln, $numWidth, ' ');

            $highlightedCode = $this->highlightText($codeLine);

            $rowClass = $ln === $line ? ' class="error-line"' : '';
            $output .= '<tr' . $rowClass . '>';
            $output .= '<td class="line-number">' . htmlspecialchars($lineNum, ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '<td class="code-content">' . $highlightedCode . '</td>';
            $output .= '</tr>';
        }

        return $output . '</table></div>';
    }

    private function sendHtmlHeaders(): void
    {
        $this->http->sendHeader('Content-Type: text/html; charset=utf-8');
        $this->http->setResponseCode(500);
    }

    private function getTemplatePath(Config $config): string
    {
        $template = $config->template ?? ($this->arr->isArray($this->env->getEnv('PHP_ERROR_INSIGHT_TEMPLATE')) ? null : $this->env->getEnv('PHP_ERROR_INSIGHT_TEMPLATE'));
        if ('' === $template || '0' === $template || null === $template) {
            $base = $this->path->dirName(__DIR__, 4);
            $template = $base . '/resources/views/error.php';
        }

        return $template;
    }

    private function highlightText(string $text): string
    {
        $text = highlight_string($text, true);
        $clean = preg_replace('|<code style="color: #[a-fA-F0-9]{6}">|', '<code>', $text);

        return $this->str->isString($clean) ? $clean : $text;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getLabels(Config $config): array
    {
        return [
            'headings' => [
                'details' => Translator::t($config, 'html.headings.details'),
                'env_details' => Translator::t($config, 'html.headings.env_details'),
                'suggestions' => Translator::t($config, 'html.headings.suggestions'),
                'stack' => Translator::t($config, 'html.headings.stack'),
                'info' => Translator::t($config, 'html.headings.info'),
            ],
            'labels' => [
                'code' => Translator::t($config, 'html.labels.code'),
                'get' => Translator::t($config, 'html.labels.get'),
                'post' => Translator::t($config, 'html.labels.post'),
                'cookie' => Translator::t($config, 'html.labels.cookie'),
                'session' => Translator::t($config, 'html.labels.session'),
                'language' => Translator::t($config, 'html.labels.language'),
                'ai_model' => Translator::t($config, 'html.labels.ai_model'),
                'editor_url' => Translator::t($config, 'html.labels.editor_url'),
                'verbose' => Translator::t($config, 'html.labels.verbose'),
            ],
            'toolbar' => [
                'copy_title' => Translator::t($config, 'html.toolbar.copy_title'),
                'copy_stack' => Translator::t($config, 'html.toolbar.copy_stack'),
                'open_in_editor' => Translator::t($config, 'html.toolbar.open_in_editor'),
                'theme' => Translator::t($config, 'html.toolbar.theme'),
            ],
            'stack' => [
                'expand_all' => Translator::t($config, 'html.stack.expand_all'),
                'collapse_all' => Translator::t($config, 'html.stack.collapse_all'),
                'copy' => Translator::t($config, 'html.stack.copy'),
                'open' => Translator::t($config, 'html.stack.open'),
            ],
            'tabs' => [
                'server_request' => Translator::t($config, 'html.tabs.server_request'),
                'env_vars' => Translator::t($config, 'html.tabs.env_vars'),
                'cookies' => Translator::t($config, 'html.tabs.cookies'),
                'session' => Translator::t($config, 'html.tabs.session'),
                'get' => Translator::t($config, 'html.tabs.get'),
                'post' => Translator::t($config, 'html.tabs.post'),
                'files' => Translator::t($config, 'html.tabs.files'),
            ],
            'messages' => [
                'no_excerpt' => Translator::t($config, 'html.messages.no_excerpt'),
                'rendered_by' => Translator::t($config, 'html.footer.rendered_by'),
            ],
            'aria' => [
                'page_actions' => Translator::t($config, 'html.aria.page_actions'),
                'toggle_theme' => Translator::t($config, 'html.aria.toggle_theme'),
                'stack_actions' => Translator::t($config, 'html.aria.stack_actions'),
                'copy_line' => Translator::t($config, 'html.aria.copy_line'),
                'code_excerpt' => Translator::t($config, 'html.aria.code_excerpt'),
                'server_dump' => Translator::t($config, 'html.aria.server_dump'),
                'env_tabs' => Translator::t($config, 'html.aria.env_tabs'),
                'frame_args' => Translator::t($config, 'html.aria.frame_args'),
            ],
            'js' => [
                'copied' => Translator::t($config, 'html.js.copied'),
                'title_copied' => Translator::t($config, 'html.js.title_copied'),
                'stack_copied' => Translator::t($config, 'html.js.stack_copied'),
            ],
            'badge' => [
                'severity' => Translator::t($config, 'html.badge.severity'),
            ],
        ];
    }
}
