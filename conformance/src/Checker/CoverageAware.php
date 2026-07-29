<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;

/**
 * A checker whose results can be out of date with the corpus.
 *
 * Every {@see Checker} that runs a binary answers for the test file as it is
 * on disk right now, so the question never arises. {@see QodanaChecker} does
 * not run anything — it reads a report someone produced by hand at some point
 * in the past — and a test written after that point was never looked at.
 *
 * Without this the two cases are indistinguishable: an analyzer that inspected
 * a file and found nothing, and an analyzer that never saw the file, both
 * produce an empty diagnostic list, and the second one would be recorded as a
 * clean pass. A new test case would silently join the matrix already green.
 */
interface CoverageAware extends Checker
{
    /**
     * False when this checker's results cannot speak for the test case.
     */
    public function covers(TestCase $testCase): bool;

    /**
     * Why not, phrased for the result record. Empty when covers() is true.
     */
    public function coverageGap(TestCase $testCase): string;
}
