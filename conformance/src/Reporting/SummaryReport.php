<?php

declare(strict_types=1);

namespace Conformance\Reporting;

use Conformance\Discovery\TestCase;
use Conformance\TestGroup\TestGroup;
use Internal\Toml\Toml;
use RuntimeException;
use function htmlspecialchars;
use function preg_match;
use function preg_replace_callback;
use function sprintf;
use function trim;

final class SummaryReport
{
    private const DETAILS_DIR = 'tests';
    private const INDEX_FILE = 'results.html';

    /**
     * @param array<string, TestGroup> $testGroups
     * @param list<TestCase> $testCases
     * @param list<string> $tools
     */
    public function generate(
        string $resultsRoot,
        string $outputPath,
        array $testGroups,
        array $testCases,
        array $tools,
    ): void {
        $detailsDir = $resultsRoot . DIRECTORY_SEPARATOR . self::DETAILS_DIR;
        $this->prepareDetailsDir($detailsDir);

        // Detail pages first so the index can link to known files.
        foreach ($testCases as $testCase) {
            $group = $testGroups[$testCase->groupKey] ?? null;
            $detailHtml = $this->renderDetailPage($resultsRoot, $testCase, $group, $tools);
            $detailPath = $detailsDir . DIRECTORY_SEPARATOR . $testCase->name . '.html';

            if (file_put_contents($detailPath, $detailHtml) === false) {
                throw new RuntimeException(sprintf('Failed to write detail page: %s', $detailPath));
            }
        }

        $indexHtml = $this->renderIndexPage($resultsRoot, $testGroups, $testCases, $tools);

        if (file_put_contents($outputPath, $indexHtml) === false) {
            throw new RuntimeException(sprintf('Failed to write summary report: %s', $outputPath));
        }
    }

    /**
     * @param array<string, TestGroup> $testGroups
     * @param list<TestCase> $testCases
     * @param list<string> $tools
     */
    private function renderIndexPage(
        string $resultsRoot,
        array $testGroups,
        array $testCases,
        array $tools,
    ): string {
        $body = [];
        $body[] = '<h1>PHP Typing Conformance Results</h1>';
        $body[] = '<p class="lead">Each row links to a per-test detail page with the source, expectations, and every analyzer&rsquo;s raw output.</p>';
        $body[] = '<table>';
        $body[] = '<thead><tr><th>Test</th>';

        foreach ($tools as $tool) {
            $body[] = sprintf(
                '<th>%s<br><small>%s</small></th>',
                htmlspecialchars($tool),
                $this->versionCell($resultsRoot, $tool),
            );
        }

        $body[] = '</tr></thead><tbody>';

        foreach ($testGroups as $groupKey => $group) {
            $groupCases = array_values(array_filter(
                $testCases,
                static fn (TestCase $testCase): bool => $testCase->groupKey === $groupKey,
            ));

            if ($groupCases === []) {
                continue;
            }

            $body[] = sprintf(
                '<tr class="group"><td colspan="%d">%s</td></tr>',
                count($tools) + 1,
                htmlspecialchars($group->name),
            );

            foreach ($groupCases as $testCase) {
                [$title] = $this->docblock($testCase);
                $href = self::DETAILS_DIR . '/' . rawurlencode($testCase->name) . '.html';

                $body[] = sprintf(
                    '<tr><td class="test-cell"><a class="test-link" href="%s">%s</a><br><small class="slug">%s</small></td>',
                    htmlspecialchars($href),
                    $this->renderInline($title),
                    htmlspecialchars($testCase->name),
                );

                foreach ($tools as $tool) {
                    $result = $this->loadResult($resultsRoot, $tool, $testCase->name);
                    [$display, $class] = $this->statusOf($result);

                    $cell = htmlspecialchars($display);
                    $cell .= $this->levelSuffix($result);

                    $notes = trim((string) ($result['notes'] ?? ''));
                    if ($notes !== '') {
                        $cell .= sprintf(
                            ' <span class="hover-card"><span class="hover-card__trigger hover-card__notes-label">Notes</span><span class="hover-card__popup">%s</span></span>',
                            $this->renderLinkedText($notes),
                        );
                    }

                    $body[] = sprintf('<td class="%s"><a class="cell-link" href="%s">%s</a></td>', $class, htmlspecialchars($href), $cell);
                }

                $body[] = '</tr>';
            }
        }

        $body[] = '</tbody></table>';

        return $this->renderPage('PHP Typing Conformance Results', $body, false);
    }

    /**
     * @param list<string> $tools
     */
    private function renderDetailPage(
        string $resultsRoot,
        TestCase $testCase,
        ?TestGroup $group,
        array $tools,
    ): string {
        [$title, $description] = $this->docblock($testCase);

        $body = [];
        $body[] = sprintf('<p class="crumb"><a href="../%s">&larr; All results</a></p>', self::INDEX_FILE);
        $body[] = sprintf('<h1>%s</h1>', $this->renderInline($title));

        $meta = [];
        if ($group !== null) {
            $meta[] = 'Group: ' . htmlspecialchars($group->name);
            $meta[] = 'Category: ' . htmlspecialchars($group->sourceCategory);
        }
        $meta[] = 'File: ' . htmlspecialchars($testCase->fileName);
        $body[] = '<p class="meta">' . implode(' &middot; ', $meta) . '</p>';

        if ($description !== '') {
            $body[] = '<div class="doc">' . $this->renderMultiline($description) . '</div>';
        }

        $body[] = '<h2>Analyzer results</h2>';
        $body[] = '<table class="detail-results">';
        $body[] = '<thead><tr><th>Analyzer</th><th>Version</th><th>Result</th><th>Diagnostics</th></tr></thead><tbody>';

        foreach ($tools as $tool) {
            $result = $this->loadResult($resultsRoot, $tool, $testCase->name);
            [$display, $class] = $this->statusOf($result);
            $status = htmlspecialchars($display) . $this->levelSuffix($result);

            $output = trim((string) ($result['output'] ?? ''));
            $errorsDiff = trim((string) ($result['errors_diff'] ?? ''));
            $notes = trim((string) ($result['notes'] ?? ''));

            $diagnostics = '';
            if ($output !== '') {
                $diagnostics .= '<pre class="diag">' . htmlspecialchars($output) . '</pre>';
            } else {
                $diagnostics .= '<p class="none">No diagnostics reported.</p>';
            }
            if ($errorsDiff !== '') {
                $diagnostics .= '<details><summary>Expectation diff</summary><pre class="diag">' . htmlspecialchars($errorsDiff) . '</pre></details>';
            }
            if ($notes !== '') {
                $diagnostics .= '<p class="note-line"><strong>Notes:</strong> ' . $this->renderLinkedText($notes) . '</p>';
            }

            $body[] = sprintf(
                '<tr><th class="tool-name">%s</th><td><small>%s</small></td><td class="%s status-cell">%s</td><td class="diag-cell">%s</td></tr>',
                htmlspecialchars($tool),
                $this->versionCell($resultsRoot, $tool),
                $class,
                $status,
                $diagnostics,
            );
        }

        $body[] = '</tbody></table>';

        $body[] = '<h2>Source</h2>';
        $body[] = '<pre class="source"><code>' . htmlspecialchars($this->readSource($testCase->path)) . '</code></pre>';

        foreach ($testCase->supportPaths as $supportPath) {
            $body[] = sprintf('<h2>Support: <code>%s</code></h2>', htmlspecialchars(basename($supportPath)));
            $body[] = '<pre class="source"><code>' . htmlspecialchars($this->readSource($supportPath)) . '</code></pre>';
        }

        return $this->renderPage($title . ' — Conformance', $body, true);
    }

    /**
     * Wrap body lines in the shared page skeleton.
     *
     * @param list<string> $body
     */
    private function renderPage(string $title, array $body, bool $isDetail): string
    {
        $html = [];
        $html[] = '<!DOCTYPE html>';
        $html[] = '<html lang="en">';
        $html[] = '<head>';
        $html[] = '  <meta charset="UTF-8">';
        $html[] = '  <meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html[] = sprintf('  <title>%s</title>', htmlspecialchars($title));
        $html[] = '  <style>';
        $html[] = $this->styles();
        $html[] = '  </style>';
        $html[] = '</head>';
        $html[] = '<body class="' . ($isDetail ? 'detail' : 'index') . '">';
        foreach ($body as $line) {
            $html[] = $line;
        }
        if ($isDetail) {
            $html[] = $this->highlightScript();
        }
        $html[] = '</body></html>';

        return implode("\n", $html);
    }

    /**
     * Client-side syntax highlighting for the source blocks via Shiki (CDN).
     *
     * @see https://shiki.style/guide/install#cdn-usage
     */
    private function highlightScript(): string
    {
        return <<<'HTML'
<script type="module">
  import { codeToHtml } from 'https://esm.sh/shiki@4.3.1';

  for (const code of document.querySelectorAll('pre.source > code')) {
    const pre = code.closest('pre.source');
    try {
      pre.outerHTML = await codeToHtml(code.textContent, { lang: 'php', theme: 'github-dark' });
    } catch (error) {
      console.error('shiki highlight failed', error);
    }
  }
</script>
HTML;
    }

    private function styles(): string
    {
        return <<<'CSS'
body { font-family: system-ui, sans-serif; margin: 24px; color: #1b1b1b; background: #ffffff; }
body.detail { max-width: 960px; }
a { color: #0b62c4; }
h1 { margin-bottom: 0.2em; }
p.lead { color: #555; margin-top: 0; }
p.crumb { margin: 0 0 12px; }
p.meta { color: #555; font-size: 0.9em; margin-top: 0; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 8px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: top; }
small a { color: inherit; }
.test-cell { min-width: 260px; }
.test-link { font-weight: 600; text-decoration: none; }
.test-link:hover { text-decoration: underline; }
.slug { color: #888; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.78em; }
.cell-link { display: block; color: inherit; text-decoration: none; }
.hover-card { position: relative; display: inline-flex; align-items: center; gap: 0.35rem; }
.hover-card__trigger { cursor: help; }
.hover-card__popup { position: absolute; left: 0; top: calc(100% + 6px); z-index: 10; min-width: 220px; max-width: 360px; padding: 8px 10px; border-radius: 8px; background: rgba(20, 20, 20, 0.96); color: #fff; box-shadow: 0 10px 28px rgba(0, 0, 0, 0.22); font-size: 0.875em; line-height: 1.4; visibility: hidden; opacity: 0; transform: translateY(-2px); transition: opacity 120ms ease, transform 120ms ease, visibility 120ms ease; }
.hover-card:hover .hover-card__popup, .hover-card:focus-within .hover-card__popup { visibility: visible; opacity: 1; transform: translateY(0); }
.hover-card__popup a { color: #9fd0ff; }
.hover-card__notes-label { border-bottom: 1px dotted currentColor; font-size: 0.875em; }
.group { background: #f3f3f3; font-weight: 700; }
.pass { background: #dff7df; }
.fail { background: #f9d6d6; }
.by-design { background: #f6e7c8; }
.unknown { background: #f0f0f0; }
.doc { white-space: pre-wrap; background: #fafafa; border: 1px solid #eee; border-left: 3px solid #0b62c4; border-radius: 4px; padding: 10px 14px; margin: 12px 0 20px; line-height: 1.5; }
.doc code, .status-cell code, h1 code { background: #eef1f4; padding: 0.1em 0.35em; border-radius: 3px; font-size: 0.9em; }
.detail-results th.tool-name { width: 110px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.status-cell { white-space: nowrap; font-weight: 600; }
.diag-cell { width: 100%; }
pre.diag { white-space: pre-wrap; word-break: break-word; margin: 0; font-size: 0.85em; }
pre.source, pre.shiki { padding: 14px 16px; border-radius: 6px; overflow-x: auto; font-size: 0.85em; line-height: 1.45; }
pre.source { background: #1e1e1e; color: #eaeaea; }
.none { color: #888; margin: 0; font-style: italic; }
.note-line { font-size: 0.85em; margin: 6px 0 0; }
details summary { cursor: pointer; font-size: 0.85em; color: #555; }
CSS;
    }

    /**
     * Extract a human-readable title and description from the leading docblock.
     *
     * @return array{0: string, 1: string} [title, description]
     */
    private function docblock(TestCase $testCase): array
    {
        $source = $this->readSource($testCase->path);

        if (!preg_match('#/\*\*(.*?)\*/#s', $source, $matches)) {
            return [$this->humanize($testCase->name), ''];
        }

        $lines = [];
        foreach (explode("\n", $matches[1]) as $rawLine) {
            $lines[] = preg_replace('/^\s*\*\s?/', '', $rawLine) ?? $rawLine;
        }

        // First blank-separated paragraph becomes the title; the rest is the description.
        $title = [];
        $rest = [];
        $inTitle = true;
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($inTitle) {
                if ($trimmed === '') {
                    if ($title !== []) {
                        $inTitle = false;
                    }
                    continue;
                }
                $title[] = $trimmed;
                continue;
            }
            $rest[] = $line;
        }

        $titleText = trim(implode(' ', $title));
        if ($titleText === '') {
            $titleText = $this->humanize($testCase->name);
        }

        // Headings read better without the sentence-ending period (but keep an
        // ellipsis intact).
        if (str_ends_with($titleText, '.') && !str_ends_with($titleText, '..')) {
            $titleText = substr($titleText, 0, -1);
        }

        return [$titleText, rtrim(implode("\n", $rest))];
    }

    private function humanize(string $name): string
    {
        return ucfirst(str_replace('_', ' ', $name));
    }

    private function readSource(string $path): string
    {
        $contents = @file_get_contents($path);

        return $contents === false ? '' : $contents;
    }

    /**
     * @param array<string, mixed> $result
     * @return array{0: string, 1: string} [display, cssClass]
     */
    private function statusOf(array $result): array
    {
        $automated = (string) ($result['conformance_automated'] ?? 'Unknown');
        $status = (string) ($result['status'] ?? 'Unknown');
        $display = $status !== 'Unknown' ? $status : $automated;

        $class = match ($display) {
            'Pass' => 'pass',
            'Fail' => 'fail',
            'By design' => 'by-design',
            default => 'unknown',
        };

        return [$display, $class];
    }

    /**
     * @param array<string, mixed> $result
     */
    private function levelSuffix(array $result): string
    {
        $firstDetectedLevel = $result['first_detected_level'] ?? null;
        if (!is_int($firstDetectedLevel)) {
            return '';
        }

        $levelLabel = $firstDetectedLevel === 10
            ? '(Lv max)'
            : sprintf('(Lv %d+)', $firstDetectedLevel);

        return ' <small>' . htmlspecialchars($levelLabel) . '</small>';
    }

    private function versionCell(string $resultsRoot, string $tool): string
    {
        $fullVersion = $this->loadVersion($resultsRoot, $tool);
        $shortVersion = $this->shortVersion($tool, $fullVersion);
        $releaseUrl = $this->releaseUrl($tool, $shortVersion);
        $versionHtml = htmlspecialchars($shortVersion);
        $popupHtml = htmlspecialchars(str_replace("\n", ' ', $fullVersion));

        if ($releaseUrl !== null) {
            $versionHtml = sprintf(
                '<a href="%s" class="hover-card__trigger" target="_blank" rel="noopener">%s</a>',
                htmlspecialchars($releaseUrl),
                $versionHtml,
            );
        } else {
            $versionHtml = sprintf('<span class="hover-card__trigger">%s</span>', $versionHtml);
        }

        return sprintf(
            '<span class="hover-card">%s<span class="hover-card__popup">%s</span></span>',
            $versionHtml,
            $popupHtml,
        );
    }

    private function prepareDetailsDir(string $detailsDir): void
    {
        if (!is_dir($detailsDir) && !mkdir($detailsDir, 0o755, true) && !is_dir($detailsDir)) {
            throw new RuntimeException(sprintf('Failed to create details directory: %s', $detailsDir));
        }

        // Drop stale detail pages so removed tests do not linger.
        foreach (glob($detailsDir . DIRECTORY_SEPARATOR . '*.html') ?: [] as $stale) {
            @unlink($stale);
        }
    }

    private function loadVersion(string $resultsRoot, string $tool): string
    {
        $path = $resultsRoot . DIRECTORY_SEPARATOR . $tool . DIRECTORY_SEPARATOR . 'version.toml';
        if (!is_file($path)) {
            return 'unknown';
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return 'unknown';
        }

        $data = Toml::parseToArray($contents);

        return (string) ($data['version'] ?? 'unknown');
    }

    private function shortVersion(string $tool, string $fullVersion): string
    {
        $version = trim($fullVersion);

        return match ($tool) {
            'phpstan' => $this->extractVersion($version, '/(\d+\.\d+\.\d+)$/'),
            'phpstan-strict' => $this->extractVersion($version, '/(\d+\.\d+\.\d+)$/'),
            'psalm' => $this->extractVersion($version, '/Psalm\s+(\d+\.\d+\.\d+)/'),
            'mago' => $this->extractVersion($version, '/mago\s+(\d+\.\d+\.\d+)/i'),
            'mir' => $this->extractVersion($version, '/mir\s+(\d+\.\d+\.\d+)/i'),
            'phan' => $this->extractVersion($version, '/Phan\s+(\d+\.\d+\.\d+)/'),
            'noverify' => $this->extractVersion($version, '/version\s+(\d+\.\d+\.\d+)/i'),
            default => $version,
        };
    }

    private function releaseUrl(string $tool, string $shortVersion): ?string
    {
        if ($shortVersion === 'unknown') {
            return null;
        }

        return match ($tool) {
            'phpstan' => sprintf('https://github.com/phpstan/phpstan/releases/tag/%s', $shortVersion),
            'phpstan-strict' => sprintf('https://github.com/phpstan/phpstan/releases/tag/%s', $shortVersion),
            'psalm' => sprintf('https://github.com/vimeo/psalm/releases/tag/%s', $shortVersion),
            'mago' => sprintf('https://github.com/carthage-software/mago/releases/tag/%s', $shortVersion),
            'mir' => sprintf('https://github.com/jorgsowa/mir/releases/tag/v%s', $shortVersion),
            'phan' => sprintf('https://github.com/phan/phan/releases/tag/%s', $shortVersion),
            'noverify' => sprintf('https://github.com/VKCOM/noverify/releases/tag/v%s', $shortVersion),
            default => null,
        };
    }

    private function extractVersion(string $version, string $pattern): string
    {
        if (preg_match($pattern, $version, $matches) === 1) {
            return $matches[1];
        }

        return $version;
    }

    /**
     * Escape text, then turn `code` spans into <code> and linkify bare URLs.
     */
    private function renderInline(string $text): string
    {
        $escaped = htmlspecialchars($text);

        $escaped = preg_replace_callback(
            '/`([^`]+)`/',
            static fn (array $matches): string => '<code>' . $matches[1] . '</code>',
            $escaped,
        ) ?? $escaped;

        return $this->linkify($escaped);
    }

    private function renderMultiline(string $text): string
    {
        return $this->renderInline($text);
    }

    private function renderLinkedText(string $text): string
    {
        return $this->linkify(htmlspecialchars($text));
    }

    private function linkify(string $escaped): string
    {
        $linked = preg_replace_callback(
            '/https?:\/\/[^\s<]+/i',
            static function (array $matches): string {
                $url = $matches[0];

                // Keep trailing sentence punctuation out of the link target.
                $trailing = '';
                while ($url !== '' && str_contains('.,;:)]}', substr($url, -1))) {
                    $trailing = substr($url, -1) . $trailing;
                    $url = substr($url, 0, -1);
                }

                // Shorten GitHub issue/PR links to `<repo>#<number>`.
                $label = $url;
                if (preg_match('~^https?://github\.com/[^/\s]+/([^/\s]+)/(?:issues|pull)/(\d+)(?:[/#?][^\s<]*)?$~i', $url, $ref) === 1) {
                    $label = htmlspecialchars($ref[1] . '#' . $ref[2]);
                }

                return sprintf(
                    '<a href="%s" target="_blank" rel="noopener">%s</a>%s',
                    $url,
                    $label,
                    $trailing,
                );
            },
            $escaped,
        );

        return $linked ?? $escaped;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadResult(string $resultsRoot, string $tool, string $testName): array
    {
        $path = $resultsRoot . DIRECTORY_SEPARATOR . $tool . DIRECTORY_SEPARATOR . $testName . '.toml';
        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        return Toml::parseToArray($contents);
    }
}
