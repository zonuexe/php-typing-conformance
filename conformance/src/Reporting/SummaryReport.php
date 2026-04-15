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
        $html = [];
        $html[] = '<!DOCTYPE html>';
        $html[] = '<html lang="en">';
        $html[] = '<head>';
        $html[] = '  <meta charset="UTF-8">';
        $html[] = '  <meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html[] = '  <title>PHP Typing Conformance Results</title>';
        $html[] = '  <style>';
        $html[] = 'body { font-family: system-ui, sans-serif; margin: 24px; }';
        $html[] = 'table { width: 100%; border-collapse: collapse; }';
        $html[] = 'th, td { padding: 8px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: top; }';
        $html[] = 'small a { color: inherit; }';
        $html[] = '.hover-card { position: relative; display: inline-flex; align-items: center; gap: 0.35rem; }';
        $html[] = '.hover-card__trigger { cursor: help; }';
        $html[] = '.hover-card__popup { position: absolute; left: 0; top: calc(100% + 6px); z-index: 10; min-width: 220px; max-width: 360px; padding: 8px 10px; border-radius: 8px; background: rgba(20, 20, 20, 0.96); color: #fff; box-shadow: 0 10px 28px rgba(0, 0, 0, 0.22); font-size: 0.875em; line-height: 1.4; visibility: hidden; opacity: 0; transform: translateY(-2px); transition: opacity 120ms ease, transform 120ms ease, visibility 120ms ease; }';
        $html[] = '.hover-card:hover .hover-card__popup, .hover-card:focus-within .hover-card__popup { visibility: visible; opacity: 1; transform: translateY(0); }';
        $html[] = '.hover-card__popup a { color: #9fd0ff; }';
        $html[] = '.hover-card__notes-label { border-bottom: 1px dotted currentColor; font-size: 0.875em; }';
        $html[] = '.group { background: #f3f3f3; font-weight: 700; }';
        $html[] = '.pass { background: #dff7df; }';
        $html[] = '.fail { background: #f9d6d6; }';
        $html[] = '.by-design { background: #f6e7c8; }';
        $html[] = '.unknown { background: #f0f0f0; }';
        $html[] = '</style>';
        $html[] = '</head>';
        $html[] = '<body>';
        $html[] = '<h1>PHP Typing Conformance Results</h1>';
        $html[] = '<table>';
        $html[] = '<thead><tr><th>Test</th>';

        foreach ($tools as $tool) {
            $fullVersion = $this->loadVersion($resultsRoot, $tool);
            $shortVersion = $this->shortVersion($tool, $fullVersion);
            $releaseUrl = $this->releaseUrl($tool, $shortVersion);
            $versionHtml = htmlspecialchars($shortVersion);
            $popupHtml = htmlspecialchars(str_replace("\n", ' ', $fullVersion));

            if ($releaseUrl !== null) {
                $versionHtml = sprintf(
                    '<a href="%s" class="hover-card__trigger" target="_blank">%s</a>',
                    htmlspecialchars($releaseUrl),
                    $versionHtml,
                );
            } else {
                $versionHtml = sprintf(
                    '<span class="hover-card__trigger">%s</span>',
                    $versionHtml,
                );
            }

            $versionHtml = sprintf(
                '<span class="hover-card">%s<span class="hover-card__popup">%s</span></span>',
                $versionHtml,
                $popupHtml,
            );

            $html[] = sprintf(
                '<th>%s<br><small>%s</small></th>',
                htmlspecialchars($tool),
                $versionHtml,
            );
        }

        $html[] = '</tr></thead><tbody>';

        foreach ($testGroups as $groupKey => $group) {
            $groupCases = array_values(array_filter(
                $testCases,
                static fn (TestCase $testCase): bool => $testCase->groupKey === $groupKey,
            ));

            if ($groupCases === []) {
                continue;
            }

            $html[] = sprintf(
                '<tr class="group"><td colspan="%d">%s</td></tr>',
                count($tools) + 1,
                htmlspecialchars($group->name),
            );

            foreach ($groupCases as $testCase) {
                $html[] = sprintf('<tr><td>%s</td>', htmlspecialchars($testCase->name));

                foreach ($tools as $tool) {
                    $result = $this->loadResult($resultsRoot, $tool, $testCase->name);
                    $automated = (string) ($result['conformance_automated'] ?? 'Unknown');
                    $status = (string) ($result['status'] ?? 'Unknown');
                    $display = $status !== 'Unknown' ? $status : $automated;
                    $firstDetectedLevel = $result['first_detected_level'] ?? null;
                    $class = match ($display) {
                        'Pass' => 'pass',
                        'Fail' => 'fail',
                        'By design' => 'by-design',
                        default => 'unknown',
                    };

                    $cell = htmlspecialchars($display);
                    if (is_int($firstDetectedLevel)) {
                        $levelLabel = $firstDetectedLevel === 10
                            ? '(Lv max)'
                            : sprintf('(Lv %d+)', $firstDetectedLevel);
                        $cell .= ' <small>' . htmlspecialchars($levelLabel) . '</small>';
                    }

                    $notes = trim((string) ($result['notes'] ?? ''));
                    if ($notes !== '') {
                        $cell .= sprintf(
                            ' <span class="hover-card"><span class="hover-card__trigger hover-card__notes-label">Notes</span><span class="hover-card__popup">%s</span></span>',
                            $this->renderLinkedText($notes),
                        );
                    }

                    $html[] = sprintf(
                        '<td class="%s">%s</td>',
                        $class,
                        $cell,
                    );
                }

                $html[] = '</tr>';
            }
        }

        $html[] = '</tbody></table>';
        $html[] = '</body></html>';

        if (file_put_contents($outputPath, implode("\n", $html)) === false) {
            throw new RuntimeException(sprintf('Failed to write summary report: %s', $outputPath));
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

    private function renderLinkedText(string $text): string
    {
        $escaped = htmlspecialchars($text);

        $linked = preg_replace_callback(
            '/https?:\/\/[^\s<]+/i',
            static fn (array $matches): string => sprintf(
                '<a href="%1$s" target="_blank">%1$s</a>',
                htmlspecialchars($matches[0]),
            ),
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
