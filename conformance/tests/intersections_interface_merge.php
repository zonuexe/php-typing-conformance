<?php

declare(strict_types=1);

namespace Conformance\Tests\IntersectionsInterfaceMerge;

/**
 * Basic intersection compatibility.
 *
 * References:
 * - PHP intersection types
 * - python-typing protocols / structural composition inspiration
 */

interface HasId
{
    public function id(): int;
}

interface HasName
{
    public function name(): string;
}

final class User implements HasId, HasName
{
    #[\Override]
    public function id(): int
    {
        return 1;
    }

    #[\Override]
    public function name(): string
    {
        return 'user';
    }
}

final class AnonymousUser implements HasId
{
    #[\Override]
    public function id(): int
    {
        return 1;
    }
}

function takesIdentifiedNamed(HasId&HasName $value): void
{
}

takesIdentifiedNamed(new User());
takesIdentifiedNamed(new AnonymousUser()); // E: object does not satisfy the full intersection
