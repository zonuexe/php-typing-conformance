<?php

declare(strict_types=1);

namespace Conformance\Lsp;

use RuntimeException;

/**
 * Asks PhpStorm the language-server probes, through the MCP server the IDE
 * runs inside itself, and hands back output in the shape lsp-probe.mjs
 * prints, so ProbeGrading grades both the same way.
 *
 * PhpStorm is not a language server: it answers no LSP request and has no
 * initialize handshake. Its MCP server is the one machine-readable interface
 * it does have, and it maps onto the protocol closely enough to measure the
 * same things at the same positions. tools/list stands in for the handshake:
 * a capability counts as advertised when a tool for it is exposed, and its
 * probe calls that tool where an LSP client would send the request. What the
 * MCP server offers no tool for — completion, signature help, folding, inlay
 * hints, and so on — is recorded as not advertised. The editor feature may
 * exist; it is just not reachable from outside the IDE, and this measures
 * what is reachable.
 *
 * Two probes need more than one call. Find references has no MCP request, so
 * it is assembled the way the IDE assembles Find Usages: every textual
 * occurrence of the name in the project is a candidate, get_symbol_info
 * resolves each one, and the sites that resolve to the declaration are the
 * answer. A site PhpStorm cannot resolve — a constant read off a `mixed`
 * receiver — is left out, where the IDE's own Find Usages would list it
 * under "Dynamic usages". And rename and formatting edit the file they are
 * asked about, which an LSP request does not, so they run last, on a scratch
 * copy the next run copies afresh.
 *
 * PhpStorm is launched headless, like every language server, through the
 * `mcpServer` app starter its bundled MCP plugin registers: it opens a
 * project, waits until indexing is done, and only then starts serving. The
 * starter is marked internal, so a later build may move it; the one line of
 * its output relied on is "MCP Server started on port". It opens one project
 * per instance — given
 * several, each one opened disposes the one before, and the starter then
 * waits forever for that one to finish indexing — so each session gets its
 * own instance, in its own scratch copy under $workRoot. Like the
 * command-line inspector, it will not start while PhpStorm is already
 * running.
 */
final class PhpStormProbeRunner
{
    /**
     * LSP method => [ServerCapabilities field it stands in for, MCP tool].
     *
     * @var array<string, array{string, string}>
     */
    public const TOOLS = [
        'textDocument/diagnostic' => ['diagnosticProvider', 'get_file_problems'],
        'textDocument/hover' => ['hoverProvider', 'get_symbol_info'],
        'textDocument/definition' => ['definitionProvider', 'get_symbol_info'],
        'textDocument/references' => ['referencesProvider', 'get_symbol_info'],
        'workspace/symbol' => ['workspaceSymbolProvider', 'search_symbol'],
        'textDocument/rename' => ['renameProvider', 'rename_refactoring'],
        'textDocument/formatting' => ['documentFormattingProvider', 'reformat_file'],
        'textDocument/prepareCallHierarchy' => ['callHierarchyProvider', 'analyze_calls'],
    ];

    /** The methods that change the file they are asked about. */
    private const EDITING = ['textDocument/rename', 'textDocument/formatting'];

    private ?PhpStormMcpClient $client = null;

    /** @var resource|null the headless instance serving $client */
    private $process = null;

    /** @var list<string> */
    private array $toolNames = [];

    public function __construct(
        private readonly string $launcher,
        private readonly string $workRoot,
    ) {
    }

    public function __destruct()
    {
        $this->close();
    }

    /** @return array{name: string, version: string} */
    public function serverInfo(): array
    {
        return $this->client()->serverInfo;
    }

    /** @return list<string> */
    public function toolNames(): array
    {
        $this->client();

        return $this->toolNames;
    }

    /**
     * Copy a source tree into a scratch project, pinned to PHP 8.5 like every
     * other workspace in this suite, and launch a headless PhpStorm serving
     * MCP for it. Whatever instance the previous session left is stopped
     * first.
     *
     * @param array{file: string, line: int, character: int} $ready a reference
     *        the IDE can only resolve once it has indexed what the probes
     *        need; checked before the session starts, as a guard on the
     *        starter's own wait for indexing
     * @param array<string, string> $extraFiles project-relative path => source file to copy over
     * @return string the project path, which every MCP call names
     */
    public function open(
        string $name,
        string $sourceDir,
        array $ready,
        bool $linkVendor = false,
        array $extraFiles = [],
    ): string {
        $this->close();

        $project = rtrim($this->workRoot, '/') . '/' . $name;
        $this->copyTree($sourceDir, $project, $linkVendor);
        foreach ($extraFiles as $relative => $source) {
            copy($source, $project . '/' . $relative);
        }
        if (!is_file($project . '/.idea/php.xml')) {
            @mkdir($project . '/.idea', 0777, true);
            file_put_contents($project . '/.idea/php.xml', <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <project version="4">
                  <component name="PhpProjectSharedConfiguration" php_language_level="8.5" />
                </project>

                XML);
        }

        $this->launch($project, rtrim($this->workRoot, '/') . "/{$name}.log");
        $this->waitUntilResolved($project, $ready);

        return $project;
    }

    /** Stop the headless instance, if one is running. */
    public function close(): void
    {
        $this->client = null;
        $process = $this->process;
        $this->process = null;
        if ($process === null) {
            return;
        }

        proc_terminate($process);
        for ($i = 0; $i < 60 && proc_get_status($process)['running']; $i++) {
            usleep(500000);
        }
        if (proc_get_status($process)['running']) {
            proc_terminate($process, 9);
        }
        proc_close($process);
    }

    /**
     * Start `phpstorm mcpServer` for one project and connect to it once it
     * reports the server started — which it does only after indexing.
     *
     * The port is chosen here rather than left to the starter: given port 0
     * it binds a free one but reports "port 0", leaving nothing to connect to.
     */
    private function launch(string $project, string $log): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new RuntimeException('Cannot find a free port for PhpStorm\'s MCP server');
        }
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $process = proc_open(
            [$this->launcher, 'mcpServer', '--allowed-tools=*', '--invocation-mode=DIRECT', "--port={$port}", "--project={$project}"],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']],
            $pipes,
            $project,
        );
        if ($process === false) {
            throw new RuntimeException("Cannot launch {$this->launcher}");
        }
        $this->process = $process;

        $deadline = time() + 1800;
        while (time() < $deadline) {
            if (str_contains((string) file_get_contents($log), 'MCP Server started on port')) {
                $this->client = new PhpStormMcpClient("http://127.0.0.1:{$port}/stream");
                $this->toolNames = $this->client->toolNames();

                return;
            }
            if (!proc_get_status($process)['running']) {
                break;
            }
            sleep(1);
        }

        $tail = implode("\n", array_slice(file($log, FILE_IGNORE_NEW_LINES) ?: [], -15));
        $this->close();

        throw new RuntimeException(
            "PhpStorm's headless MCP server never started for {$project}. It does not start while PhpStorm is already running. Its output ({$log}):\n{$tail}",
        );
    }

    /**
     * Ask every probe in one project.
     *
     * @param list<array<string, mixed>> $probes client-ready probes (id, method, file, line, character, …)
     * @param list<string> $diagnosticFiles files whose problems stand in for published diagnostics
     * @return array<string, mixed> lsp-probe.mjs-shaped output
     */
    public function run(string $project, array $probes, array $diagnosticFiles = []): array
    {
        $capabilities = [];
        foreach (self::TOOLS as [$field, $tool]) {
            if (in_array($tool, $this->toolNames(), true)) {
                $capabilities[$field] = true;
            }
        }

        // Probes that edit their file go last, so every other probe reads
        // the file as committed.
        usort($probes, static fn (array $a, array $b): int
            => (int) in_array($a['method'] ?? '', self::EDITING, true) <=> (int) in_array($b['method'] ?? '', self::EDITING, true));

        $results = [];
        foreach ($probes as $probe) {
            $method = (string) ($probe['method'] ?? '');
            $tool = self::TOOLS[$method][1] ?? null;
            if ($tool === null || !in_array($tool, $this->toolNames(), true)) {
                $results[] = ['id' => $probe['id'], 'method' => $method, 'ok' => false, 'skipped' => true, 'result' => null, 'error' => 'not advertised'];
                continue;
            }

            try {
                $result = $this->ask($project, $method, $probe);
                $results[] = ['id' => $probe['id'], 'method' => $method, 'ok' => true, 'result' => $result, 'error' => null];
            } catch (ProbeFailed $e) {
                $results[] = ['id' => $probe['id'], 'method' => $method, 'ok' => false, 'result' => null, 'error' => $e->getMessage()];
            }
        }

        $diagnostics = [];
        foreach ($diagnosticFiles as $file) {
            $diagnostics[$file] = $this->problems($project, $file);
        }

        return [
            'capabilities' => $capabilities,
            'dynamicRegistrations' => [],
            'probes' => $results,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $probe
     */
    private function ask(string $project, string $method, array $probe): mixed
    {
        $file = (string) ($probe['file'] ?? '');

        return match ($method) {
            'textDocument/hover' => $this->hover($project, $probe),
            'textDocument/definition' => $this->definition($project, $probe),
            'textDocument/references' => $this->references($project, $probe),
            'textDocument/diagnostic' => ['kind' => 'full', 'items' => $this->problems($project, $file)],
            'workspace/symbol' => $this->symbols($project, (string) ($probe['query'] ?? '')),
            'textDocument/rename' => $this->rename($project, $probe),
            'textDocument/formatting' => $this->format($project, $file),
            'textDocument/prepareCallHierarchy' => $this->callHierarchy($project, $probe),
            default => throw new ProbeFailed("no MCP tool for {$method}"),
        };
    }

    /**
     * Quick Documentation for the position, as hover contents.
     *
     * The text is Quick Documentation's own HTML rendered to Markdown, and
     * the one rendering artifact that matters is undone: a space HTML keeps
     * from breaking (`int<1,&nbsp;100>`) is an ordinary space to every other
     * server's pretty-printer.
     *
     * @param array<string, mixed> $probe
     * @return array{contents: array{kind: string, value: string}}|null
     */
    private function hover(string $project, array $probe): ?array
    {
        $info = $this->symbolInfo($project, $probe);
        $text = trim(str_replace("\u{00A0}", ' ', (string) ($info['documentation'] ?? '')));

        return $text === '' ? null : ['contents' => ['kind' => 'markdown', 'value' => $text]];
    }

    /**
     * The declaration get_symbol_info resolves the position to — the same
     * target Quick Documentation describes, which is Go to Declaration's.
     *
     * @param array<string, mixed> $probe
     * @return list<array<string, mixed>>
     */
    private function definition(string $project, array $probe): array
    {
        $declaration = $this->declarationOf($project, (string) $probe['file'], (int) $probe['line'], (int) $probe['character'] + 1);

        return $declaration === null ? [] : [self::location($declaration['file'], $declaration['line'])];
    }

    /**
     * Find references, assembled from resolutions; see the class docblock.
     *
     * The probe sits on the declaration, as for every server. The candidate
     * names are the declared name, plus for a constructor everything a
     * `new` or `parent::` call can spell it as: the class name, `self`,
     * `static`, `parent`. The declaration itself is part of the answer, as
     * it is for the LSP probe, which asks with includeDeclaration.
     *
     * @param array<string, mixed> $probe
     * @return list<array<string, mixed>>
     */
    private function references(string $project, array $probe): array
    {
        $file = (string) $probe['file'];
        $line = (int) $probe['line'];
        $lines = file($project . '/' . $file) ?: [];
        $name = self::identifierAt($lines[$line - 1] ?? '', (int) $probe['character']);
        if ($name === null) {
            throw new ProbeFailed("no identifier at {$file}:{$line}");
        }

        $names = [$name];
        if (strtolower($name) === '__construct') {
            $class = self::enclosingClass($lines, $line);
            $names = [...$names, ...($class === null ? [] : [$class]), 'self', 'static', 'parent'];
        }

        $found = ["{$file}:{$line}" => self::location($file, $line)];
        foreach ($this->projectPhpFiles($project) as $candidateFile) {
            $source = file($project . '/' . $candidateFile) ?: [];
            foreach ($source as $index => $text) {
                foreach ($names as $candidate) {
                    $pattern = '/(?<![A-Za-z0-9_])' . preg_quote($candidate, '/') . '(?![A-Za-z0-9_])/';
                    if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE) === 0) {
                        continue;
                    }
                    foreach ($matches[0] as [, $offset]) {
                        $declaration = $this->declarationOf($project, $candidateFile, $index + 1, $offset + 1);
                        if ($declaration !== null && $declaration['file'] === $file && $declaration['line'] === $line) {
                            $found["{$candidateFile}:" . ($index + 1)] = self::location($candidateFile, $index + 1);
                        }
                    }
                }
            }
        }
        ksort($found);

        return array_values($found);
    }

    /**
     * get_file_problems, as diagnostics. An IDE anchors a problem on the
     * expression it is about and does not repeat that expression in the
     * message ("Unknown view", underlining `'missing.page'`), where a
     * language server's message names it. A string literal starting at the
     * problem's column is therefore appended in brackets, so a probe that
     * asks whether the diagnostic is about its key can tell.
     *
     * @return list<array{line: int, character: int, severity: string, message: string, code: string}>
     */
    private function problems(string $project, string $file): array
    {
        $answer = $this->client()->call('get_file_problems', [
            'filePath' => $file,
            'errorsOnly' => false,
            'timeout' => 120000,
            'projectPath' => $project,
        ]);
        if ($answer['error'] || !is_array($answer['json'])) {
            throw new ProbeFailed($answer['text']);
        }

        $lines = file($project . '/' . $file) ?: [];
        $problems = [];
        foreach (is_array($answer['json']['errors'] ?? null) ? $answer['json']['errors'] : [] as $problem) {
            $line = (int) ($problem['line'] ?? 0);
            $column = (int) ($problem['column'] ?? 0);
            $message = (string) ($problem['description'] ?? '');
            $literal = preg_match('/\G([\'"])(?:\\\\.|(?!\1).)*\1/', $lines[$line - 1] ?? '', $match, 0, max(0, $column - 1)) === 1
                ? $match[0]
                : null;
            $problems[] = [
                'line' => $line,
                'character' => max(0, $column - 1),
                'severity' => (string) ($problem['severity'] ?? ''),
                'message' => $literal === null ? $message : "{$message} [{$literal}]",
                'code' => '',
            ];
        }

        return $problems;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function symbols(string $project, string $query): array
    {
        $answer = $this->client()->call('search_symbol', ['q' => $query, 'projectPath' => $project]);
        if ($answer['error'] || !is_array($answer['json'])) {
            throw new ProbeFailed($answer['text']);
        }

        $symbols = [];
        foreach (is_array($answer['json']['items'] ?? null) ? $answer['json']['items'] : [] as $item) {
            $symbols[] = [
                'name' => $query,
                'location' => self::location((string) ($item['filePath'] ?? ''), (int) ($item['startLine'] ?? 0)),
            ];
        }

        return $symbols;
    }

    /**
     * rename_refactoring names the symbol rather than a position, so the
     * identifier at the probe's position is what it is asked to rename.
     *
     * @param array<string, mixed> $probe
     * @return array{changes: array<string, list<array<string, mixed>>>}|null
     */
    private function rename(string $project, array $probe): ?array
    {
        $file = (string) $probe['file'];
        $lines = file($project . '/' . $file) ?: [];
        $name = self::identifierAt($lines[(int) $probe['line'] - 1] ?? '', (int) $probe['character']);
        if ($name === null) {
            throw new ProbeFailed("no identifier at {$file}:{$probe['line']}");
        }

        $answer = $this->client()->call('rename_refactoring', [
            'pathInProject' => $file,
            'symbolName' => $name,
            'newName' => (string) ($probe['newName'] ?? 'renamedByProbe'),
            'projectPath' => $project,
        ]);
        if ($answer['error']) {
            throw new ProbeFailed($answer['text']);
        }

        // The tool reports what it did rather than returning the edits.
        return preg_match('/with (\d+) usages?/', $answer['text'], $match) === 1 && (int) $match[1] > 0
            ? ['changes' => [$file => array_fill(0, (int) $match[1], ['newText' => (string) ($probe['newName'] ?? '')])]]
            : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function format(string $project, string $file): array
    {
        $answer = $this->client()->call('reformat_file', ['files' => [$file], 'projectPath' => $project]);
        if ($answer['error']) {
            throw new ProbeFailed($answer['text']);
        }

        // The file is reformatted in place and no edits come back; an empty
        // edit list is how a language server says the same thing.
        return [];
    }

    /**
     * analyze_calls takes a fully qualified name rather than a position.
     *
     * @param array<string, mixed> $probe
     * @return list<array<string, mixed>>
     */
    private function callHierarchy(string $project, array $probe): array
    {
        $file = (string) $probe['file'];
        $lines = file($project . '/' . $file) ?: [];
        $name = self::identifierAt($lines[(int) $probe['line'] - 1] ?? '', (int) $probe['character']);
        $namespace = preg_match('/^namespace\s+([^;\s]+)\s*;/m', implode('', $lines), $match) === 1 ? $match[1] . '\\' : '';
        $fqn = $namespace . (string) $name;

        $answer = $this->client()->call('analyze_calls', [
            'symbolFqn' => $fqn,
            'analysisKind' => 'INCOMING_CALLS',
            'projectPath' => $project,
        ]);
        if ($answer['error'] || str_starts_with($answer['text'], 'No callable symbol found')) {
            throw new ProbeFailed(trim((string) strtok($answer['text'], "\n")));
        }

        return [['name' => $fqn, 'uri' => $file]];
    }

    /**
     * @param array<string, mixed> $probe
     * @return array<string, mixed>
     */
    private function symbolInfo(string $project, array $probe): array
    {
        $answer = $this->client()->call('get_symbol_info', [
            'filePath' => (string) $probe['file'],
            'line' => (int) $probe['line'],
            'column' => (int) $probe['character'] + 1,
            'projectPath' => $project,
        ]);
        if ($answer['error']) {
            throw new ProbeFailed($answer['text']);
        }

        return is_array($answer['json']) ? $answer['json'] : [];
    }

    /**
     * @return array{file: string, line: int}|null
     */
    private function declarationOf(string $project, string $file, int $line, int $column): ?array
    {
        $info = $this->symbolInfo($project, ['file' => $file, 'line' => $line, 'character' => $column - 1]);
        $declaration = $info['symbolInfo'] ?? null;
        if (!is_array($declaration) || !is_string($declaration['declarationFile'] ?? null) || !is_int($declaration['declarationLine'] ?? null)) {
            return null;
        }

        return ['file' => $declaration['declarationFile'], 'line' => $declaration['declarationLine']];
    }

    /**
     * @param array{file: string, line: int, character: int} $ready
     */
    private function waitUntilResolved(string $project, array $ready, int $timeoutSeconds = 120): void
    {
        $deadline = time() + $timeoutSeconds;
        $last = '';
        while (time() < $deadline) {
            try {
                if ($this->declarationOf($project, $ready['file'], $ready['line'], $ready['character'] + 1) !== null) {
                    return;
                }
            } catch (ProbeFailed $e) {
                $last = $e->getMessage();
            }
            sleep(2);
        }

        throw new RuntimeException(sprintf(
            'PhpStorm did not resolve %s:%d in %s within %ds after indexing. %s',
            $ready['file'],
            $ready['line'],
            $project,
            $timeoutSeconds,
            substr($last, 0, 300),
        ));
    }

    private function client(): PhpStormMcpClient
    {
        if ($this->client === null) {
            throw new RuntimeException('No PhpStorm session is open; call open() first');
        }

        return $this->client;
    }

    /**
     * @return list<string> project-relative paths, in a stable order
     */
    private function projectPhpFiles(string $project): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($project, \FilesystemIterator::SKIP_DOTS),
            // The symlinked vendor of a Laravel corpus is not the project's
            // own code, and .idea is the IDE's.
            static fn (\SplFileInfo $entry): bool => !in_array($entry->getFilename(), ['.idea', '.git', 'vendor', 'node_modules'], true),
        ));
        foreach ($iterator as $entry) {
            if ($entry->isFile() && str_ends_with($entry->getFilename(), '.php')) {
                $files[] = substr($entry->getPathname(), strlen($project) + 1);
            }
        }
        sort($files);

        return $files;
    }

    private function copyTree(string $source, string $destination, bool $linkVendor): void
    {
        if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
            throw new RuntimeException("Cannot create {$destination}");
        }

        // --delete keeps the scratch copy identical to the source between
        // runs, which matters because the editing probes changed it; the
        // excluded entries are protected from deletion, which keeps .idea
        // (the IDE's) and the vendor symlink.
        $exclude = '--exclude .idea --exclude .git' . ($linkVendor ? ' --exclude vendor --exclude node_modules' : '');
        exec(sprintf(
            'rsync -a --delete %s %s %s 2>&1',
            $exclude,
            escapeshellarg(rtrim($source, '/') . '/'),
            escapeshellarg($destination . '/'),
        ), $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException("Failed to copy {$source} into {$destination}: " . implode("\n", $output));
        }

        if ($linkVendor && is_dir($source . '/vendor') && !is_link($destination . '/vendor')) {
            if (!symlink($source . '/vendor', $destination . '/vendor')) {
                throw new RuntimeException("Failed to link vendor from {$source} into {$destination}");
            }
        }
    }

    /**
     * @return array<string, mixed> an LSP Location, with a project-relative
     *         path where the URI goes — ProbeGrading compares relative paths
     */
    private static function location(string $file, int $line): array
    {
        return [
            'uri' => $file,
            'range' => ['start' => ['line' => $line - 1, 'character' => 0], 'end' => ['line' => $line - 1, 'character' => 0]],
        ];
    }

    private static function identifierAt(string $line, int $character): ?string
    {
        return preg_match('/\G\$?([A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)/', $line, $match, 0, $character) === 1
            ? $match[1]
            : null;
    }

    /**
     * The class whose body a 1-based line falls in: the last class
     * declaration before it. Good enough for the one-class-per-file corpus
     * this is asked about.
     *
     * @param list<string> $lines
     */
    private static function enclosingClass(array $lines, int $line): ?string
    {
        $class = null;
        foreach (array_slice($lines, 0, $line) as $text) {
            if (preg_match('/^\s*(?:(?:final|abstract|readonly)\s+)*class\s+([A-Za-z_][A-Za-z0-9_]*)/', $text, $match) === 1) {
                $class = $match[1];
            }
        }

        return $class;
    }
}
