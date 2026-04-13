# Architecture Design

## Purpose

This repository is intended to provide a conformance-style test suite and report generator for PHP static analysis tools, inspired by `python/typing`.

The target outcome is a system that can:

- define language-typing and PHPDoc-typing test cases,
- run multiple analyzers against the same corpus,
- normalize diagnostics into a common format,
- store per-tool results in versioned files,
- publish a report that compares tool behavior.

## Architectural Principles

### 1. Separate references, execution, and results

The repository should distinguish clearly between:

- reference material used to define expected behavior,
- executable conformance infrastructure,
- generated compatibility results.

That separation prevents the project from collapsing documentation, fixtures, and tool output into one directory tree.

### 2. Keep tool installations isolated

Static analyzers often have conflicting dependencies and different bootstrap requirements. Tool-specific installations should stay isolated from the conformance runner and from each other.

### 3. Keep expectations close to test code

Expected diagnostics should live in the test files themselves using simple comment markers. This makes test intent readable and reduces synchronization problems between fixtures and manifests.

### 4. Treat manual review as part of the model

Automated line-based checking is useful, but not sufficient. The architecture should support both:

- automated expectation matching,
- manual conformance classification with notes.

## Reference Inputs

The project needs external materials that define or justify expected behavior. Those materials live under `references/`.

The current `references/` subtree already serves multiple roles:

- `references/python-typing/`: full reference implementation that inspired this repository's architecture.
- `references/fig-standards/`: FIG and PSR standards material.
- `references/mago/`: Mago source tree and documentation.
- `references/phpDocumentor/`: phpDocumentor source and documentation.
- `references/phpstan/`: PHPStan source tree and website documentation.
- `references/psalm/`: Psalm source tree and documentation.
- `references/phan.wiki/`: Phan wiki documentation.
- `references/noverify/`: NoVerify source tree and documentation.

This is the correct direction for the repository. PHP typing behavior is defined by multiple authorities, and the conformance suite will need both normative references and tool-specific behavioral references.

The architecture should therefore treat `references/` as a structured reference layer with at least three categories:

- architectural reference implementations,
- standards and conventions,
- documentation for type syntax and PHPDoc semantics,
- analyzer documentation used to explain supported or unsupported behavior.

## Current Repository Layers

At the moment, the repository has three concrete layers:

### 1. Root project metadata

- `composer.json`
- `composer.lock`
- `README.md`
- `LICENSE`

This layer defines the repository itself and the Composer Bin Plugin setup used for tool isolation.

### 2. Tool environments

- `vendor-bin/phpstan/`
- `vendor-bin/psalm/`
- `vendor-bin/phan/`
- `vendor-bin/mago/`
- `vendor-bin/noverify/`

Each environment is an isolated Composer installation for one analyzer.

### 3. Documentation and references

- `docs/design-doc.md`
- `AGENTS.md`
- `references/fig-standards/`
- `references/python-typing/`
- `references/phpDocumentor/`
- `references/mago/`
- `references/phpstan/`
- `references/psalm/`
- `references/phan.wiki/`
- `references/noverify/`

This layer explains the system and stores normative material that future tests will cite.

## Target Runtime Architecture

The missing piece is the actual conformance subsystem. The intended structure should look like this:

```text
.
├── docs/
│   └── design-doc.md
├── references/
│   ├── python-typing/
│   ├── fig-standards/
│   ├── mago/
│   ├── phpDocumentor/
│   ├── phpstan/
│   ├── psalm/
│   ├── phan.wiki/
│   └── noverify/
├── conformance/
│   ├── tests/
│   ├── fixtures/
│   ├── src/
│   │   ├── main.php
│   │   ├── Checker/
│   │   ├── Expectation/
│   │   ├── Discovery/
│   │   ├── Result/
│   │   └── Reporting/
│   ├── templates/
│   ├── results/
│   │   ├── phpstan/
│   │   ├── psalm/
│   │   ├── phan/
│   │   ├── mago/
│   │   └── noverify/
│   └── scripts/
└── vendor-bin/
```

## Responsibilities by Subsystem

### `references/`

Stores external standards and source material. This subtree is read-mostly and should not contain generated artifacts.

Within this layer, different directories serve different purposes:

- architectural references, such as `python-typing`,
- standards references, such as FIG / PSR material,
- PHPDoc and documentation references, such as phpDocumentor,
- analyzer references, such as Mago, PHPStan, Psalm, Phan, and NoVerify docs.

The conformance suite should cite these sources explicitly rather than treating all tests as if they came from one unified specification.

Unlike the documentation-oriented reference repositories, `references/python-typing/` is intentionally kept as a fuller checkout because it is useful to inspect its repository structure, runner code, tests, and reporting pipeline together.

### `vendor-bin/`

Stores isolated tool installations. This subtree is operational infrastructure, not product code.

### `conformance/tests/`

Stores test programs. Each file should:

- represent one focused typing topic,
- use a stable filename prefix derived from a test group,
- contain inline expectation markers such as `// E` or `// E?`,
- cite the reference material that justifies the expectation.

### `conformance/fixtures/`

Stores shared bootstrap files, stubs, autoload helpers, or supporting source needed by tests.

### `conformance/src/Checker/`

Contains one adapter per analyzer. Each adapter should handle:

- installation checks,
- version discovery,
- invocation,
- output parsing into a shared diagnostic model.

### `conformance/src/Expectation/`

Parses inline expectation markers from test files and converts them into a tool-agnostic expected-error model.

### `conformance/src/Discovery/`

Discovers tests by filename convention and group metadata.

### `conformance/src/Result/`

Reads and writes per-tool result files. Result storage should preserve:

- raw normalized output,
- automated diff against expectations,
- manual conformance classification,
- notes,
- ignored diagnostics where automation is intentionally relaxed.

### `conformance/src/Reporting/`

Builds the summary report from stored results rather than from live analyzer output.

### `conformance/results/`

Stores one result file per checker per test, plus checker version metadata and generated aggregate reports.

## Shared Data Model

The system should standardize on a minimal cross-tool model.

### Test groups

Each group should define:

- stable key used as filename prefix,
- human-readable name,
- reference URL or document path,
- source category such as `language`, `fig`, `phpdoc`, or `ecosystem`.

### Expected diagnostics

The initial model only needs:

- required errors on a line,
- optional errors on a line,
- grouped alternatives for tools that report on one of several lines.

### Parsed diagnostics

For a first version, the common diagnostic shape can remain simple:

```text
line number -> list of messages
```

Column numbers, severity, and tool-specific error codes can be preserved later if they materially improve matching or reporting.

### Result records

Per-test result files should include fields equivalent to:

- `output`
- `errors_diff`
- `conformance_automated`
- `conformant`
- `notes`
- `ignore_errors`

Each checker should also have a `version.toml` or equivalent metadata file.

## Reporting Model

The first useful report is a matrix:

- rows grouped by test topic,
- columns for analyzers and their versions,
- cells containing `Pass`, `Partial`, `Unsupported`, `Fail`, or `Unknown`,
- optional notes shown inline or by hover/detail UI.

This report should be generated from stored result files so that:

- report generation is reproducible,
- result changes are reviewable in git,
- CI can rebuild reports without rerunning all analyzers.

## Proposed PHP Compatibility Test Plan

The most useful idea to copy from `references/python-typing/` is not any specific Python feature. It is the way the suite is decomposed into topic-prefixed test files backed by explicit group metadata.

The PHP version should adopt the same pattern:

- one stable prefix per test group,
- one focused topic per test file,
- one or more inline `// E` expectations per fixture,
- one reference source attached to each group or test.

### Proposed test groups

The following group set is a reasonable PHP translation of the `python-typing` structure:

- `native_types`: scalar types, `mixed`, `void`, `never`, `null`, nullable types.
- `unions`: union types and redundancy / compatibility checks.
- `intersections`: intersection types and object-only constraints.
- `callables`: callable signatures, closures, first-class callables, callback PHPDoc forms.
- `generics`: template types in PHPDoc, template bounds, template inheritance, generic collections.
- `arrays`: array shapes, list semantics, key/value compatibility, non-empty arrays.
- `objects`: object type compatibility, inheritance, interface implementation, `static`, late static return.
- `properties`: typed properties, readonly behavior, promoted properties, initialization rules.
- `constants`: class constants, enum cases, constant value typing.
- `enums`: backed enums, pure enums, enum case usage, enum method typing.
- `exceptions`: throwable contracts, `@throws`, exception flow assumptions where tools model them.
- `phpdoc_basics`: `@param`, `@return`, `@var`, `@throws`, inline `@var`, docblock/native consistency.
- `phpdoc_advanced`: conditional types, utility types, integer ranges, literal strings, key-of / value-of.
- `assertions`: `assert`, tool-specific assertion tags, narrowing via guard methods or helper functions.
- `directives`: suppressions, ignore comments, baseline interactions, analyzer directives that affect diagnostics.
- `stubs`: behavior when external stubs or vendor signatures override source information.
- `psr`: compatibility expectations derived from FIG or PSR documents.
- `historical`: deprecated doc syntaxes, legacy annotations, compatibility behavior kept for ecosystem reasons.

This group list is intentionally broader than PHP's native language grammar. PHP static analysis behavior is split across the language itself, PHPDoc conventions, and analyzer-defined type systems.

### Mapping from the Python reference

Several Python groups map directly to PHP-style concerns:

- `generics` -> PHPDoc templates and generic inheritance.
- `callables` -> callable signatures and closure typing.
- `aliases` -> PHPDoc type aliases and imported aliases.
- `literals` -> literal scalars, literal-string-like features, constant-value types.
- `enums` -> native PHP enums plus tool inference around cases and backed values.
- `directives` -> ignores, suppressions, baselines, and analyzer hints.
- `historical` -> legacy annotations that tools still parse for compatibility.

Other Python groups do not map directly and should be translated rather than copied:

- `typeddicts` becomes array-shape and keyed-array tests.
- `protocols` becomes a mix of interfaces, structural assumptions, and analyzer-specific object-shape behavior.
- `dataclasses` is mostly irrelevant for PHP and should instead become property-promotion, readonly, and constructor-contract tests.
- `narrowing` becomes assertion-driven narrowing, `instanceof`, null checks, enum checks, and guard helpers.

### Test file design rules

Each test file should follow rules close to the Python suite:

- filename starts with the group prefix, such as `generics_template_bounds.php`,
- the file contains one coherent subtopic,
- comments explain why an error is expected,
- comments cite the relevant reference when possible,
- helper files that support a test should use a leading underscore and not be treated as standalone cases.

Recommended expectation syntax for PHP fixtures:

```php
<?php

/** @param string $value */
function takesString(string $value): void {}

takesString(1); // E: int is not accepted by string parameter
```

Grouped or optional expectations should mirror the Python design:

- `// E`
- `// E?`
- `// E[tag]`
- `// E[tag+]`

### Priority test matrix for the first milestone

The initial PHP suite should not try to cover every advanced feature. It should start with high-signal compatibility points that most analyzers claim to support.

Recommended first-wave tests:

1. `native_types_*`
   - scalar parameter and return compatibility
   - nullable arguments and returns
   - `mixed`, `void`, and `never`
2. `unions_*`
   - union acceptance and rejection
   - duplicate or redundant members
   - nullable shorthand vs explicit union
3. `objects_*`
   - inheritance and interface substitutability
   - `static` return types
   - parent/child parameter variance where tools disagree
4. `phpdoc_basics_*`
   - native type vs docblock mismatch
   - parameter / return doc parsing
   - inline `@var`
5. `arrays_*`
   - homogeneous arrays
   - list-style arrays
   - array shapes in PHPDoc
6. `generics_*`
   - basic templates
   - template constraints
   - generic collections through inheritance
7. `directives_*`
   - ignore comments
   - baseline or suppression behavior where relevant

This first wave is enough to expose meaningful differences between PHPStan, Psalm, Phan, NoVerify, and Mago without immediately getting trapped in niche extensions.

### Second-wave tests

After the first milestone, add tests that are known to produce tool divergence:

- conditional and dependent PHPDoc types,
- integer ranges and refined scalar subtypes,
- literal-string and taint-adjacent string categories,
- key-of / value-of and constant-derived types,
- object shapes or structural object typing,
- stub precedence and vendor override behavior,
- enum exhaustiveness assumptions,
- analyzer assertion annotations.

These are especially valuable because they show where tools are extending PHP beyond the core language.

### Metadata needed for each group

Like `references/python-typing/conformance/src/test_groups.toml`, the PHP suite should define group metadata in one file. Each group entry should include:

- `name`
- `source_category`
- `references`
- `description`

For example:

```toml
[generics]
name = "PHPDoc generics"
source_category = "phpdoc"
references = [
  "references/phpDocumentor/docs/references/phpdoc",
  "references/phpstan/website/src",
  "references/psalm/docs/annotating_code/type_syntax",
]
```

This is more explicit than the Python version because PHP requires multiple source authorities.

### Result interpretation policy

The Python suite assumes one official spec and asks whether a checker conforms to it. The PHP suite should be slightly more precise.

Each test result should be interpreted against one of these policies:

- `normative`: expected by the language or an accepted standard,
- `conventional`: expected by widely used PHPDoc or ecosystem practice,
- `extension`: documented tool extension being compared, not required for all tools.

This prevents the report from unfairly treating every unsupported analyzer extension as a conformance failure.

## Differences from the Python Reference

The Python repository provides the structural template, but PHP needs a stricter separation of source categories because there is no single canonical typing specification.

In practice, each PHP test should be traceable to one of:

- core language semantics,
- FIG or PSR standards,
- phpDocumentor-style documentation conventions,
- analyzer-documented behavior that has become ecosystem convention,
- analyzer-specific extensions being compared explicitly.

This distinction should appear in test metadata or group metadata. Otherwise the project risks presenting incomparable behaviors as one flat notion of conformance.

## Implementation Strategy

The first milestone should stay small:

1. create the `conformance/` subtree,
2. define a handful of test groups,
3. implement expectation parsing,
4. implement adapters for `phpstan` and `psalm`,
5. persist per-test result files,
6. generate one HTML summary report.

After that foundation is stable, add more analyzers and broaden coverage to PHPDoc-heavy or ecosystem-specific type features.

## Summary

The architecture of this repository should remain simple and layered:

- `references/` for external normative material,
- `vendor-bin/` for isolated analyzer installations,
- `conformance/` for executable test infrastructure and generated results,
- `docs/` for design and project-level documentation.

That separation is the main architectural decision. Everything else follows from it.
