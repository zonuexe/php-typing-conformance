<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

/**
 * Adapter for Qodana (https://www.jetbrains.com/qodana/), the analysis engine
 * behind PhpStorm's inspections.
 *
 * Qodana is proprietary and cannot be shipped here, so the column is measured
 * through a PhpStorm installation someone holds a licence for, in one of two
 * ways that run the same inspections:
 *
 * - By hand, with the IDE's own "Run Qodana in the IDE" action, described at
 *   https://www.jetbrains.com/help/qodana/quick-start.html#quickstart-run-in-ide.
 *   This class then reads the `qodana.sarif.json` it leaves behind.
 * - Headless, when constructed with a PhpStorm launcher: the checker runs
 *   PhpStorm's command-line inspector itself and reads its JSON output — see
 *   {@see PhpStormInspectionReport}. The inspector refuses to start while
 *   another PhpStorm instance is running, so the IDE has to be closed.
 *
 * It is worth being explicit that neither is
 * https://github.com/JetBrains/qodana-cli. That is a separate artifact with
 * its own versioning and its own Qodana Cloud licensing, and the version it
 * pins in qodana.yaml's `linter:` field describes a CI container that never
 * runs here.
 *
 * Everything else follows from having no artifact to install: there is
 * nothing to pin, so the configuration is pinned instead, in `qodana.yaml`
 * and `.idea/inspectionProfiles/Project_Default.xml` — see
 * {@see QodanaSarifReport::TYPE_RULES} for which inspections are measured and
 * why those.
 *
 * A report is a snapshot of one working tree at one moment, which is the one
 * thing every other checker gets for free and this one has to assert. A test
 * file touched after the report was produced was never looked at, and neither
 * output carries a list of what was analysed, so silence about it cannot be
 * read as a clean result. {@see covers()} draws that line; see
 * {@see CoverageAware} for why it has to exist at all.
 *
 * Support files are not passed separately the way the CLI checkers pass them:
 * PhpStorm indexes the entire project, so a `_`-prefixed helper is always in
 * scope whether or not this suite mentions it.
 */
final class QodanaChecker implements CoverageAware
{
    private QodanaSarifReport|PhpStormInspectionReport|null $report = null;

    private bool $stalenessReported = false;

    /**
     * @param string|null $reportPath      null resolves the newest report at the
     *                                     moment of the first analysis
     * @param string|null $phpStormLauncher PhpStorm's launcher (`phpstorm`, or
     *                                     `phpstorm.sh` in the install's bin/);
     *                                     when given, the checker runs the
     *                                     inspection itself instead of reading
     *                                     a report produced by hand
     */
    public function __construct(
        private readonly string $projectRoot,
        private readonly ?string $reportPath = null,
        private readonly ?string $searchDirectory = null,
        private readonly ?string $phpStormLauncher = null,
    ) {
    }

    public function name(): string
    {
        return 'qodana';
    }

    public function version(): string
    {
        $report = $this->report();

        // The IDE build number, which is what either report states —
        // 262.8665.325 for PhpStorm 2026.2. There is no separate Qodana version
        // to read. The name says which of the two took the measurement.
        return trim(sprintf('%s %s', $report->toolName, $report->toolVersion));
    }

    /**
     * @return array<int, list<string>>
     */
    public function analyse(TestCase $testCase): array
    {
        return $this->report()->forPath($this->toProjectRelativePath($testCase->path));
    }

    public function covers(TestCase $testCase): bool
    {
        return $this->staleFile($testCase) === null;
    }

    public function coverageGap(TestCase $testCase): string
    {
        $stale = $this->staleFile($testCase);
        if ($stale === null) {
            return '';
        }

        return sprintf(
            '%s changed after the Qodana report of %s was produced, so the report cannot answer for it. Run Qodana in PhpStorm, or set PHPSTORM to run the inspection headless, and re-run this tool.',
            basename($stale),
            $this->report()->startedAt ?? 'unknown date',
        );
    }

    /**
     * The first file this test depends on that is newer than the report, or
     * null when the report still speaks for all of them.
     *
     * Modification time rather than git history, because the report is a
     * snapshot of the working tree and not of a commit: a test file added but
     * not yet committed is absent from every revision, and yet PhpStorm
     * inspected it. Keying on the revision would call such a test unmeasured
     * forever, including immediately after someone re-ran the inspection.
     *
     * Support files count too. A test whose stub changed is being answered on
     * the strength of the stub the report saw, which is no longer the stub.
     *
     * The failure mode is one-directional by construction. Anything that
     * rewrites mtimes without changing content — a fresh clone, a branch
     * switch, a rebase — asks for an inspection that was not strictly needed.
     * It never claims a measurement that was not taken.
     */
    private function staleFile(TestCase $testCase): ?string
    {
        $producedAt = $this->reportTimestamp();
        if ($producedAt === null) {
            return null;
        }

        foreach ([$testCase->path, ...$testCase->supportPaths] as $path) {
            $modified = @filemtime($path);
            if ($modified !== false && $modified > $producedAt) {
                return $path;
            }
        }

        return null;
    }

    private function reportTimestamp(): ?int
    {
        $startedAt = $this->report()->startedAt;
        if ($startedAt === null) {
            return null;
        }

        $timestamp = strtotime($startedAt);

        return $timestamp !== false ? $timestamp : null;
    }

    private function report(): QodanaSarifReport|PhpStormInspectionReport
    {
        if ($this->report !== null) {
            return $this->report;
        }

        if ($this->phpStormLauncher !== null) {
            ['directory' => $path, 'startedAt' => $startedAt] = $this->inspect($this->phpStormLauncher);
            $report = PhpStormInspectionReport::fromDirectory($path, $startedAt);
        } else {
            $path = $this->reportPath ?? QodanaSarifReport::locateLatest($this->searchDirectory);
            $report = QodanaSarifReport::fromFile($path);
        }

        $this->warnIfLocalised($report, $path);

        return $this->report = $report;
    }

    /**
     * Run PhpStorm's command-line inspector over the corpus, once.
     *
     * The profile is the pinned project profile, passed explicitly, so the
     * set of inspections does not depend on whatever the IDE was last told.
     * The scope is the test directory alone; the whole project is still
     * indexed, which is what keeps the `_`-prefixed support files in scope.
     *
     * Before inspecting, PhpStorm's PHP project configurator runs
     * `composer install` for any composer.json whose vendor directory is
     * missing. At this repository's root that install is forwarded by the
     * bin plugin into every vendor-bin namespace, which drops a `{}`
     * composer.json into the npm-installed ones. Requiring the root vendor
     * directory up front keeps the inspector from touching the tree.
     *
     * @return array{directory: string, startedAt: string}
     */
    private function inspect(string $launcher): array
    {
        $root = rtrim($this->projectRoot, '/');
        if (!is_dir($root . '/vendor')) {
            throw new RuntimeException(sprintf(
                'Run composer install in %s first: without a vendor directory, PhpStorm runs it itself before inspecting.',
                $root,
            ));
        }

        $directory = sprintf('%s/phpstorm_inspect_%s', rtrim(sys_get_temp_dir(), '/'), bin2hex(random_bytes(4)));
        if (!mkdir($directory, 0777, true)) {
            throw new RuntimeException(sprintf('Unable to create %s', $directory));
        }

        $command = sprintf(
            '%s inspect %s %s %s -format json -v0 -d %s 2>&1',
            escapeshellarg($launcher),
            escapeshellarg($root),
            escapeshellarg($root . '/.idea/inspectionProfiles/Project_Default.xml'),
            escapeshellarg($directory),
            escapeshellarg($root . '/conformance/tests'),
        );

        fwrite(STDERR, sprintf("qodana: running PhpStorm's command-line inspector into %s\n", $directory));

        // Recorded before the launch: a test file saved while the inspector
        // was reading must not be counted as covered.
        $startedAt = gmdate('Y-m-d\TH:i:s\Z');
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($directory . '/.descriptions.json')) {
            throw new RuntimeException(sprintf(
                "PhpStorm's inspector failed (exit %d). It does not start while another PhpStorm instance is running. Last output:\n%s",
                $exitCode,
                implode("\n", array_slice($output, -10)),
            ));
        }

        return ['directory' => $directory, 'startedAt' => $startedAt];
    }

    /**
     * Once, and without stopping the run.
     *
     * Staleness used to be warned about here too, by comparing the report's
     * revision against HEAD. That fires after any commit at all, including the
     * commits that record these very results, so it cried wolf constantly
     * while saying nothing about which tests were affected. covers() answers
     * the same question per test case and lands the answer in the matrix,
     * where it is actually read.
     */
    private function warnIfLocalised(QodanaSarifReport|PhpStormInspectionReport $report, string $path): void
    {
        if ($this->stalenessReported || !$report->localised) {
            return;
        }

        $this->stalenessReported = true;

        fwrite(STDERR, sprintf(
            "qodana: %s was produced by a localised IDE; its messages will not match an English run.\n",
            $path,
        ));
    }

    private function toProjectRelativePath(string $path): string
    {
        $prefix = rtrim($this->projectRoot, '/') . '/';
        if (!str_starts_with($path, $prefix)) {
            throw new RuntimeException(sprintf('Path %s is outside project %s', $path, $this->projectRoot));
        }

        return substr($path, strlen($prefix));
    }
}
