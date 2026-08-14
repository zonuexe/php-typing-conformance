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
     * The test carries no `// E` probes at all, so enforcement was never put to
     * the question. Distinct from NONE, which means probes existed and none of
     * them fired: "nothing rejected" and "nothing asked" are different facts.
     */
    public const NO_PROBES = 'no-probes';

    /**
     * @param list<int> $unrecognizedLines `// T` lines the analyzer complained about
     *     with a type-resolution failure (not style / documented-vs-declared noise)
     * @param list<int> $falsePositiveLines lines that are neither marked nor expected
     * @param list<int> $overRejectedLines valid-control / unmarked-valid lines
     *     rejected with a type-mismatch — enforcement on the `// E` lines is
     *     then incidental (the analyzer also rejects values the type admits)
     */
    public function __construct(
        public string $recognition,
        public string $enforcement,
        public array $unrecognizedLines,
        public array $falsePositiveLines,
        public int $expectedLineCount,
        public int $enforcedLineCount,
        public array $overRejectedLines = [],
    ) {
    }

    /**
     * Hits on the violating lines cannot be trusted as enforcement: either the
     * spelling was not resolved, or values the type admits were also rejected.
     */
    public function isIncidental(): bool
    {
        return $this->recognition === self::UNRECOGNIZED || $this->overRejectedLines !== [];
    }
}
