# Conformance

The compatibility and conformance tests in this subtree take their starting point from the official Python typing conformance suite in [python/typing — `conformance/`](https://github.com/python/typing/tree/main/conformance): shared fixtures across tools, grouping by specification topics, and inline markers for expected diagnostics (in this repository, PHP uses `// E`-style comments as described under [`tests/README.md`](tests/README.md)).

Upstream documentation for structure, naming, and marker conventions lives in that directory’s README. This PHP project adapts those ideas to PHP syntax, PHPDoc, and the analyzers wired in at the repository root.

One deliberate difference from upstream: Python's suite measures conformance to the typing specification, and PHP has no such specification beyond the runtime type declarations. Here the tests themselves are the target — each collects a feature that originated in a specific tool or in de-facto convention, states the expected behaviour inline, and the results record whether each tool implements the feature faithfully, degrades to a wider type, or reports the spelling as unknown. The root [`README.md`](../README.md) spells this position out.
