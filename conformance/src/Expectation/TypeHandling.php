<?php

declare(strict_types=1);

namespace Conformance\Expectation;

/**
 * How one analyzer handled the PHPDoc type spelling a `// T`-marked test probes.
 *
 * Two independent facets, kept apart on purpose:
 *
 * - **Recognition** — does the analyzer resolve the spelling at all? Reported on
 *   the `// T` lines. Level-independent for every analyzer here.
 * - **Enforcement** — does it then reject the values the spelling excludes?
 *   Reported on the `// E` lines. For PHPStan this is gated by the level, since
 *   levels switch rule sets on; recognition never is.
 *
 * Collapsing the two into one word is what made labels like "Full support"
 * ambiguous: it could be read either as "accepts the type name" or as "warns
 * when a value is out of range", and for a `// T`-marked family test those can
 * even have different answers for different spellings in the same file.
 */
final readonly class TypeHandling
{
    public const RECOGNIZED = 'recognized';
    public const UNRECOGNIZED = 'unrecognized';

    public const ENFORCED = 'enforced';
    public const PARTIAL = 'partial';
    public const NONE = 'none';

    /**
     * @param list<int> $unrecognizedLines `// T` lines the analyzer complained about
     * @param list<int> $falsePositiveLines lines that are neither marked nor expected
     */
    public function __construct(
        public string $recognition,
        public string $enforcement,
        public array $unrecognizedLines,
        public array $falsePositiveLines,
        public int $expectedLineCount,
        public int $enforcedLineCount,
    ) {
    }
}
