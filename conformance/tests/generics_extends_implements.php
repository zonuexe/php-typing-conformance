<?php

declare(strict_types=1);

namespace Conformance\Tests\GenericsExtendsImplements;

/**
 * Generic inheritance via `@extends` / `@implements`.
 *
 * A subclass that documents `@extends Box<int>` should behave as `Box<int>`
 * at call sites: accepted where `Box<int>` is required, rejected where
 * `Box<string>` is required.
 *
 * References:
 * - PHPStan generics-in-php-using-phpdocs
 * - Psalm templated_annotations (@extends / @implements)
 */

/**
 * @template T
 */
class Box
{
    /**
     * @param T $value
     */
    public function __construct(
        public mixed $value, // E<noverify>: NoVerify does not understand class-level template parameters
    ) {
    }
}

/**
 * @extends Box<int>
 */
final class IntBox extends Box
{
    public function __construct(int $value)
    {
        parent::__construct($value);
    }
}

/**
 * @param Box<int> $box
 */
function takesIntBox(Box $box): void // E<noverify>: NoVerify does not accept Box<int> parameter annotations
{
}

/**
 * @param Box<string> $box
 */
function takesStringBox(Box $box): void // E<noverify>: NoVerify does not accept Box<string> parameter annotations
{
}

takesIntBox(new IntBox(1));

// IntBox is Box<int>, not Box<string>.
takesStringBox(new IntBox(1)); // E: Box<int> subclass should not satisfy Box<string>
