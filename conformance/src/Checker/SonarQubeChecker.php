<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

/**
 * Adapter for SonarQube Community Build's bundled sonar-php plugin
 * (https://github.com/SonarSource/sonar-php), driven through sonar-scanner.
 *
 * sonar-scanner cannot analyse without a server: it downloads the PHP plugin
 * and the quality profile from SonarQube, uploads the report, and the issues
 * are then read back from the Web API. This suite talks to a local Community
 * Build (`SONAR_HOST_URL`, default http://127.0.0.1:9000) with `SONAR_TOKEN`.
 * When either is missing `--tool=sonarqube` exits rather than writing empty
 * passes. Not a matrix column (#10); only runs when asked.
 *
 * One corpus scan per process, like PHPStan's max-level pass: sonar-php
 * indexes the project as a whole, and per-file scans would re-upload 200
 * times. Diagnostics are sliced by basename afterwards. The tests live under
 * a directory named `tests/`, which makes the text/secrets sensor skip them
 * as test files; the PHP sensor still runs, and that is the one we read.
 *
 * sonar-php is a rule catalogue (empty methods, unused parameters, insecure
 * `rand()`, generic `Exception`, …), not a PHPDoc type checker. A scan of
 * this corpus produced no argument-type or return-type findings at all. The
 * empty-function and unused-parameter rules fire on every stub used as a
 * typed sink, so they are dropped here — they are the fixture shape, not a
 * type verdict. What remains is recorded; silence on a `// T` row is the
 * measurement that the spelling was not enforced.
 *
 * There is no PHP-version knob. sonar-php 3.59 documents support through 8.4.
 */
final class SonarQubeChecker implements Checker
{
    /**
     * Fixture-shape and style findings that are not a type verdict.
     *
     * `S1172` unused parameter and `S1186` empty function are the two that
     * land on every `function takesInt(int $value): void {}` sink. `S2245`
     * flags `rand()` / `random_int()` used as an unpredictable value.
     * `S112` wants a dedicated exception class instead of `throw new \Exception`.
     * `S1124` is PSR-2 modifier order on `private(set)`. `S4144` / `S905` /
     * `S1848` are duplicate-method, unused-statement and unused-instantiation
     * lint on debug and collection fixtures.
     *
     * @var list<string>
     */
    private const IGNORED_RULES = [
        'php:S1172',
        'php:S1186',
        'php:S2245',
        'php:S112',
        'php:S1124',
        'php:S4144',
        'php:S905',
        'php:S1848',
    ];

    private const PROJECT_KEY = 'php-typing-conformance';

    /** @var array<string, array<int, list<string>>>|null file name => diagnostics */
    private ?array $diagnostics = null;

    private ?string $versionBanner = null;

    public function __construct(
        private readonly string $scannerPath,
        private readonly string $workspacePath,
        private readonly string $hostUrl,
        private readonly string $token,
    ) {
    }

    public function name(): string
    {
        return 'sonarqube';
    }

    /**
     * True when a token is set and the server answers UP. The runner omits
     * this checker from a default run otherwise, so stored results stay put.
     */
    public function available(): bool
    {
        if ($this->token === '') {
            return false;
        }

        $status = $this->api('/api/system/status');

        return ($status['status'] ?? '') === 'UP';
    }

    public function version(): string
    {
        if ($this->versionBanner !== null) {
            return $this->versionBanner;
        }

        $system = $this->api('/api/system/status');
        $server = trim((string) ($system['version'] ?? 'unknown'));

        $phpPlugin = 'unknown';
        $plugins = $this->api('/api/plugins/installed');
        foreach ($plugins['plugins'] ?? [] as $plugin) {
            if (!is_array($plugin)) {
                continue;
            }
            if (($plugin['key'] ?? '') === 'php') {
                $phpPlugin = trim((string) ($plugin['version'] ?? 'unknown'));
                break;
            }
        }

        return $this->versionBanner = sprintf('SonarQube %s (php %s)', $server, $phpPlugin);
    }

    /**
     * @return array<int, list<string>>
     */
    public function analyse(TestCase $testCase): array
    {
        $this->diagnostics ??= $this->scanCorpus();

        return $this->diagnostics[$testCase->fileName] ?? [];
    }

    /**
     * @return array<string, array<int, list<string>>>
     */
    private function scanCorpus(): array
    {
        // Token stays in the environment (`SONAR_TOKEN`); putting it on argv
        // would show it in `ps`. Host is also env (`SONAR_HOST_URL`) so a
        // scanner defaulting to SonarCloud cannot hijack a local run.
        putenv('SONAR_TOKEN=' . $this->token);
        putenv('SONAR_HOST_URL=' . $this->hostUrl);

        $command = sprintf(
            'cd %s && %s'
            . ' -Dsonar.projectKey=%s'
            . ' -Dsonar.projectName=%s'
            . ' -Dsonar.sources=tests,fixtures'
            . ' -Dsonar.sourceEncoding=UTF-8'
            . ' -Dsonar.scm.disabled=true'
            . ' -Dsonar.qualitygate.wait=true'
            . ' -Dsonar.inclusions=**/*.php'
            . ' 2>&1',
            escapeshellarg($this->workspacePath),
            escapeshellarg($this->scannerPath),
            escapeshellarg(self::PROJECT_KEY),
            escapeshellarg('PHP Typing Conformance'),
        );

        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException(
                "sonar-scanner failed:\n" . implode("\n", $output),
            );
        }

        return $this->fetchIssues();
    }

    /**
     * @return array<string, array<int, list<string>>>
     */
    private function fetchIssues(): array
    {
        $byFile = [];
        $page = 1;

        while (true) {
            $payload = $this->api(sprintf(
                '/api/issues/search?componentKeys=%s&ps=500&p=%d',
                rawurlencode(self::PROJECT_KEY),
                $page,
            ));
            $issues = $payload['issues'] ?? [];
            if (!is_array($issues)) {
                break;
            }

            foreach ($issues as $issue) {
                if (!is_array($issue)) {
                    continue;
                }

                $rule = (string) ($issue['rule'] ?? '');
                if (in_array($rule, self::IGNORED_RULES, true)) {
                    continue;
                }

                $line = (int) ($issue['line'] ?? 0);
                $message = trim((string) ($issue['message'] ?? ''));
                $file = basename((string) ($issue['component'] ?? ''));
                if ($line <= 0 || $message === '' || $file === '') {
                    continue;
                }

                $formatted = $rule !== '' ? sprintf('%s [%s]', $message, $rule) : $message;
                $byFile[$file][$line] ??= [];
                $byFile[$file][$line][] = $formatted;
            }

            $total = (int) ($payload['paging']['total'] ?? $payload['total'] ?? 0);
            if ($page * 500 >= $total || $issues === []) {
                break;
            }
            $page++;
        }

        foreach ($byFile as $file => $lines) {
            ksort($lines);
            $byFile[$file] = $lines;
        }
        ksort($byFile);

        return $byFile;
    }

    /**
     * @return array<string, mixed>
     */
    private function api(string $path): array
    {
        $url = rtrim($this->hostUrl, '/') . $path;
        $command = sprintf(
            'curl -sS -m 30 -u %s: %s',
            escapeshellarg($this->token),
            escapeshellarg($url),
        );
        exec($command, $output, $exitCode);
        $raw = implode("\n", $output);
        if ($exitCode !== 0 || $raw === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
