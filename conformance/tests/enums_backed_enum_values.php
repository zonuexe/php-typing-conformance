<?php

declare(strict_types=1);

namespace Conformance\Tests\EnumsBackedEnumValues;

/**
 * `$case->value` is the backing scalar, not the case object.
 *
 * `enums_case_object_vs_backing_scalar` asks whether the case itself is a
 * string. This file asks whether `->value` on a string-backed case is
 * `string` and on an int-backed case is `int`.
 *
 * Source: vimeo/psalm#10165 (6.0.0).
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/10165
 * - PHP native backed enums
 */

enum Status: string
{
    case Active = 'active';
}

enum Priority: int
{
    case High = 3;
}

function takesString(string $value): void
{
}

function takesInt(int $value): void
{
}

takesString(Status::Active->value); // V
takesInt(Status::Active->value); // E: Status is string-backed
takesInt(Priority::High->value); // V
takesString(Priority::High->value); // E: Priority is int-backed
