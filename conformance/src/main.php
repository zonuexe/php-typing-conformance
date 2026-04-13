<?php

declare(strict_types=1);

use Conformance\TestGroup\TestGroupLoader;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/TestGroup/TestGroup.php';
require_once __DIR__ . '/TestGroup/TestGroupLoader.php';

$rootDir = dirname(__DIR__);
$testGroupsFile = $rootDir . '/src/test-groups.toml';

$loader = new TestGroupLoader();
$testGroups = $loader->load($testGroupsFile);

printf("Loaded %d test groups\n", count($testGroups));

foreach ($testGroups as $group) {
    printf(
        "- %s [%s]: %s\n",
        $group->key,
        $group->sourceCategory,
        $group->name,
    );
}
