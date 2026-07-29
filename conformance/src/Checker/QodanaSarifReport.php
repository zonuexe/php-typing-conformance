<?php

declare(strict_types=1);

namespace Conformance\Checker;

use JsonException;
use RuntimeException;

/**
 * Reads a `qodana.sarif.json` produced by PhpStorm's "Inspect Code" / Qodana
 * IDE run and turns it into the same shape every {@see Checker} produces.
 *
 * Qodana itself is not run by this repository: its licence does not allow us to
 * ship the linter, so the report is generated interactively in PhpStorm and
 * only parsed here. Everything below therefore has to survive a file that was
 * written by someone else's IDE, in someone else's locale, at some other point
 * in history — hence the fingerprinting and the revision check.
 */
final class QodanaSarifReport
{
    /**
     * Inspections that speak about types, in the sense this suite measures.
     *
     * The SARIF taxonomy looks like the natural filter — `PHP/Type
     * compatibility` is exactly the right taxon — but taxa ids are localised:
     * a Japanese IDE emits `PHP/型の互換性` for the same rules. An explicit id
     * allowlist is therefore the only locale-stable option. Inspection ids
     * themselves are ASCII and stable across releases.
     *
     * This list mirrors the inspections switched on in
     * `.idea/inspectionProfiles/Project_Default.xml`; that profile is what the
     * IDE actually obeys, and this constant is the second lock, so that a
     * profile edited by hand in the IDE cannot silently widen a measurement.
     *
     * Deliberately absent are PhpMissingParamType / PhpMissingReturnType /
     * PhpMissingFieldType / PhpMissingClassConstantType. They report the
     * absence of a declaration rather than a disagreement between types, and
     * the corpus leaves declarations off on purpose — `constants_class_
     * constant_type.php` types its constant and expects the mismatch at the
     * call site, so the nag would land as a false positive.
     *
     * @var list<string>
     */
    public const TYPE_RULES = [
        // PHP/Type compatibility
        'PhpIncompatibleReturnTypeInspection',
        'PhpParamsInspection',
        'PhpStrictTypeCheckingInspection',
        // PHP/PHPDoc — but not PHP/PHPDoc/Code style, which is where
        // PhpDocMissingThrowsInspection and friends live.
        'PhpDocSignatureInspection',
        'PhpReturnDocTypeMismatchInspection',
        // PHP/Attributes — where array shapes are reported
        'PhpArrayKeyDoesNotMatchArrayShapeInspection',
        'PhpMissingArrayKeyInspection',
        // PHP/Undefined symbols — how PhpStorm rejects an unknown pseudo-type
        // such as `non-empty-string`: it reads it as a class name.
        'PhpUndefinedClassInspection',
        'PhpUndefinedNamespaceInspection',
        // PHP/Control flow — narrowing outcomes
        'PhpConditionAlreadyCheckedInspection',
        'PhpExpressionAlwaysNullInspection',
        'PhpTypedPropertyMightBeUninitializedInspection',
        // PHP/General, PHP/Probable bugs — property and enum typing rules
        'PhpCannotModifyPropertyOutsideSetVisibilityScopeInspection',
        'PhpDeprecatedImplicitlyNullableParameterInspection',
        'PhpReadonlyPropertyWrittenOutsideDeclarationScopeInspection',
        'PhpUncoveredEnumCasesInspection',
    ];

    /**
     * @param array<string, array<int, list<string>>> $diagnostics keyed by repository-relative path
     * @param array<string, string> $ruleTitles inspection id => localised short description
     */
    private function __construct(
        public readonly string $toolName,
        public readonly string $toolVersion,
        public readonly ?string $revisionId,
        public readonly ?string $startedAt,
        public readonly bool $localised,
        public readonly array $diagnostics,
        public readonly array $ruleTitles,
    ) {
    }

    /**
     * Locate the newest `qodana.sarif.json` PhpStorm has left behind.
     *
     * The IDE writes each run into a fresh `qodana_output`, `qodana_output1`,
     * `qodana_output2`, … under the temporary directory, and the whole set is
     * wiped on restart — so neither the suffix nor a fixed name identifies the
     * latest report. Modification time does, and it is also the only ordering
     * that stays right once the counter resets and `qodana_output` (no suffix)
     * becomes the newest again.
     *
     * Two neighbours are deliberately not matched. `qodana-converter/` and
     * `qodana-converter-input/` are byte-identical copies of whichever run was
     * last opened in the browser, which is not necessarily the newest one. And
     * `qodana-short.sarif.json`, sitting inside each output directory, is the
     * new-problems-since-baseline view: it carries the same summary counts but
     * an empty `results` array, so reading it would quietly measure nothing.
     */
    public static function locateLatest(?string $searchDirectory = null): string
    {
        $directory = rtrim($searchDirectory ?? sys_get_temp_dir(), '/');
        $candidates = glob($directory . '/qodana_output*/qodana.sarif.json');
        if ($candidates === false) {
            $candidates = [];
        }

        $newest = null;
        $newestTime = -1;
        foreach ($candidates as $candidate) {
            $modified = filemtime($candidate);
            // Ties are broken by path so the result never depends on glob order.
            if ($modified !== false && ($modified > $newestTime || ($modified === $newestTime && $candidate < (string) $newest))) {
                $newest = $candidate;
                $newestTime = $modified;
            }
        }

        if ($newest === null) {
            throw new RuntimeException(sprintf(
                'No qodana_output*/qodana.sarif.json found under %s. Run "Inspect Code" in PhpStorm first.',
                $directory,
            ));
        }

        return $newest;
    }

    /**
     * @param list<string>|null $ruleFilter null keeps every inspection
     */
    public static function fromFile(
        string $path,
        string $pathPrefix = 'conformance/tests/',
        ?array $ruleFilter = self::TYPE_RULES,
        bool $includePromo = false,
    ): self {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException(sprintf('Unable to read Qodana SARIF report at %s', $path));
        }

        try {
            /** @var array<string, mixed> $sarif */
            $sarif = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('Qodana SARIF report at %s is not valid JSON: %s', $path, $e->getMessage()), 0, $e);
        }

        $run = $sarif['runs'][0] ?? null;
        if (!is_array($run)) {
            throw new RuntimeException('Qodana SARIF report has no runs[0].');
        }

        $results = [];
        foreach (self::asList($run['results'] ?? null) as $result) {
            $results[] = $result;
        }

        // `results` is what the IDE's problem tree shows and what
        // `qodanaNewResultSummary.total` counts. An empty array under a
        // non-zero total means this is `qodana-short.sarif.json` — the
        // baseline-diff view — and measuring it would record a clean run.
        $reportedTotal = $run['properties']['qodanaNewResultSummary']['total'] ?? 0;
        if ($results === [] && is_int($reportedTotal) && $reportedTotal > 0) {
            throw new RuntimeException(sprintf(
                'Report at %s claims %d problems but carries no results; read qodana.sarif.json rather than qodana-short.sarif.json.',
                $path,
                $reportedTotal,
            ));
        }

        // The starter profile disables a handful of inspections that are
        // Ultimate-only upsells; the IDE still runs them and parks the hits in
        // `runs[0].properties["qodana.promo.results"]`. Some of those — notably
        // PhpReturnDocTypeMismatchInspection — are squarely on topic.
        //
        // They are still excluded, because the set is not stable: three runs
        // over the same revision produced 24, 20 and 18 promo hits with
        // entirely different rules present each time. It is a sample of what
        // the profile leaves disabled, not an inspection result.
        //
        // The fix is not to read them but to stop them being promo: enabling an
        // inspection in the profile moves it into `results`, where it is
        // deterministic. That is what the pinned profile does for
        // PhpReturnDocTypeMismatchInspection and PhpDocSignatureInspection.
        // Keep this switch for exploring what an unpinned inspection would say.
        if ($includePromo) {
            foreach (self::asList($run['properties']['qodana.promo.results'] ?? null) as $result) {
                $results[] = $result;
            }
        }

        $rows = [];
        foreach ($results as $result) {
            $ruleId = is_string($result['ruleId'] ?? null) ? $result['ruleId'] : '';
            if ($ruleId === '') {
                continue;
            }
            if ($ruleFilter !== null && !in_array($ruleId, $ruleFilter, true)) {
                continue;
            }

            $location = $result['locations'][0]['physicalLocation'] ?? null;
            if (!is_array($location)) {
                continue;
            }

            $uri = $location['artifactLocation']['uri'] ?? null;
            if (!is_string($uri) || !str_starts_with($uri, $pathPrefix)) {
                continue;
            }

            $line = $location['region']['startLine'] ?? null;
            if (!is_int($line) || $line <= 0) {
                continue;
            }

            $message = trim((string) ($result['message']['text'] ?? ''));
            if ($message === '') {
                continue;
            }

            $rows[] = [
                'uri' => $uri,
                'line' => $line,
                'column' => (int) ($location['region']['startColumn'] ?? 0),
                'offset' => (int) ($location['region']['charOffset'] ?? 0),
                'rule' => $ruleId,
                'text' => sprintf('%s [%s]', self::normaliseWhitespace($message), $ruleId),
            ];
        }

        // SARIF result order is not contractual, and two diagnostics can share
        // a line (differing only by column). Sorting on the full tuple keeps
        // the emitted TOML byte-identical across re-runs of the same report.
        usort($rows, static fn (array $a, array $b): int
            => [$a['uri'], $a['line'], $a['column'], $a['offset'], $a['rule'], $a['text']]
            <=> [$b['uri'], $b['line'], $b['column'], $b['offset'], $b['rule'], $b['text']]);

        $diagnostics = [];
        foreach ($rows as $row) {
            $diagnostics[$row['uri']][$row['line']][] = $row['text'];
        }
        ksort($diagnostics);

        return new self(
            toolName: (string) ($run['tool']['driver']['fullName'] ?? $run['tool']['driver']['name'] ?? 'Qodana'),
            toolVersion: (string) ($run['tool']['driver']['version'] ?? ''),
            revisionId: self::stringOrNull($run['versionControlProvenance'][0]['revisionId'] ?? null),
            startedAt: self::stringOrNull($run['invocations'][0]['startTimeUtc'] ?? null),
            localised: self::detectLocalisation($run),
            diagnostics: $diagnostics,
            ruleTitles: self::collectRuleTitles($run),
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
     * Was the report produced by an IDE running a localised UI?
     *
     * Diagnostic messages are the thing that actually matters — a Japanese IDE
     * writes `未定義のクラス 'non-empty-string'` where an English one writes
     * `Undefined class` — but messages are a poor detector: a report with no
     * results has none to inspect. The taxonomy is always present and is
     * translated in lockstep, and its English form is pure ASCII, so a
     * non-ASCII taxon id is the reliable signal. Rule descriptions are not
     * usable here: even the English ones carry typographic quotes.
     *
     * @param array<string, mixed> $run
     */
    private static function detectLocalisation(array $run): bool
    {
        foreach (self::asList($run['tool']['driver']['taxa'] ?? null) as $taxon) {
            $id = $taxon['id'] ?? null;
            if (is_string($id) && preg_match('/[^\x20-\x7e]/', $id) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $run
     * @return array<string, string>
     */
    private static function collectRuleTitles(array $run): array
    {
        $titles = [];
        foreach (self::asList($run['tool']['extensions'] ?? null) as $extension) {
            foreach (self::asList($extension['rules'] ?? null) as $rule) {
                $id = $rule['id'] ?? null;
                $title = $rule['shortDescription']['text'] ?? null;
                if (is_string($id) && is_string($title)) {
                    $titles[$id] = $title;
                }
            }
        }
        ksort($titles);

        return $titles;
    }

    /**
     * `tool.driver.rules` is empty in an IDE-produced report; the metadata sits
     * in `tool.extensions[].rules` instead, split per plugin.
     *
     * @return list<array<string, mixed>>
     */
    private static function asList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function normaliseWhitespace(string $message): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $message));
    }
}
