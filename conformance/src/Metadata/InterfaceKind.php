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

    public function label(): string
    {
        return $this->value;
    }
}
