<?php

declare(strict_types=1);

namespace Conformance\Tests\NativeTypesUntypedReturnInferred;

/**
 * Return type inferred from an untyped body.
 *
 * A function or method with neither a native return type nor `@return` has an
 * implicit `mixed` return. Some tools infer the type from the body instead —
 * here every path returns a User — and then catch a method User lacks.
 *
 * Treating the result as mixed is a legitimate reading (PHPStan does, and
 * reports a mixed method call only at level 10), so a miss here is a design
 * choice rather than a defect.
 *
 * References:
 * - PHPStan rule levels (implicit mixed at level 10)
 */

final class User
{
    public function name(): string
    {
        return 'user';
    }
}

function makeUser() // E?[noise]: missing return type
{
    return new User();
}

final class Repository
{
    public function find() // E?[noise]: missing return type
    {
        return new User();
    }
}

function callsFunction(): void
{
    echo makeUser()->missing(); // E: the body only returns a User
}

function callsMethod(Repository $repository): void
{
    echo $repository->find()->missing(); // E: the body only returns a User
}
