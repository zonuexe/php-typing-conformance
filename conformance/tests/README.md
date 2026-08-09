# Conformance Tests

This directory will contain PHP compatibility fixtures grouped by the prefixes defined in `../src/test-groups.toml`.

Rules for test files:

- one focused topic per file,
- filename starts with a test-group prefix,
- expected diagnostics are marked inline with `// E`, `// E?`, `// E[tag]`, `// E?[tag]`, or `// E[tag+]` (`+` allows multiple hits in the group),
- quiet probes `// Q` / `// Q?` expect **silence** (success for suppress tags such as `@psalm-ignore-falsable-return`); a real diagnostic on a quiet line means the feature was not applied,
- a tagged group is **one** logical probe (OR of its lines): useful when tools disagree which line to blame,
- `// E?[tag]` is optional (silence is Pass); `// E[tag]` still requires at least one hit,
- `// E[noise]` / `// E?[noise]` marks incidental diagnostics that are allowed for Pass/Fail (e.g. "expression has no effect" on a Phan string annotation) but **do not count as enforcement probes** for `// T` type-handling / debug rows,
- for `// T` rows, diagnostics about **unknown analyzer helpers** (`Function PHPStan\… does not exist`, `Mago\inspect not found`, …) and no-op-expression lint also do **not** count as enforcement — they are non-support signals, not type inspection,
- the type spelling a test probes is marked with `// T` — a diagnostic on that line means the analyzer did not recognize the spelling, which is recorded as "not implemented" rather than as a failure,
- helper files should start with `_` and should not be treated as standalone tests.
