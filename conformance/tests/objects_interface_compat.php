<?php

declare(strict_types=1);

namespace Conformance\Tests\ObjectsInterfaceCompat;

/**
 * Basic interface compatibility checks.
 *
 * References:
 * - PHP object type compatibility
 */

interface HasName
{
    public function name(): string;
}

final class User implements HasName
{
    #[\Override]
    public function name(): string
    {
        return 'user';
    }
}

final class AnonymousUser
{
    public function id(): int
    {
        return 1;
    }
}

function takesHasName(HasName $value): void
{
}

takesHasName(new User());
takesHasName(new AnonymousUser()); // E: object does not implement HasName
