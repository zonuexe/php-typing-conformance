# Debug features and type-inspection helpers

Static analyzers do not only report errors. Each major PHP tool also ships a
**type-inspection** surface: a way to ask, at a specific point in a file,
“what type do you think this is?” or “assert that the type is X.” Those helpers
are not runtime PHP, and they are not a shared standard. They exist so a human
(or a fixture suite) can debug inference without guessing from secondary
symptoms.

This document compares those helpers as they appear in this suite’s **Debug
features** matrix: syntax shape, what the diagnostic looks like, how foreign
tools react, and how the harness scores the row.

The committed results live under `conformance/results/<tool>/debug_*.toml`.
The HTML report renders them as their own section (not as Pass/Fail soundness
rows).

## Why these are not ordinary conformance rows

A soundness test asks whether an analyzer rejects a real type error. A debug
helper does the opposite of “stay quiet when correct”:

- a **dump / inspect / trace** helper is *supposed* to emit a diagnostic even
  when the program is fine — the message *is* the answer;
- an **assertType-style** helper is silent when the expectation matches and
  noisy only when it does not.

Folding either shape into the soundness matrix would misread intentional noise
as failure, or intentional silence as a miss. The suite therefore tags these
cases with `@conformance-kind debug` and scores them with the same
**recognition / enforcement** vocabulary used for PHPDoc type spellings: does
the analyzer accept this helper, and does it emit a diagnostic that reveals
(or checks) the type?

## Three families

Despite the variety of names, every helper in the matrix falls into one of
three families.

| Family | What you write | Success looks like | Typical use |
| --- | --- | --- | --- |
| **Dump / inspect (function-style)** | A pseudo-function call on an expression | A diagnostic that prints the inferred type | Interactive debugging, playgrounds |
| **Trace (annotation-style)** | A PHPDoc tag (or Phan string annotation) naming a variable | A diagnostic that prints `$var: Type` (or equivalent) | Pin a variable after a narrow |
| **Fixture assert** | `assertType('expected', $expr)` (and siblings) | **Silence** when expected matches actual; failure only on mismatch | PHPStan’s own regression fixtures |

The first two both *show* a type. They differ in where that request lives in
the source and in what other tools do when they do not understand it. The
third *checks* a type against a string you wrote yourself.

## Function-style vs trace-style

This is the distinction that most confuses readers, so it is worth stating
twice: once as intent, once as observable behaviour.

### Intent

**Function-style** helpers look like ordinary PHP:

```php
\PHPStan\dumpType($value);
\Mago\inspect($value);
```

You pass an **expression**. The call site is the answer site. The analyzer
that owns the helper intercepts the call during analysis and turns it into a
diagnostic; it is not a real function that runs at runtime (unless a stub
exists, which production code should not rely on).

**Trace-style** helpers look like documentation (or, for Phan, a string
statement that only Phan treats as an annotation):

```php
/** @psalm-trace $value */
echo $value;

/** @trace $value */
echo $value;

'@phan-debug-var $value';
```

You name a **variable**, not an arbitrary expression. The tag does not change
control flow; it only asks the analyzer to publish that variable’s type at
that program point.

### What the diagnostic looks like

Function-style messages tend to talk about the **call** or the **dumped
type**:

| Tool | Helper | Example message (from this suite) |
| --- | --- | --- |
| PHPStan | `\PHPStan\dumpType($value)` | `Dumped type: int` |
| PHPStan | `\PHPStan\dumpPhpDocType($value)` | `Dumped type: int` (PHPDoc-facing spelling when it differs) |
| Mago | `\Mago\inspect($value)` | `Type information for arguments of \`Mago\inspect()\` call. [type-inspection]` |

Trace-style messages tend to talk about the **variable name**:

| Tool | Helper | Example message (from this suite) |
| --- | --- | --- |
| Psalm | `@psalm-trace $value` | `$value: int [Trace]` |
| Mago | `@psalm-trace` / `@trace` | `Trace: Type of \`$value\` is \`int\` [psalm-trace]` |
| mir | `@trace $value` | `Trace: Type of $value is int [MIR0221]` |
| Phan | `'@phan-debug-var $value';` | `@phan-debug-var requested for variable $value - it has union type int(real=int) [PhanDebugAnnotation]` |

So when you open an analyzer log and see `Dumped type: …`, you are in
function territory. When you see `$value: int` or `Trace: Type of $value is
…`, you are in trace territory. The information content is similar (a type
string at a point); the **framing** of the message tells you which surface
produced it.

### What foreign tools do

This is where the families diverge sharply in practice.

| | Own tool | Foreign tools |
| --- | --- | --- |
| **Function-style** | Emits a type dump / inspection note | Almost always report **undefined function** (or equivalent) on the same line |
| **Trace-style** | Emits a type trace | Usually **silent**: unknown PHPDoc tags are ignored; Phan’s string is a no-op expression |

That matters for reading the matrix:

- A foreign tool on a dump/inspect test still “fires” on the expected line,
  but the message is incidental (`Function PHPStan\dumpType does not exist`,
  `Function Mago\inspect not found`). Recognition of the *helper* is wrong;
  the line is only noisy because the name is unresolved.
- A foreign tool on a `@psalm-trace` / `@trace` test typically contributes
  **no** diagnostic at all. Silence means “I do not implement this tag,” not
  “the type is empty.”

Phan’s string form sits between the two: it is *syntactically* an expression
statement, but *semantically* a trace. Tools that do not know
`@phan-debug-var` usually leave the string alone (no unused-expression noise
in this suite’s configuration), so foreign rows look like silent
trace-style behaviour.

### What you can inspect

| | Function-style | Trace-style |
| --- | --- | --- |
| Target | Any **expression** (variable, call, property fetch, …) | A **variable name** written in the tag |
| Placement | Call expression in statement position | Docblock immediately before the statement of interest (Psalm / mir / Mago), or a bare string statement (Phan) |
| Line the diagnostic lands on | The call line (reliable) | Next statement (Psalm, mir), the **docblock** line (Mago), or the **enclosing** statement such as `if` (Phan) |

Mago’s preference is explicit in its own messaging: it understands
`@psalm-trace` for compatibility and steers authors toward
`\Mago\inspect()` for new code, because inspect works on expressions and
attributes cleanly to the call.

### `dumpType` vs `dumpPhpDocType`

Both are PHPStan function-style dumps. On a plain narrowed `int` they print
the same string (`Dumped type: int`). They diverge when the interesting
part of the type is **PHPDoc-only** (template expansions, conditional types,
aliases). Prefer:

- `dumpType` for “what did the engine resolve this to?”
- `dumpPhpDocType` for “how would this be written back as PHPDoc?”

## Fixture asserts (PHPStan Testing helpers)

A third family lives under `PHPStan\Testing\`:

| Helper | Question it answers |
| --- | --- |
| `assertType('T', $expr)` | Is the resolved type exactly (or equal to) the string `T`? |
| `assertSuperType('T', $expr)` | Is the actual type a subtype of `T`? |
| `assertNativeType('T', $expr)` | Same as `assertType`, but against the **native** type (typehints / inference without PHPDoc refinement) |
| `assertVariableCertainty(TrinaryLogic::…, $var)` | Is this variable definitely / maybe / never defined here? |

These are the building blocks of PHPStan’s own fixture tests. Behaviour:

1. **Match → silence.** A correct `assertType('int', $value)` produces no
   diagnostic from PHPStan.
2. **Mismatch → one diagnostic** that names expected and actual, for example
   `Expected type string, actual: int` or
   `Expected subtype of string, actual: int` or
   `Expected native type positive-int, actual: int` or
   `Expected variable $value certainty Maybe, actual: Yes`.
3. **Foreign tools** see undefined functions / unknown classes
   (`PHPStan\TrinaryLogic`), so both the “correct” and “wrong” lines may
   light up for the wrong reason.

In this suite, assert tests deliberately put `// E?` on **both** the matching
and the failing call. Native PHPStan therefore scores **partial** enforcement
(`1/2`): only the failing assert is expected to speak. That is correct for an
assert API; it is not a bug in the helper.

`assertNativeType` is the one that makes PHPDoc vs native visible. With
`@param positive-int $value` on an `int` parameter, `assertType` would see the
refined type while `assertNativeType('int', $value)` still holds and
`assertNativeType('positive-int', $value)` fails.

## Per-helper inventory

### PHPStan

| Surface | Suite test | Notes |
| --- | --- | --- |
| `\PHPStan\dumpType()` | `debug_phpstan_dump_type` | Function-style dump; level 0 |
| `\PHPStan\dumpPhpDocType()` | `debug_phpstan_dump_phpdoc_type` | PHPDoc-facing dump |
| `\PHPStan\Testing\assertType()` | `debug_phpstan_assert_type` | Silent on match |
| `\PHPStan\Testing\assertSuperType()` | `debug_phpstan_assert_super_type` | Subtype check |
| `\PHPStan\Testing\assertNativeType()` | `debug_phpstan_assert_native_type` | Ignores PHPDoc refinement |
| `\PHPStan\Testing\assertVariableCertainty()` | `debug_phpstan_assert_variable_certainty` | Needs `TrinaryLogic` |

### Psalm

| Surface | Suite test | Notes |
| --- | --- | --- |
| `@psalm-trace $var` | `debug_psalm_trace` | Diagnostic on the **next** statement; message `$var: Type [Trace]` |
| bare `@trace $var` | `debug_psalm_trace_short` | **Not** accepted by Psalm in this suite (empty output); Mago and mir do accept it |

Psalm does not ship a first-class `dumpType()`-style function. Trace is the
native inspection surface.

### Mago

| Surface | Suite test | Notes |
| --- | --- | --- |
| `\Mago\inspect($expr)` | `debug_mago_inspect` | Preferred function-style helper; any expression |
| `@psalm-trace` / `@trace` | `debug_psalm_trace`, `debug_psalm_trace_short`, `debug_mir_trace` | Compatibility; diagnostic often attributed to the **docblock** line, not the following statement |

### mir

| Surface | Suite test | Notes |
| --- | --- | --- |
| `@trace $var` (and Psalm-compatible forms mir accepts) | `debug_mir_trace`, `debug_psalm_trace_short` | Info-level issue **MIR0221**; requires `--show-info` |
| `@psalm-trace` specifically | `debug_psalm_trace` | In this suite’s pin, bare `@trace` fires more reliably than the `psalm-` prefix |

The harness enables `--show-info` **only** for test files whose name starts
with `debug_`. Elsewhere, info-level noise (including unused parameters) would
pollute ordinary soundness rows. See `MirChecker`.

### Phan

| Surface | Suite test | Notes |
| --- | --- | --- |
| `'@phan-debug-var $var';` | `debug_phan_debug_var` | Must be a **string expression statement**, not a comment — php-ast does not surface comment annotations for this feature |
| Line attribution | same | Often the enclosing `if` / statement, not only the string line |

### Tools without a native helper

Intelephense, NoVerify, phpy, pzoom, Steins, and Qodana (as measured here) do
not expose a comparable dump/trace/assert surface in this matrix. Their cells
show incidental behaviour only (undefined function, silence, or Not measured).

## Cross-tool summary

Very roughly, for “show me the type of this narrowed `int`”:

| | PHPStan | Psalm | Mago | mir | Phan |
| --- | --- | --- | --- | --- | --- |
| Function dump/inspect | `dumpType` / `dumpPhpDocType` | — | `Mago\inspect` | — | — |
| Trace annotation | — | `@psalm-trace` | `@psalm-trace` / `@trace` | `@trace` (MIR0221, info) | string `@phan-debug-var` |
| Fixture assert | `Testing\assert*` | — | — | — | — |
| Foreign reaction to functions | n/a | undefined function | undefined function | undefined function | undefined function |
| Foreign reaction to traces | silence | n/a | (implements) | (implements bare `@trace`) | silence / string no-op |

## How the suite measures them

1. **Group** `[debug]` in `conformance/src/test-groups.toml`.
2. **Kind** `@conformance-kind debug` in each test’s leading docblock — pulls
   the file out of the soundness table into the debug matrix.
3. **Markers**
   - `// T: <helper>` on the declaration (or the line under test) so unknown-
     dialect noise on that spelling is classified as recognition, not as an
     unexpected error.
   - `// E?` on lines that *may* report, because foreign tools and line
     attribution differ.
4. **Scoring** uses recognition / enforcement / enforced_lines, same as type-
   spelling rows. For asserts, partial enforcement on the native tool is
   expected (only the failing assert speaks).
5. **mir** special-case: `--show-info` only for `debug_*` file names.

Reading a debug cell:

- **Recognized + enforced** on the owning tool means the helper worked and
  printed (or failed an assert) as designed.
- **Enforced** on a foreign tool for a **function** helper is often
  *incidental* undefined-function noise — open the detail page and read the
  message.
- **none** on a foreign tool for a **trace** helper usually means the tag was
  ignored, not that the type was empty.

## Pitfalls when authoring or reading debug tests

1. **Do not put `// T` on a dump call that you also expect to “fail
   recognition.”** Put `// T` on the function (or the concept under test) and
   `// E?` on the dump/assert line. Marking the dump line as a type spelling
   confuses recognition with the dump diagnostic itself.

2. **mir `@trace` is brittle about layout.** Trailing `//` comments on the
   same line as the docblock, comments between `@trace` and the next
   statement, or a `*/` that terminates an earlier docblock early can make mir
   drop the annotation. Keep a clean `/** @trace $var */` immediately above
   the next statement.

3. **Psalm wants `@psalm-trace`; bare `@trace` is for Mago/mir.** A single
   file cannot mean the same thing to all three without tool-specific
   expectations. The suite splits `debug_psalm_trace` and
   `debug_psalm_trace_short` for that reason.

4. **Mago attributes trace diagnostics to the docblock line.** If the
   expectation sits only on the following `echo`, native Mago can look like
   `enforced_lines = 0/1` even though it printed a perfect Trace message one
   line up.

5. **Phan attributes `@phan-debug-var` to the enclosing statement.** Expect
   `// E?` on both the `if` and the string line.

6. **Assert success is silence.** Do not treat “PHPStan said nothing on the
   correct `assertType`” as a missing feature.

7. **Leave debug helpers out of production paths.** They are analyzer hooks.
   Shipping `\PHPStan\dumpType()` or a permanent `@psalm-trace` in library
   code either no-ops, fails CI on other tools, or confuses readers.

## Related documents

- [`analyzer-adapters.md`](analyzer-adapters.md) — how each CLI is invoked and
  how diagnostics are normalized (including mir’s `--show-info` gate).
- [`language-servers.md`](language-servers.md) — a different measurement:
  hover type conformance over LSP, not dump/trace helpers.
- Report section **Debug features — type-inspection helpers** in the generated
  HTML index (from `@conformance-kind debug` tests).
- Test sources: `conformance/tests/debug_*.php`.
