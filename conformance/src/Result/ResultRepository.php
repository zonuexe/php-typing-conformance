<?php

declare(strict_types=1);

namespace Conformance\Result;

use Internal\Toml\Toml;
use RuntimeException;

final class ResultRepository
{
    public function __construct(
        private readonly string $resultsRoot,
    ) {
    }

    public function save(ResultRecord $record): string
    {
        $toolDir = $this->resultsRoot . DIRECTORY_SEPARATOR . $record->tool;
        if (!is_dir($toolDir) && !mkdir($toolDir, 0777, true) && !is_dir($toolDir)) {
            throw new RuntimeException(sprintf('Failed to create results directory: %s', $toolDir));
        }

        $path = $toolDir . DIRECTORY_SEPARATOR . $record->testName . '.toml';
        $toml = (string) Toml::encode($record->toArray());

        if (file_put_contents($path, $toml) === false) {
            throw new RuntimeException(sprintf('Failed to write result file: %s', $path));
        }

        return $path;
    }
}
