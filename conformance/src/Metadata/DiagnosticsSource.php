<?php

declare(strict_types=1);

namespace Conformance\Metadata;

/**
 * Where a language server's diagnostics come from.
 *
 * The axis that separates the language servers from each other, and exactly
 * the axis the analyzer table cannot express.
 */
enum DiagnosticsSource: string
{
    /** The server shells out to third-party analyzers and republishes their diagnostics over LSP. */
    case Adapter = 'Adapter';

    /** The server infers and diagnoses by itself. */
    case OwnEngine = 'Own engine';

    /**
     * Both, which is what Phpactor and PHPantom each claim. Neither is honestly
     * one or the other: Phpactor ships its own Worse Reflection inference plus
     * eleven built-in diagnostic providers *and* opt-in extension packages that
     * run PHPStan or Psalm; PHPantom ships its own type engine *and*
     * auto-detects vendor/bin PHPStan, PHPCS and Mago to fold their output in.
     * Collapsing either to a single value would misreport the project's own
     * description.
     */
    case OwnEngineAndAdapter = 'Own engine + adapter';

    public function label(): string
    {
        return $this->value;
    }
}
