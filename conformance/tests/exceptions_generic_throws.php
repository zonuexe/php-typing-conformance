<?php

declare(strict_types=1);

namespace Conformance\Tests\ExceptionsGenericThrows;

/**
 * Generic exception classes in `@throws` and in `catch`.
 *
 * `GenericException<User>` carries a `User` payload. Honouring the template
 * on the caught variable makes `name()` valid and `missing()` an error.
 * Tools that drop `T` on `catch` see `mixed` and stay silent on `missing()`.
 *
 * Source: vimeo/psalm#12090 (6.19.2).
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/12090
 */

final class User
{
    public function name(): string
    {
        return 'user';
    }
}

/**
 * @template T
 */
final class GenericException extends \Exception
{
    /**
     * @param T $data
     */
    public function __construct(
        public mixed $data,
    ) {
        parent::__construct();
    }
}

/**
 * @throws GenericException<User>
 */
function throwsUser(): void
{
    throw new GenericException(new User());
}

function catchesUser(): void
{
    try {
        throwsUser();
    } catch (GenericException $exception) {
        echo $exception->data->name(); // V: T is User
        echo $exception->data->missing(); // E?: User has no missing()
    }
}
