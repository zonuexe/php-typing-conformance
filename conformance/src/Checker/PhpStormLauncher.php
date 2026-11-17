<?php

declare(strict_types=1);

namespace Conformance\Checker;

/**
 * Finds the PhpStorm binary that can run `inspect` and `mcpServer`.
 *
 * Toolbox puts a `phpstorm` on PATH that is `open -na` and does not wait, so
 * it cannot drive a headless session. The Mac app binary can. Resolution
 * therefore prefers `PhpStorm.app/Contents/MacOS/phpstorm` under Home and
 * `/Applications`, and only then a PATH entry that is a real executable.
 *
 * `PHPSTORM` is an override of that search, not a switch that turns
 * inspection on. A full matrix run measures the qodana column whenever this
 * class finds a launcher and {@see isRunning()} is false.
 */
final class PhpStormLauncher
{
    /**
     * @param string|null $override PHPSTORM, or null to search
     * @param string|null $home     HOME, overridable in tests
     */
    public static function resolve(?string $override = null, ?string $home = null): ?string
    {
        $home ??= (string) (getenv('HOME') ?: '');

        if ($override !== null && $override !== '') {
            if (str_contains($override, '/') || str_starts_with($override, '.')) {
                return self::accept($override);
            }

            $fromName = self::lookupOnPath($override);
            if ($fromName !== null) {
                $accepted = self::accept($fromName);
                if ($accepted !== null) {
                    return $accepted;
                }
            }
        }

        foreach ([
            $home . '/Applications/PhpStorm.app/Contents/MacOS/phpstorm',
            '/Applications/PhpStorm.app/Contents/MacOS/phpstorm',
        ] as $candidate) {
            $accepted = self::accept($candidate);
            if ($accepted !== null) {
                return $accepted;
            }
        }

        if ($override === null || $override === '') {
            $fromPath = self::lookupOnPath('phpstorm');
            if ($fromPath !== null) {
                return self::accept($fromPath);
            }
        }

        return null;
    }

    /**
     * True when a PhpStorm GUI (or leftover inspect) is already up.
     *
     * `phpstorm inspect` refuses to start in that state, so the runner skips
     * the qodana column rather than failing the rest of the matrix.
     */
    public static function isRunning(): bool
    {
        $pids = [];
        exec('pgrep -x phpstorm 2>/dev/null', $pids);

        return $pids !== [];
    }

    public static function isToolboxWrapper(string $path): bool
    {
        if (str_contains($path, '/JetBrains/Toolbox/scripts/')) {
            return true;
        }

        $head = @file_get_contents($path, false, null, 0, 4096);
        if ($head === false) {
            return false;
        }

        return str_starts_with($head, '#!') && str_contains($head, 'open -na');
    }

    private static function accept(string $path): ?string
    {
        if (!is_file($path) || !is_executable($path) || self::isToolboxWrapper($path)) {
            return null;
        }

        return $path;
    }

    private static function lookupOnPath(string $name): ?string
    {
        $found = [];
        exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null', $found);
        $path = $found[0] ?? '';

        return $path !== '' ? $path : null;
    }
}
