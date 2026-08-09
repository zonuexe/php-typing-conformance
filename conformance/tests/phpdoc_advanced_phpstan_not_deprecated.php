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

// Direct call to the parent's own @deprecated method. This exercises plain
// @deprecated support, not @not-deprecated: no tested analyzer propagates a
// parent's deprecation onto an override, so there is nothing for the tag to
// break, and this diagnostic must not be counted as tag enforcement.
(new Base())->run(); // E?[noise]: deprecated parent method usage
