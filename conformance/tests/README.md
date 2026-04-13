# Conformance Tests

This directory will contain PHP compatibility fixtures grouped by the prefixes defined in `../src/test-groups.toml`.

Rules for test files:

- one focused topic per file,
- filename starts with a test-group prefix,
- expected diagnostics are marked inline with `// E`, `// E?`, `// E[tag]`, or `// E[tag+]`,
- helper files should start with `_` and should not be treated as standalone tests.
