<?php

declare(strict_types=1);

namespace Conformance\Metadata;

/**
 * How an analyzer is driven.
 *
 * Independent of AnalysisKind: a tool can ship both, and several do.
 */
enum InterfaceKind: string
{
    case Cli = 'CLI';
    case Lsp = 'LSP';

    /**
     * Driven from inside an editor that is not speaking a protocol to a
     * separate process. Qodana's analysis is the IDE's own inspection engine,
     * reached by running Inspect Code in PhpStorm; it has a CLI too, but that
     * one is licensed separately and is not what this suite measures.
     */
    case Ide = 'IDE';

    public function label(): string
    {
        return $this->value;
    }
}
