<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanNotDeprecated;

/**
 * Cross-tool handling of `@not-deprecated`.
 *
 * `@deprecated` on a parent method is inherited. `@not-deprecated` on an
 * override breaks that chain so callers of the child are clean while direct
 * calls to the parent stay deprecated (when the tool reports deprecations).
 *
 * References:
 * - PHPStan phpdocs-basics: Deprecations / `@not-deprecated`
 *
 * @conformance-kind style
 */

class Base
{
    /** @deprecated Use child instead */
    public function run(): void
    {
    }
}

class Child extends Base
{
    /** @not-deprecated */
    #[\Override]
    public function run(): void // T: @not-deprecated
    {
    }
}

(new Child())->run();

(new Base())->run(); // E?: deprecated parent method usage
