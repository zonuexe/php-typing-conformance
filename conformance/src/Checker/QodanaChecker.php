<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

/**
 * Adapter for Qodana (https://www.jetbrains.com/qodana/), the analysis engine
 * behind PhpStorm's inspections.
 *
 * The one column this suite does not run. Qodana is proprietary and cannot be
 * shipped here, so the measurement is performed by hand from PhpStorm — the
 * IDE's own "Run Qodana in the IDE" action, described at
 * https://www.jetbrains.com/help/qodana/quick-start.html#quickstart-run-in-ide
 * — and this class reads the `qodana.sarif.json` it leaves behind rather than
 * invoking anything.
 *
 * It is worth being explicit that this is *not*
 * https://github.com/JetBrains/qodana-cli. That is a separate artifact with
 * its own versioning and its own Qodana Cloud licensing, and the version it
 * pins in qodana.yaml's `linter:` field describes a CI container that never
 * runs here.
 *
 * Everything else follows from having no binary to invoke: there is no
 * artifact to pin, so the configuration is pinned instead, in
 * `qodana.yaml` and `.idea/inspectionProfiles/Project_Default.xml` — see
 * {@see QodanaSarifReport::TYPE_RULES} for which inspections are measured and
 * why those.
 *
 * A report is a snapshot of one working tree at one moment, so two things
 * that other checkers get for free have to be asserted here. Its git revision
 * is compared against HEAD, because a stale report will happily answer for
 * test files that have changed underneath it. And a file the report never
 * mentions is recorded as clean — SARIF carries no list of what was analysed,
 * so "inspected and silent" and "never inspected" are indistinguishable, and
 * the whole-project scan makes the former overwhelmingly likelier.
 *
 * Support files are not passed separately the way the CLI checkers pass them:
 * PhpStorm indexes the entire project, so a `_`-prefixed helper is always in
 * scope whether or not this suite mentions it.
 */
final class QodanaChecker implements Checker
{
    private ?QodanaSarifReport $report = null;

    private bool $stalenessReported = false;

    /**
     * @param string|null $reportPath null resolves the newest report at the
     *                                moment of the first analysis
     */
    public function __construct(
        private readonly string $projectRoot,
        private readonly ?string $reportPath = null,
        private readonly ?string $searchDirectory = null,
    ) {
    }

    public function name(): string
    {
        return 'qodana';
    }

    public function version(): string
    {
        $report = $this->report();

        // The IDE build number, which is what the report states — 262.8665.325
        // for PhpStorm 2026.2. There is no separate Qodana version to read.
        return trim(sprintf('%s %s', $report->toolName, $report->toolVersion));
    }

    /**
     * @return array<int, list<string>>
     */
    public function analyse(TestCase $testCase): array
    {
        return $this->report()->forPath($this->toProjectRelativePath($testCase->path));
    }

    private function report(): QodanaSarifReport
    {
        if ($this->report !== null) {
            return $this->report;
        }

        $path = $this->reportPath ?? QodanaSarifReport::locateLatest($this->searchDirectory);
        $report = QodanaSarifReport::fromFile($path);

        $this->warnIfStale($report, $path);

        return $this->report = $report;
    }

    /**
     * Loudly, and once, and without stopping the run: which revision to
     * measure is the operator's call, and a deliberate re-read of an older
     * report is a reasonable thing to want.
     */
    private function warnIfStale(QodanaSarifReport $report, string $path): void
    {
        if ($this->stalenessReported) {
            return;
        }

        $this->stalenessReported = true;

        if ($report->localised) {
            fwrite(STDERR, sprintf(
                "qodana: %s was produced by a localised IDE; its messages will not match an English run.\n",
                $path,
            ));
        }

        $head = $this->headRevision();
        if ($head === null || $report->revisionId === null || $report->revisionId === $head) {
            return;
        }

        fwrite(STDERR, sprintf(
            "qodana: %s was produced at %s but HEAD is %s; re-run Inspect Code in PhpStorm to re-measure.\n",
            $path,
            substr($report->revisionId, 0, 12),
            substr($head, 0, 12),
        ));
    }

    private function headRevision(): ?string
    {
        $command = sprintf('git -C %s rev-parse HEAD 2>/dev/null', escapeshellarg($this->projectRoot));
        $revision = trim((string) shell_exec($command));

        return $revision !== '' ? $revision : null;
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
