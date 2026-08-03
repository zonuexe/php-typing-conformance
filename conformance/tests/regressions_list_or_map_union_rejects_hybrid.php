<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsListOrMapUnionRejectsHybrid;

/**
 * `list<T>|array<K, V>` rejects a hybrid list-and-map value.
 *
 * HTML-attribute style APIs often accept either a list of flag names
 * (`list<string>`) or a string-keyed map of values
 * (`array<string, bool|string|int>`). A value that mixes both shapes —
 * a positional string *and* a string key — is neither a list nor a pure
 * string-keyed map, so it should be rejected.
 *
 * Same family as the homogeneous-array union case (#8963): a union of
 * array types is not a free-form "either shape can show up in the same
 * value".
 *
 * Reference: https://github.com/phpstan/phpstan/issues/8963
 */

/**
 * @param list<string>|array<string, bool|string|int> $values
 */
function attrs(array $values): void // E<noverify>: NoVerify cannot evaluate the list|array union and flags the signature itself
{
}

// Pure list of flag names — member of list<string>.
attrs(['disabled', 'contenteditable']);

// Pure string-keyed map — member of array<string, bool|string|int>.
attrs(['disabled' => true, 'href' => 'https://example.com']);

// Hybrid: positional string plus a string key. Neither list nor map.
attrs(['disabled', 'href' => 'https://example.com']); // E: hybrid list+map is not accepted by list<string>|array<string, bool|string|int>
