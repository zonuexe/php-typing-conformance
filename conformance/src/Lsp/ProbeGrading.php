<?php

declare(strict_types=1);

namespace Conformance\Lsp;

/**
 * Turns one server's raw probe output into the graded shape the result TOML
 * stores: an advertised/probed row per capability column, a verdict per
 * hover case, and the push-diagnostics observation.
 *
 * The three-way split mirrors the suite's recognition/enforcement
 * distinction. "Advertised" is the handshake's claim; the probe verdict says
 * whether the claim answers when called; the hover verdicts grade what the
 * answer contains. Each layer is recorded even when a lower one already
 * failed, so a server that answers a method it never advertised would show
 * up as exactly that oddity rather than be normalized away.
 */
final class ProbeGrading
{
    /**
     * Report column => [ServerCapabilities field, LSP method, probe id].
     * Method and probe id are null for columns recorded from the handshake
     * alone. push-diagnostics is the one column with no capability field:
     * the protocol has no flag for the push model, so only behaviour talks.
     *
     * @var array<string, array{string|null, string|null, string|null}>
     */
    public const COLUMNS = [
        'push-diagnostics' => [null, null, null],
        'pull-diagnostics' => ['diagnosticProvider', 'textDocument/diagnostic', 'pull-diagnostics'],
        'hover' => ['hoverProvider', 'textDocument/hover', 'hover'],
        'completion' => ['completionProvider', 'textDocument/completion', 'completion'],
        'signature-help' => ['signatureHelpProvider', 'textDocument/signatureHelp', 'signature-help'],
        'definition' => ['definitionProvider', 'textDocument/definition', 'definition'],
        'declaration' => ['declarationProvider', 'textDocument/declaration', null],
        'type-definition' => ['typeDefinitionProvider', 'textDocument/typeDefinition', 'type-definition'],
        'implementation' => ['implementationProvider', 'textDocument/implementation', 'implementation'],
        'references' => ['referencesProvider', 'textDocument/references', 'references'],
        'document-highlight' => ['documentHighlightProvider', 'textDocument/documentHighlight', 'document-highlight'],
        'document-symbol' => ['documentSymbolProvider', 'textDocument/documentSymbol', 'document-symbol'],
        'workspace-symbol' => ['workspaceSymbolProvider', 'workspace/symbol', 'workspace-symbol'],
        'code-action' => ['codeActionProvider', 'textDocument/codeAction', 'code-action'],
        'code-lens' => ['codeLensProvider', 'textDocument/codeLens', null],
        'rename' => ['renameProvider', 'textDocument/rename', 'rename'],
        'formatting' => ['documentFormattingProvider', 'textDocument/formatting', 'formatting'],
        'range-formatting' => ['documentRangeFormattingProvider', 'textDocument/rangeFormatting', null],
        'folding-range' => ['foldingRangeProvider', 'textDocument/foldingRange', 'folding-range'],
        'selection-range' => ['selectionRangeProvider', 'textDocument/selectionRange', 'selection-range'],
        'semantic-tokens' => ['semanticTokensProvider', 'textDocument/semanticTokens/full', 'semantic-tokens'],
        'inlay-hint' => ['inlayHintProvider', 'textDocument/inlayHint', 'inlay-hint'],
        'call-hierarchy' => ['callHierarchyProvider', 'textDocument/prepareCallHierarchy', 'call-hierarchy'],
        'type-hierarchy' => ['typeHierarchyProvider', 'textDocument/prepareTypeHierarchy', 'type-hierarchy'],
    ];

    /**
     * @param array<string, mixed> $clientOutput
     * @return array<string, array<string, mixed>> column => row
     */
    public function capabilities(array $clientOutput): array
    {
        /** @var array<string, mixed> $advertised */
        $advertised = $clientOutput['capabilities'] ?? [];
        /** @var list<string> $dynamic */
        $dynamic = $clientOutput['dynamicRegistrations'] ?? [];
        $probesById = [];
        foreach ($clientOutput['probes'] ?? [] as $probe) {
            $probesById[(string) $probe['id']] = $probe;
        }

        $rows = [];
        foreach (self::COLUMNS as $column => [$field, $method, $probeId]) {
            $via = 'none';
            if ($field !== null && ($advertised[$field] ?? null) !== null && ($advertised[$field] ?? null) !== false) {
                $via = 'static';
            } elseif ($method !== null && in_array($method, $dynamic, true)) {
                $via = 'dynamic';
            }

            $row = ['advertised' => $via !== 'none', 'via' => $via];

            if ($column === 'push-diagnostics') {
                // No flag exists to advertise this; observed behaviour is the
                // whole cell. The deliberate error sits in capabilities.php.
                $published = $clientOutput['diagnostics'] ?? [];
                $row['advertised'] = false;
                $row['via'] = 'n/a';
                $row['probe'] = ($published['capabilities.php'] ?? []) === [] ? 'empty' : 'answered';
            } elseif ($probeId === null) {
                $row['probe'] = 'not-probed';
            } elseif (!isset($probesById[$probeId])) {
                $row['probe'] = 'not-probed';
            } else {
                $row = [...$row, ...$this->probeVerdict($probesById[$probeId])];
            }

            $rows[$column] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $probe one entry of the client's probes array
     * @return array<string, mixed> probe verdict fields for the row
     */
    private function probeVerdict(array $probe): array
    {
        if (($probe['skipped'] ?? false) === true) {
            return ['probe' => 'skipped'];
        }
        if (($probe['error'] ?? null) === 'timeout') {
            return ['probe' => 'timeout'];
        }
        if (($probe['ok'] ?? false) !== true) {
            $error = (string) ($probe['error'] ?? '');

            // The second way a server gates a paid feature: advertise it,
            // then refuse the call by licence (DEVSENSE's -32803 "Feature
            // Rename Refactoring is not licensed"). Intelephense gates the
            // other way, by omitting the capability from the handshake, so
            // without this verdict the same commercial fact would render as
            // "Error" for one server and a dash for the other.
            if (preg_match('/licen[cs]ed?/i', $error) === 1) {
                return ['probe' => 'gated', 'note' => $error];
            }

            return ['probe' => 'error', 'note' => $error];
        }

        return [
            'probe' => $this->isEmpty((string) $probe['method'], $probe['result']) ? 'empty' : 'answered',
        ];
    }

    private function isEmpty(string $method, mixed $result): bool
    {
        if ($result === null || $result === []) {
            // One honest exception: formatting may answer "no edits needed",
            // which for an already-formatted fixture is a working answer.
            return $method !== 'textDocument/formatting';
        }
        if (!is_array($result)) {
            return false;
        }

        return match ($method) {
            'textDocument/hover' => $this->hoverText($result) === '',
            'textDocument/completion' => ($result['items'] ?? $result) === [],
            'textDocument/signatureHelp' => ($result['signatures'] ?? []) === [],
            'textDocument/rename' => ($result['changes'] ?? []) === [] && ($result['documentChanges'] ?? []) === [],
            'textDocument/semanticTokens/full' => ($result['data'] ?? []) === [],
            // A full document report for the fixture with the deliberate
            // type error should carry at least one item.
            'textDocument/diagnostic' => ($result['items'] ?? []) === [],
            default => false,
        };
    }

    /**
     * @param array<string, mixed> $clientOutput
     * @param list<array<string, mixed>> $hoverProbes the definitions, with grading patterns
     * @return array<string, array<string, mixed>> hover case id => graded row
     */
    public function hoverConformance(array $clientOutput, array $hoverProbes): array
    {
        /** @var array<string, mixed> $advertised */
        $advertised = $clientOutput['capabilities'] ?? [];
        $hoverAdvertised = ($advertised['hoverProvider'] ?? null) !== null && ($advertised['hoverProvider'] ?? null) !== false;
        $probesById = [];
        foreach ($clientOutput['probes'] ?? [] as $probe) {
            $probesById[(string) $probe['id']] = $probe;
        }

        $rows = [];
        foreach ($hoverProbes as $definition) {
            $id = (string) $definition['id'];
            $caseId = substr($id, strlen('hover:'));
            $row = [
                'feature' => (string) ($definition['feature'] ?? $caseId),
                'expected' => (string) ($definition['expected'] ?? ''),
            ];

            $probe = $probesById[$id] ?? null;
            if (!$hoverAdvertised || $probe === null || ($probe['skipped'] ?? false) === true) {
                $row['verdict'] = 'not-advertised';
                $row['shown'] = '';
            } else {
                $shown = is_array($probe['result']) ? $this->hoverText($probe['result']) : '';
                $row['shown'] = $shown;
                $row['verdict'] = $shown === '' ? 'none' : $this->grade($shown, $definition);
            }

            $rows[$caseId] = $row;
        }

        return $rows;
    }

    /**
     * Framework-specific probes (env/route/view/translation). Graded like
     * capability probes, with hover text kept when the method is hover.
     * The push-diagnostics row looks at the published stream for that
     * file rather than at a request, and when the definition has a line
     * it keeps only the diagnostic that starts there so several missing
     * keys in one file do not all report the first message.
     *
     * @param array<string, mixed> $clientOutput
     * @param list<array<string, mixed>> $definitions
     * @return array<string, array<string, mixed>>
     */
    public function framework(array $clientOutput, array $definitions): array
    {
        $probesById = [];
        foreach ($clientOutput['probes'] ?? [] as $probe) {
            $probesById[(string) $probe['id']] = $probe;
        }

        $rows = [];
        foreach ($definitions as $definition) {
            $id = (string) $definition['id'];
            $method = (string) ($definition['method'] ?? '');
            $row = [
                'feature' => $id,
                'method' => $method,
                'expected' => (string) ($definition['expected'] ?? ''),
            ];

            if ($method === 'push-diagnostics') {
                $file = (string) ($definition['file'] ?? '');
                /** @var list<array<string, mixed>> $published */
                $published = $clientOutput['diagnostics'][$file] ?? [];
                $line = $definition['line'] ?? null;
                if (is_int($line)) {
                    $published = array_values(array_filter(
                        $published,
                        static fn (array $d): bool => (int) ($d['line'] ?? 0) === $line,
                    ));
                }

                if ($published === []) {
                    $row['probe'] = 'empty';
                    $row['shown'] = '';
                    $rows[$id] = $row;
                    continue;
                }

                // Any diagnostic on the line used to count as an answer, so a
                // type checker that flags something unrelated (e.g. the
                // undefined-function complaint about a vendor-less env()) got
                // credited for a feature it does not implement. When the
                // probe declares what the diagnostic should say, only a
                // message that says it counts; a probe with no expected text
                // yet cannot tell relevance from noise, so it is recorded as
                // unrecognized rather than defaulting to a pass.
                $expected = $row['expected'];
                $matching = $expected !== ''
                    ? array_values(array_filter(
                        $published,
                        static fn (array $d): bool => str_contains((string) ($d['message'] ?? ''), $expected),
                    ))
                    : [];
                $row['probe'] = $matching !== [] ? 'answered' : 'unrecognized';
                $shownFrom = $matching !== [] ? $matching : $published;
                $row['shown'] = (string) ($shownFrom[0]['message'] ?? '');
                $rows[$id] = $row;
                continue;
            }

            $probe = $probesById[$id] ?? null;
            if ($probe === null) {
                $row['probe'] = 'not-probed';
                $row['shown'] = '';
            } else {
                $row = [...$row, ...$this->probeVerdict($probe)];
                $row['shown'] = $method === 'textDocument/hover' && is_array($probe['result'] ?? null)
                    ? $this->hoverText($probe['result'])
                    : '';
            }

            $rows[$id] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function grade(string $shown, array $definition): string
    {
        foreach ($definition['precise'] ?? [] as $pattern) {
            if (preg_match('{' . (string) $pattern . '}', $shown) !== 1) {
                return 'other';
            }
        }
        foreach ($definition['reject'] ?? [] as $pattern) {
            if (preg_match('{' . (string) $pattern . '}', $shown) === 1) {
                return 'other';
            }
        }

        return 'precise';
    }

    /**
     * Grade the real-project navigation session: per symbol, did go-to-
     * definition land on the declaration line, and how much of the known
     * reference set did find-references enumerate.
     *
     * Reference recall is found/expected over the complete list in
     * navigation.toml; anything returned beyond it is counted as an extra,
     * not a failure — the corpus plants same-name decoys (another info(),
     * other getAll()s), so a large extra count is how name-matching
     * reference implementations become visible.
     *
     * @param array<string, mixed> $clientOutput
     * @param list<array<string, mixed>> $symbols from NavigationDefinitions
     * @return array<string, array<string, mixed>> symbol id => graded row
     */
    public function navigation(array $clientOutput, array $symbols): array
    {
        $probesById = [];
        foreach ($clientOutput['probes'] ?? [] as $probe) {
            $probesById[(string) $probe['id']] = $probe;
        }

        $rows = [];
        foreach ($symbols as $symbol) {
            $id = (string) $symbol['id'];
            $row = ['kind' => (string) $symbol['kind'], 'name' => (string) $symbol['name']];

            $definition = $probesById['nav-def:' . $id] ?? null;
            if (($symbol['probe'] ?? null) === null) {
                // The symbol declares no use site to jump from: its definition
                // answer is not gradeable, and only its references are asked.
                $row['definition'] = 'n/a';
            } elseif ($definition === null || ($definition['skipped'] ?? false) === true) {
                $row['definition'] = 'skipped';
            } elseif (($definition['ok'] ?? false) !== true) {
                $row['definition'] = ($definition['error'] ?? '') === 'timeout' ? 'timeout' : 'error';
            } else {
                $locations = $this->locations($definition['result']);
                if ($locations === []) {
                    $row['definition'] = 'empty';
                } else {
                    $expected = $symbol['decl'];
                    // Containment, not equality: Intelephense answers with a
                    // range that starts at the declaration's docblock, which
                    // is still the right jump. Correct means some returned
                    // range in the right file covers the declaration line.
                    $hit = array_filter(
                        $locations,
                        static fn (array $location): bool => $location['file'] === $expected['file']
                            && $location['line'] <= $expected['line']
                            && $expected['line'] <= max($location['line'], $location['endLine']),
                    );
                    $row['definition'] = $hit === [] ? 'wrong-location' : 'correct';
                    if ($hit === []) {
                        $first = $locations[0];
                        $row['definition_actual'] = "{$first['file']}:{$first['line']}";
                    }
                }
            }

            $references = $probesById['nav-refs:' . $id] ?? null;
            $expectedRefs = array_map(
                static fn (array $ref): string => $ref['file'] . ':' . $ref['line'],
                $symbol['refs'],
            );
            $row['refs_expected'] = count($expectedRefs);
            if ($references === null || ($references['skipped'] ?? false) === true) {
                $row['references'] = 'skipped';
                $row['refs_found'] = 0;
                $row['refs_extra'] = 0;
            } elseif (($references['ok'] ?? false) !== true) {
                $row['references'] = ($references['error'] ?? '') === 'timeout' ? 'timeout' : 'error';
                $row['refs_found'] = 0;
                $row['refs_extra'] = 0;
            } else {
                $found = array_unique(array_map(
                    static fn (array $location): string => $location['file'] . ':' . $location['line'],
                    $this->locations($references['result']),
                ));
                $matched = array_intersect($expectedRefs, $found);
                $row['refs_found'] = count($matched);
                $row['refs_extra'] = count($found) - count($matched);
                $row['references'] = match (true) {
                    $found === [] => 'none',
                    count($matched) === count($expectedRefs) => 'all',
                    $matched === [] => 'none',
                    default => 'partial',
                };
                $missing = array_values(array_diff($expectedRefs, $found));
                if ($missing !== [] && count($matched) > 0) {
                    $row['refs_missing'] = $missing;
                }
            }

            $rows[$id] = $row;
        }

        return $rows;
    }

    /**
     * Normalize every shape a definition/references result may take —
     * Location, Location[], LocationLink[] — into workspace-relative
     * {file, line, endLine} triples. URIs outside the workspace are kept
     * as-is and simply never match an expectation.
     *
     * @return list<array{file: string, line: int, endLine: int}>
     */
    private function locations(mixed $result): array
    {
        if ($result === null) {
            return [];
        }
        $items = is_array($result) && array_is_list($result) ? $result : [$result];
        $locations = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $uri = $item['uri'] ?? $item['targetUri'] ?? null;
            $range = $item['range'] ?? $item['targetRange'] ?? $item['targetSelectionRange'] ?? null;
            if (!is_string($uri) || !is_array($range)) {
                continue;
            }
            $locations[] = [
                'file' => $this->workspaceRelative($uri),
                'line' => (int) ($range['start']['line'] ?? -1) + 1,
                'endLine' => (int) ($range['end']['line'] ?? ($range['start']['line'] ?? -1)) + 1,
            ];
        }

        return $locations;
    }

    private function workspaceRelative(string $uri): string
    {
        $path = rawurldecode(preg_replace('{^file://}', '', $uri) ?? $uri);
        // The client records the workspace as diagnostics keys' base; here we
        // only need a stable suffix: strip everything up to the temp
        // workspace segment, which always contains "lsp-ws-".
        $marker = strpos($path, 'lsp-ws-');
        if ($marker === false) {
            return $path;
        }
        $slash = strpos($path, '/', $marker);

        return $slash === false ? $path : substr($path, $slash + 1);
    }

    /**
     * Flatten every shape LSP allows hover contents to take into one string.
     *
     * @param array<string, mixed> $hover
     */
    public function hoverText(array $hover): string
    {
        $contents = $hover['contents'] ?? null;
        $parts = [];
        foreach (is_array($contents) && array_is_list($contents) ? $contents : [$contents] as $part) {
            if (is_string($part)) {
                $parts[] = $part;
            } elseif (is_array($part) && isset($part['value'])) {
                $parts[] = (string) $part['value'];
            }
        }

        return trim(implode("\n", $parts));
    }
}
