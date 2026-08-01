<?php

declare(strict_types=1);

namespace Conformance\Lsp;

use RuntimeException;

/**
 * Encodes one probe run's payload as TOML.
 *
 * Hand-rolled for the same reason ResultRepository rolls its own: the
 * internal/toml encoder writes a string containing single quotes as a
 * literal string, which does not survive its own parser, and hover text
 * quotes whatever a server said — `'alpha'|'beta'` broke the report's very
 * first read-back. Strings here are always TOML basic strings via
 * json_encode, whose escape set is a valid subset of TOML's.
 *
 * Understands exactly the payload shape run-lsp-probes.php writes: scalars
 * and flat lists at the top level, then one level of sub-tables whose rows
 * are themselves flat. Anything deeper is a bug in the payload, not a case
 * to support.
 */
final class LspResultFile
{
    /** @param array<string, mixed> $payload */
    public static function encode(array $payload): string
    {
        $scalars = [];
        $tables = [];

        foreach ($payload as $key => $value) {
            if (is_array($value) && !array_is_list($value)) {
                $tables[$key] = $value;
            } else {
                $scalars[] = self::keyValue($key, $value);
            }
        }

        $blocks = [implode("\n", $scalars)];

        foreach ($tables as $tableKey => $rows) {
            foreach ($rows as $rowKey => $row) {
                if (!is_array($row) || array_is_list($row)) {
                    throw new RuntimeException("Unexpected shape under [{$tableKey}]: {$rowKey}");
                }
                $lines = ['[' . self::key($tableKey) . '.' . self::key((string) $rowKey) . ']'];
                foreach ($row as $key => $value) {
                    $lines[] = self::keyValue((string) $key, $value);
                }
                $blocks[] = implode("\n", $lines);
            }
        }

        return implode("\n\n", $blocks) . "\n";
    }

    private static function keyValue(string $key, mixed $value): string
    {
        return self::key($key) . ' = ' . self::value($value);
    }

    private static function key(string $key): string
    {
        return preg_match('/\A[A-Za-z0-9_-]+\z/', $key) === 1 ? $key : self::string($key);
    }

    private static function value(mixed $value): string
    {
        if (is_string($value)) {
            return self::string($value);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_array($value) && array_is_list($value)) {
            return '[' . implode(', ', array_map(self::value(...), $value)) . ']';
        }

        throw new RuntimeException('Unsupported value type: ' . get_debug_type($value));
    }

    private static function string(string $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Unencodable string in probe payload');
        }

        return $encoded;
    }
}
