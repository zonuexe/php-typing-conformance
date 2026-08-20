<?php

declare(strict_types=1);

namespace Conformance\Tests\PropertiesPromotedPropertyHookBody;

/**
 * Property-hook bodies on constructor-promoted properties.
 *
 * A set hook on a promoted property is still a function body: a type
 * mismatch inside it should be reported the same way it would on a
 * non-promoted hooked property. Mago 1.47 started analysing these bodies
 * (carthage-software/mago#2218); before that the hook was skipped.
 *
 * References:
 * - PHP language: constructor property promotion + property hooks
 * - https://github.com/carthage-software/mago/issues/2218
 */

function takesString(string $value): void
{
}

function takesInt(int $value): void
{
}

final class Promoted
{
    public function __construct(
        public int $value {
            set(int $value) {
                takesString($value); // E: int is not string
            }
        },
    ) {
    }
}

final class Regular
{
    public int $value = 0 {
        set(int $value) {
            takesString($value); // E: the same mismatch, not promotion-specific
            $this->value = $value;
        }
    }
}

final class PromotedOk
{
    public function __construct(
        public int $value {
            set(int $value) {
                takesInt($value); // V
            }
        },
    ) {
    }
}
