<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmClassStringMap;

/**
 * `class-string-map<T of X, T>` is a Psalm-only dependent generic.
 *
 * It types a map whose keys are class-strings and whose value is an instance of
 * that exact class. Only Psalm models this dependent relationship; other
 * analyzers do not recognize the keyword. This records who parses it at all.
 *
 * References:
 * - Psalm TClassStringMap (class-string-map<T as Foo, T>)
 */

/**
 * @param class-string-map<T of \Throwable, T> $map
 */
function acceptsThrowableMap(array $map): void // T: class-string-map<T of \Throwable, T>
{
}

// A class-string key mapped to an instance of that class is valid.
acceptsThrowableMap([\RuntimeException::class => new \RuntimeException()]);

// A value that is not an instance of the key class is rejected by Psalm.
acceptsThrowableMap([\RuntimeException::class => new \LogicException()]); // E?: value must be an instance of the class-string key
