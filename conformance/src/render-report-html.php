<?php

declare(strict_types=1);

use Conformance\Reporting\Report;

require_once __DIR__ . '/../vendor/autoload.php';

printf("Generated summary report at %s\n", Report::fromRootDir(dirname(__DIR__))->write());
