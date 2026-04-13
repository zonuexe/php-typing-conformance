# Conformance

The compatibility and conformance tests in this subtree take their starting point from the official Python typing conformance suite in [python/typing — `conformance/`](https://github.com/python/typing/tree/main/conformance): shared fixtures across tools, grouping by specification topics, and inline markers for expected diagnostics (in this repository, PHP uses `// E`-style comments as described under [`tests/README.md`](tests/README.md)).

Upstream documentation for structure, naming, and marker conventions lives in that directory’s README. This PHP project adapts those ideas to PHP syntax, PHPDoc, and the analyzers wired in at the repository root.
