# Conformance Tests

This directory will contain PHP compatibility fixtures grouped by the prefixes defined in `../src/test-groups.toml`.

Rules for test files:

- one focused topic per file,
- filename starts with a test-group prefix,
- expected diagnostics are marked inline with `// E`, `// E?`, `// E[tag]`, or `// E[tag+]`,
- the type spelling a test probes is marked with `// T` — a diagnostic on that line means the analyzer did not recognize the spelling, which is recorded as "not implemented" rather than as a failure,
- helper files should start with `_` and should not be treated as standalone tests.
