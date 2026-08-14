<?php

declare(strict_types=1);

namespace Conformance\Expectation;

/**
 * A `// T` marker: this line declares the PHPDoc type spelling the test probes.
 *
 * Marked lines answer the *recognition* question. An analyzer that does not
 * know the spelling complains right here — "unresolvable type", "undeclared
 * type", a docblock parse error — so a *type-resolution* diagnostic on a
 * marked line means the spelling was not recognized, and silence (or only
 * style / documented-vs-declared noise) means it was. That is a different
 * question from whether the analyzer then *enforces* the type at the call site,
 * which the `// E` markers answer.
 *
 * Diagnostics on marked lines are never counted as unexpected: complaining
 * about a dialect you do not implement is legitimate behaviour, not a defect.
 */
final readonly class TypeMarker
{
    public function __construct(
        public int $line,
        /** The spelling under test, e.g. `int-range<0, 255>`. May be empty. */
        public string $spelling,
    ) {
    }
}
