<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;

interface Checker
{
    public function name(): string;

    public function version(): string;

    /**
     * @return array<int, list<string>>
     */
    public function analyse(TestCase $testCase): array;
}
