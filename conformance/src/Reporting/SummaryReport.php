<?php

declare(strict_types=1);

namespace Conformance\Reporting;

use Conformance\Discovery\TestCase;
use Conformance\TestGroup\TestGroup;
use Internal\Toml\Toml;
use RuntimeException;

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
        $html[] = '.group { background: #f3f3f3; font-weight: 700; }';
        $html[] = '.pass { background: #dff7df; }';
        $html[] = '.fail { background: #f9d6d6; }';
        $html[] = '.unknown { background: #f0f0f0; }';
        $html[] = '</style>';
        $html[] = '</head>';
        $html[] = '<body>';
        $html[] = '<h1>PHP Typing Conformance Results</h1>';
        $html[] = '<table>';
        $html[] = '<thead><tr><th>Test</th>';

        foreach ($tools as $tool) {
            $html[] = sprintf(
                '<th>%s<br><small>%s</small></th>',
                htmlspecialchars($tool, ENT_QUOTES),
                htmlspecialchars($this->loadVersion($resultsRoot, $tool), ENT_QUOTES),
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
                htmlspecialchars($group->name, ENT_QUOTES),
            );

            foreach ($groupCases as $testCase) {
                $html[] = sprintf('<tr><td>%s</td>', htmlspecialchars($testCase->name, ENT_QUOTES));

                foreach ($tools as $tool) {
                    $result = $this->loadResult($resultsRoot, $tool, $testCase->name);
                    $automated = (string) ($result['conformance_automated'] ?? 'Unknown');
                    $status = (string) ($result['status'] ?? 'Unknown');
                    $display = $status !== 'Unknown' ? $status : $automated;
                    $class = match ($display) {
                        'Pass' => 'pass',
                        'Fail' => 'fail',
                        default => 'unknown',
                    };

                    $html[] = sprintf(
                        '<td class="%s">%s</td>',
                        $class,
                        htmlspecialchars($display, ENT_QUOTES),
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
