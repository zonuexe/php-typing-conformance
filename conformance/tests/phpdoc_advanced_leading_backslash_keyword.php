<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedLeadingBackslashKeyword;

/**
 * A leading backslash on a PHPDoc keyword is not a fully-qualified name.
 *
 * A slashed int keyword looks like a slashed DateTime, but int is a language
 * keyword, not a class in the root namespace. Native PHP rejects that form
 * on a parameter type at parse time. In a docblock the slash is spelling.
 * mir 0.73.0 reports InvalidDocblockType (MIR1107) at the token and still
 * type-checks the parameter as int. Other tools may strip the slash and
 * read int, or look up a class named int.
 *
 * A slashed DateTime is the control: a leading backslash on a real class is
 * a fully-qualified name and must stay valid. A tool that rejects every
 * backslash-prefixed type fails that control.
 *
 * References:
 * - mir 0.73.0 InvalidDocblockType / MIR1107 (int and non-empty-array cases)
 */

/**
 * @param \int $value
 */
function takesBackslashInt($value): void // T: \int
{
}

/**
 * @param int $value
 */
function takesInt($value): void
{
}

/**
 * @param \DateTime $value
 */
function takesBackslashDateTime($value): void // T: \DateTime
{
}

// Bare `int` still means int.
takesInt(1); // V
takesInt('x'); // E: string is not int

// A tool that reads `\int` as `int` admits this. A class-name fallback
// rejects it, which is over-rejection of a value the keyword admits.
takesBackslashInt(1); // V

// String is outside `int`, whether the slash was stripped or not.
takesBackslashInt('x'); // E: string is not int

// A leading backslash on a real class remains a fully-qualified name.
takesBackslashDateTime(new \DateTime()); // V
