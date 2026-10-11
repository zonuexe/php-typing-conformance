<?php

declare(strict_types=1);

namespace Conformance\Checker;

use JsonException;
use RuntimeException;

/**
 * Reads the directory PhpStorm's headless command-line inspector leaves behind
 * and turns it into the shape {@see QodanaSarifReport} produces.
 *
 * `phpstorm inspect <project> <profile> <output> -format json`, documented at
 * https://www.jetbrains.com/help/phpstorm/command-line-code-inspector.html,
 * runs the same inspections "Run Qodana in the IDE" runs; only the envelope
 * differs. Instead of one SARIF file it writes one `<InspectionId>.json` per
 * inspection, each a `problems` list, plus `.descriptions.json` naming the
 * profile and every inspection's plugin, build and enabled state. Measured
 * against the same build and corpus, its messages match the SARIF ones byte
 * for byte, so either report reproduces the same results.
 *
 * The inspector is PhpStorm itself rather than its Qodana identity, which is
 * why it runs on an ordinary PhpStorm licence: the `qodana` app starter in the
 * same installation relaunches the IDE as "Qodana for PHP" and asks for a
 * Qodana licence instead.
 *
 * Nothing in the output records when it was produced, so the caller supplies
 * the moment the run began; that is what coverage is judged against.
 */
final class PhpStormInspectionReport
{
    /**
     * @param array<string, array<int, list<string>>> $diagnostics keyed by repository-relative path
     */
    private function __construct(
        public readonly string $toolName,
        public readonly string $toolVersion,
        public readonly ?string $startedAt,
        public readonly bool $localised,
        public readonly array $diagnostics,
    ) {
    }

    /**
     * @param string|null $startedAt when the run began, as an ISO 8601 UTC timestamp
     * @param list<string>|null $ruleFilter null keeps every inspection
     */
    public static function fromDirectory(
        string $directory,
        ?string $startedAt,
        string $pathPrefix = 'conformance/tests/',
        ?array $ruleFilter = QodanaSarifReport::TYPE_RULES,
    ): self {
        $directory = rtrim($directory, '/');
        $descriptions = self::readJson($directory . '/.descriptions.json');

        $inspections = [];
        $localised = false;
        foreach (self::asList($descriptions['groups'] ?? null) as $group) {
            // Group names are translated with the UI and are pure ASCII in
            // English, the same signal QodanaSarifReport reads off the taxa.
            $name = $group['name'] ?? null;
            if (is_string($name) && preg_match('/[^\x20-\x7e]/', $name) === 1) {
                $localised = true;
            }

            foreach (self::asList($group['inspections'] ?? null) as $inspection) {
                $id = $inspection['shortName'] ?? null;
                if (is_string($id)) {
                    $inspections[$id] = $inspection;
                }
            }
        }

        // The profile is passed on the command line, so a measured inspection
        // that it leaves disabled is not a finding to report but a broken
        // setup: the inspection never ran, and its silence would read as a
        // clean result in every row it governs.
        foreach ($ruleFilter ?? [] as $ruleId) {
            if (($inspections[$ruleId]['enabled'] ?? false) !== true) {
                throw new RuntimeException(sprintf(
                    'Inspection %s is not enabled in the profile the inspector ran (%s), so its results cannot be read as a measurement.',
                    $ruleId,
                    (string) ($descriptions['profile'] ?? 'unknown profile'),
                ));
            }
        }

        $rows = [];
        // glob() skips the dot-file, which is the one that is not a result.
        foreach (glob($directory . '/*.json') ?: [] as $file) {
            foreach (self::asList(self::readJson($file)['problems'] ?? null) as $problem) {
                $ruleId = $problem['problem_class']['id'] ?? null;
                if (!is_string($ruleId) || $ruleId === '') {
                    continue;
                }
                if ($ruleFilter !== null && !in_array($ruleId, $ruleFilter, true)) {
                    continue;
                }

                $uri = preg_replace('{^file://\$PROJECT_DIR\$/}', '', (string) ($problem['file'] ?? ''));
                if ($uri === null || !str_starts_with($uri, $pathPrefix)) {
                    continue;
                }

                $line = $problem['line'] ?? null;
                if (!is_int($line) || $line <= 0) {
                    continue;
                }

                $message = trim((string) ($problem['description'] ?? ''));
                if ($message === '') {
                    continue;
                }

                $rows[] = [
                    'uri' => $uri,
                    'line' => $line,
                    'offset' => (int) ($problem['offset'] ?? 0),
                    'rule' => $ruleId,
                    'text' => sprintf('%s [%s]', trim((string) preg_replace('/\s+/u', ' ', $message)), $ruleId),
                ];
            }
        }

        // Same ordering as QodanaSarifReport, where `offset` is the column:
        // results stay byte-identical whichever report they were read from.
        usort($rows, static fn (array $a, array $b): int
            => [$a['uri'], $a['line'], $a['offset'], $a['rule'], $a['text']]
            <=> [$b['uri'], $b['line'], $b['offset'], $b['rule'], $b['text']]);

        $diagnostics = [];
        foreach ($rows as $row) {
            $diagnostics[$row['uri']][$row['line']][] = $row['text'];
        }
        ksort($diagnostics);

        $build = $inspections['PhpParamsInspection']['pluginVersion'] ?? null;

        return new self(
            toolName: 'PhpStorm',
            toolVersion: is_string($build) ? $build : '',
            startedAt: $startedAt,
            localised: $localised,
            diagnostics: $diagnostics,
        );
    }

    /**
     * Diagnostics for one repository-relative path, in {@see Checker::analyse} shape.
     *
     * @return array<int, list<string>>
     */
    public function forPath(string $uri): array
    {
        return $this->diagnostics[$uri] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function readJson(string $path): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException(sprintf('Unable to read PhpStorm inspection output at %s', $path));
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('PhpStorm inspection output at %s is not valid JSON: %s', $path, $e->getMessage()), 0, $e);
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function asList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }
}
