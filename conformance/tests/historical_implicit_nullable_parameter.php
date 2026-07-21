<?php

declare(strict_types=1);

namespace Conformance\Tests\HistoricalImplicitNullableParameter;

/**
 * Historical implicit-nullable parameter syntax.
 *
 * References:
 * - python-typing historical group inspiration
 * - legacy PHP signatures that relied on a null default before explicit nullable hints
 *
 * @conformance-kind style
 */
function legacyImplicitNullable(array $values = null): void // E?: implicit nullable parameter
{
}

legacyImplicitNullable();
legacyImplicitNullable([1, 2, 3]);
