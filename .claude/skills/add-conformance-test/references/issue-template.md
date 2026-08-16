# Cross-analyzer issue: structure and worked example

The issue exists to collect information the measurement cannot produce on its
own — prior art, vendor positions, whether the semantics are even agreed. So
each section has a job, and a section that is not doing its job is worth
cutting rather than padding.

## Formatting

GitHub renders a single newline inside a paragraph as a `<br>`, so the body
must not be hard-wrapped: one paragraph is one line, one list item is one line,
however long they get. Only fenced code blocks keep their own line breaks. The
worked example below is stored unwrapped for that reason — it is a verbatim
artifact and can be copied as-is, unlike the wrapped prose around it.

## Structure

### Summary

Three things, in about five lines: what the spelling or feature means in plain
words, that nothing measured implements it (or which subset does), and a link
to the upstream report that started it. Say the number of columns measured —
it is what makes the claim checkable.

### Example

The idiom in the form a reader would actually write, **not** the test file's
expanded form. The test splits one variable into three to keep the probes
independent; that serves the measurement and would only distract a reader here.
Comment each line with what should happen, not with what the tools do.

### Expected semantics

Numbered claims, each one independently testable. Two things make this section
carry weight:

- Say what the feature is *not*, not only what it is. "It is about definedness
  of the variable, not a value: not `null`, not `void`, not `never`" closes off
  the misreadings a reader would otherwise supply.
- Include the weaker reading you would also accept. Asking for one specific
  implementation invites a "we do it differently" reply and ends the
  conversation; naming the range of honest readings, and then naming the one
  reading that is plainly wrong, keeps it open.

### Current state

Group the columns **by mechanism**, with a real quoted diagnostic under each
group. A per-tool table looks thorough and conveys less: four tools emitting
the same class-not-found in four dialects is one finding, not four.

Call out explicitly any column whose reaction the `// V` control proved
unrelated to the feature. That is the part a reader cannot reconstruct from the
matrix, and leaving it out lets a tool take credit it has not earned.

State the negative result too: "no tool keeps the guarded reads quiet *and*
flags the unguarded ones" is the finding, and it needs saying out loud.

### Open questions

What the measurement cannot answer. Prior art and documentation; whether the
feature should be accepted in every position or only one; how it relates to
diagnostics the tools already have without a spelling for it; whether the
spelling is right at all. Close by inviting pointers and vendor positions.

## Worked example

Filed as https://github.com/zonuexe/php-typing-conformance/issues/7.

---

**Title:** The `unset` pseudo-type

## Summary

`@var Foo|unset` is a community idiom for template-like top-level scripts (Blade views, plain `include`d partials). It means *"`$var` is a `Foo`, **or it is not defined at all**"*.

Nothing measured in this project understands it. Most analyzers resolve `unset` as a class name in the current namespace and report an unknown class; the rest react to something other than the spelling. Not one of the 15 analyzer configurations measured here models the possibly-undefined state.

Reported for PHPantom in PHPantom-dev/phpantom_lsp#366. This issue collects the cross-tool picture and the semantics we would like to measure against.

## Example

```php
<?php

/** @var \DateTime|unset $datetime */
echo $datetime->format('Y-m-d');      // unsafe: $datetime may be undefined
echo date_format($datetime, 'Y-m-d'); // unsafe for the same reason

if (isset($datetime)) {
    echo $datetime->format('Y-m-d');      // safe
    echo date_format($datetime, 'Y-m-d'); // safe
}
```

## Expected semantics

1. `unset` is not a class. It must never be resolved as `\Current\Namespace\unset`, and it must not produce an "unknown class" diagnostic.
2. `T|unset` is about *definedness of the variable*, not about a value. It is not `null`, not `void`, not `never`, and not `mixed`.
3. Reading the variable without a guard is unsafe, so a diagnostic there is correct — for example "possibly undefined variable", or an argument-type error naming the undefined state.
4. `isset($var)` (and `empty()`, `??`, `??=`) removes the undefined state. Inside the guard there must be no diagnostic, and **the guard itself must not be reported as redundant** — a variable that may be undefined is exactly what makes `isset()` meaningful.
5. A weaker but still honest reading is to treat `T|unset` as `T|null`: the unguarded reads stay unsafe and the guard stays meaningful. Resolving `unset` as a class name is the only reading that is plainly wrong.

## Current state

Measured with `conformance/tests/regressions_unset_pseudo_type.php`, which gives each probe its own variable so that one read cannot narrow the next, and adds a control that repeats the same reads under a plain `@var \DateTime` with no `unset` in the union.

**Resolve `unset` as a class name in the current namespace** — PHPStan (`class.notFound`), Psalm and pzoom (`UndefinedDocblockClass`), Mago (`non-existent-class-like`), PHPantom (`unknown_class`), Qodana (`PhpUndefinedClassInspection`), php.py (`Use of unknown class`), NoVerify (`undefinedClass`):

```
PHPDoc tag @var for variable $read contains unknown class
  Conformance\Tests\RegressionsUnsetPseudoType\unset.
```

**Keeps the spelling, rejects the union as a non-object** — Intelephense is the only tool that does not invent a class:

```
Expected type 'object'. Found 'DateTime|unset'. [P1006]
```

**Reacts to the variable, not to the spelling** — Phan (`PhanUndeclaredGlobalVariable`) and Phpactor (`Undefined variable`). The control proves it: both produce the same diagnostic for `/** @var \DateTime $defined */` with no `unset` anywhere in the union, so their output says nothing about this feature.

**Silent** — mir, Steins.

**The `isset()` guard is reported as redundant** by PHPStan, Psalm/pzoom and Mago, which is the direct consequence of reading `Foo|unset` as a union of two class names — such a variable is always defined and never null:

```
Variable $guarded in isset() always exists and is not nullable.  (PHPStan)
Docblock-defined type ...\unset|DateTime for $guarded is never null  (Psalm)
This condition (type `true`) will always evaluate to true.  (Mago)
```

No tool keeps the guarded reads quiet *and* flags the unguarded ones, which is what the idiom asks for.

## Open questions

- Is there prior art or documentation for this spelling? (phpDocumentor, PhpStorm, Laravel IDE helper, or a specific framework's convention?)
- Should `unset` be accepted only in `@var` at the top level of a script, or also in `@param` / `@return` / `@var` on properties, where "undefined" means something different?
- How does it relate to the diagnostics tools already have without a type spelling for it — Psalm's `PossiblyUndefinedVariable`, PHPStan's `variable.undefined`?
- Is `unset` the right spelling at all, versus something explicitly vendor-prefixed?

Pointers, prior art, and tool-side positions are all welcome here.
