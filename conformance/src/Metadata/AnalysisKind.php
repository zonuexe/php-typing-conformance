<?php

declare(strict_types=1);

namespace Conformance\Metadata;

/**
 * What an analyzer is aiming at.
 *
 * The one axis whose vocabulary is coined here rather than read off the
 * project, because no project describes itself this way: every tool in the
 * table calls itself a static analyzer, which is the entry criterion, not a
 * distinction.
 */
enum AnalysisKind: string
{
    /** Aims at type correctness. */
    case TypeChecker = 'Type checker';

    /**
     * Aims at a rule catalogue, powered by the tool's own (usually
     * simpler) type inference.
     */
    case InferringLinter = 'Inferring linter';

    /** Infers types mainly to drive completion and navigation. */
    case CodeIntelligence = 'Code intelligence';

    public function label(): string
    {
        return $this->value;
    }
}
