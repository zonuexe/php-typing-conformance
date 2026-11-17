<?php

declare(strict_types=1);

/**
 * Self-test for PhpStormLauncher resolution.
 *
 * Run: php conformance/src/Checker/self-test.php
 */

use Conformance\Checker\PhpStormLauncher;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$failures = 0;

function expect(string $label, mixed $actual, mixed $expected): void
{
    global $failures;
    if ($actual !== $expected) {
        $failures++;
        fwrite(STDERR, sprintf(
            "FAIL %s\n  expected %s\n  actual   %s\n",
            $label,
            var_export($expected, true),
            var_export($actual, true),
        ));
        return;
    }

    echo "ok  {$label}\n";
}

$scratch = sys_get_temp_dir() . '/phpstorm-launcher-test-' . bin2hex(random_bytes(4));
if (!mkdir($scratch, 0777, true)) {
    fwrite(STDERR, "unable to create $scratch\n");
    exit(1);
}

$binary = $scratch . '/phpstorm';
file_put_contents($binary, "#!/bin/sh\nexit 0\n");
chmod($binary, 0755);

$wrapper = $scratch . '/toolbox-phpstorm';
file_put_contents($wrapper, "#!/bin/sh\nopen -na PhpStorm.app \"$@\"\n");
chmod($wrapper, 0755);

$home = $scratch . '/home';
$macBinary = $home . '/Applications/PhpStorm.app/Contents/MacOS/phpstorm';
if (!mkdir(dirname($macBinary), 0777, true)) {
    fwrite(STDERR, "unable to create " . dirname($macBinary) . "\n");
    exit(1);
}
file_put_contents($macBinary, "#!/bin/sh\nexit 0\n");
chmod($macBinary, 0755);

expect('explicit executable path', PhpStormLauncher::resolve($binary, $home), $binary);
expect('explicit missing path', PhpStormLauncher::resolve($scratch . '/missing', $home), null);
expect('toolbox wrapper is not a launcher', PhpStormLauncher::isToolboxWrapper($wrapper), true);
expect(
    'toolbox scripts path is a wrapper',
    PhpStormLauncher::isToolboxWrapper('/Users/x/Library/Application Support/JetBrains/Toolbox/scripts/phpstorm'),
    true,
);
expect('plain script is not a toolbox wrapper', PhpStormLauncher::isToolboxWrapper($binary), false);
expect('explicit toolbox path is rejected', PhpStormLauncher::resolve($wrapper, $home), null);
expect('HOME app binary when override is empty', PhpStormLauncher::resolve(null, $home), $macBinary);
expect('HOME app binary when override is an unknown PATH name', PhpStormLauncher::resolve('phpstorm-not-on-path', $home), $macBinary);

$emptyHome = $scratch . '/empty-home';
mkdir($emptyHome, 0777, true);
expect('no app and no PATH override yields null', PhpStormLauncher::resolve('/no/such/phpstorm', $emptyHome), null);

foreach (new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($scratch, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST,
) as $file) {
    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
}
rmdir($scratch);

if ($failures > 0) {
    fwrite(STDERR, sprintf("%d failure(s)\n", $failures));
    exit(1);
}

echo "all ok\n";
