<?php

declare(strict_types=1);

namespace Conformance\Tests\PropertiesOverrideAttribute;

/**
 * PHP 8.5 lets `#[\Override]` mark a property that replaces a parent property.
 *
 * A matching parent property is silent. A property with no parent counterpart
 * is a fatal (`has #[\Override] attribute, but no matching parent property
 * exists`). Class constants are not a valid target (`allowed targets: method,
 * property`). Method `#[\Override]` is the control: that target is older and
 * must stay accepted.
 *
 * Source: vimeo/psalm#11891 (6.19.0).
 *
 * References:
 * - https://wiki.php.net/rfc/marking_overriden_methods
 * - https://github.com/vimeo/psalm/pull/11891
 */

class ParentBox
{
    public int $value = 1;

    public function label(): string
    {
        return 'parent';
    }
}

class MatchingProperty extends ParentBox
{
    #[\Override] // E?[noise]: tools that still reject Override on properties blame the attribute
    public int $value = 2; // V: the parent declares the same property
}

class MatchingMethod extends ParentBox
{
    #[\Override]
    public function label(): string // V: method Override is not what this file is adding
    {
        return 'child';
    }
}

class MissingParentProperty extends ParentBox
{
    #[\Override] // E[missing]
    public int $other = 3; // E[missing]: no parent property named $other
}

class OverrideOnConstant extends ParentBox
{
    #[\Override] // E?[const]
    public const int FLAG = 1; // E?[const]: Override cannot target a class constant
}
