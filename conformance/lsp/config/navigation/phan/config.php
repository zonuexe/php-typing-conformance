<?php

// The Phan config the navigation corpus workspace carries. Only src and
// test are parsed: the corpus's own diagnostics are not the measurement,
// and polyfill-parsing its whole vendor tree would slow every session for
// nothing that navigation needs. In LSP mode Phan only analyses the files
// the session opens, so no exclude list is needed on top.
return [
    'target_php_version' => '8.5',
    'allow_polyfill_parser' => true,
    'directory_list' => ['src', 'test'],
    'exclude_analysis_directory_list' => [],
];
