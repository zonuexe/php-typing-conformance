<?php

declare(strict_types=1);

namespace Conformance\Result;

use DateTimeImmutable;
use DateTimeInterface;
use Internal\Toml\Toml;
use RuntimeException;
use function hash;
use function hash_file;
use function implode;
use function is_file;
use function sort;
use function sprintf;
use function trim;

/**
 * When the results last changed, written down by the run that changed them.
 *
 * The report states this, and nothing else can tell it: file modification
 * times do not survive a clone -- a checkout stamps every file with the moment
 * it was written -- and the time the pages were built says nothing about how
 * fresh the comparison is. So the runner records the moment, the record is
 * committed with the data it describes, and the report reads it back.
 *
 * "Changed" is decided by a digest of the data itself, not by the run having
 * happened: a run that re-derives the same results for the same tests at the
 * same versions leaves the stamp where it was. Otherwise re-running the suite
 * would keep claiming the comparison is fresher than it is, and every run
 * would dirty the working tree.
 *
 * The stamp is ISO 8601 with an offset, and travels as text: the report prints
 * exactly what was written here.
 */
final class ResultsUpdate
{
    /** In the results root, beside the per-tool directories. */
    public const FILE = 'updated.toml';

    private const KEY = 'updated_at';

    private const DIGEST_KEY = 'data_digest';

    /**
     * @param string $resultsRoot per-tool results and versions
     * @param string $testsDir the test set the results are about
     */
    public function __construct(
        private readonly string $resultsRoot,
        private readonly string $testsDir,
    ) {
    }

    /**
     * Stamp the data as updated, unless nothing about it moved.
     *
     * @return string the stamp now on record, new or kept
     */
    public function record(?DateTimeInterface $at = null): string
    {
        $digest = $this->digest();
        $recorded = $this->read();

        $stamp = trim((string) ($recorded[self::KEY] ?? ''));
        if ($stamp !== '' && ($recorded[self::DIGEST_KEY] ?? null) === $digest) {
            return $stamp;
        }

        $stamp = ($at ?? new DateTimeImmutable())->format(DateTimeInterface::ATOM);
        $path = $this->path();
        $toml = (string) Toml::encode([self::KEY => $stamp, self::DIGEST_KEY => $digest]);

        if (file_put_contents($path, $toml) === false) {
            throw new RuntimeException(sprintf('Failed to write the update timestamp: %s', $path));
        }

        return $stamp;
    }

    /**
     * What was recorded, exactly as recorded, or null if nothing has been.
     */
    public function recorded(): ?string
    {
        $stamp = trim((string) ($this->read()[self::KEY] ?? ''));

        return $stamp === '' ? null : $stamp;
    }

    public function path(): string
    {
        return $this->resultsRoot . '/' . self::FILE;
    }

    /**
     * One value over everything the report is derived from: every test in the
     * set, every tool's version, and every result recorded for them.
     *
     * Content, not modification time, so re-writing a file with what it
     * already said is not a change.
     */
    private function digest(): string
    {
        $entries = [];

        foreach ([...glob($this->resultsRoot . '/*/*.toml') ?: [], ...glob($this->testsDir . '/*.php') ?: []] as $file) {
            $hash = hash_file('sha256', $file);

            if ($hash !== false) {
                // Enough of the path to tell two files apart, and nothing that
                // changes with where the repository is checked out.
                $entries[] = basename(dirname($file)) . '/' . basename($file) . ' ' . $hash;
            }
        }

        sort($entries);

        return hash('sha256', implode("\n", $entries));
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $path = $this->path();

        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        return $contents === false ? [] : Toml::parseToArray($contents);
    }
}
