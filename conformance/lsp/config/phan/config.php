<?php

// The Phan config the probe workspace carries. Matches the CLI column's
// flags (PhanChecker passes --allow-polyfill-parser and a target PHP
// version); the language server reads both from here instead.
return [
    'target_php_version' => '8.5',
    'allow_polyfill_parser' => true,
    'directory_list' => ['.'],
    'exclude_analysis_directory_list' => [],
];
