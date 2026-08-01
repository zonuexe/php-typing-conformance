// Server-agnostic LSP probe client: spawns a language server over stdio,
// records what the initialize handshake advertises, then exercises a list of
// probe requests against a prepared workspace and prints everything as JSON.
//
// Usage: node lsp-probe.mjs <spec.json>
//
// The spec is prepared by the PHP runner (see LspProbeRunner), which also owns
// the workspace directory: unlike intelephense-client.mjs this client copies
// nothing and cleans up nothing, so one workspace layout can carry per-server
// config files (psalm.xml, .phan/config.php) the client knows nothing about.
//
// Spec shape:
// {
//   "command": ["php", ".../psalm-language-server", "-r", "..."],
//   "workspace": "/abs/path",            // rootUri; probe files live inside
//   "open": ["case.php", ...],           // didOpen, in order, relative paths
//   "settings": {...},                   // didChangeConfiguration + the reply
//                                        // to workspace/configuration pulls
//   "initializationOptions": {...},
//   "probes": [{"id": "hover-narrowing", "method": "textDocument/hover",
//               "file": "case.php", "line": 9, "character": 14, ...}, ...],
//   "warmupMs": 2000, "settleMs": 1500, "timeoutMs": 90000, "probeTimeoutMs": 15000
// }
//
// Output:
// {
//   "serverInfo": {...}|null,
//   "capabilities": {...},               // the initialize result, verbatim
//   "dynamicRegistrations": ["textDocument/rename", ...],
//   "diagnostics": {"case.php": [{line, character, severity, message, code}]},
//   "probes": [{"id", "method", "ok", "result", "error", "ms"}]
// }
//
// Probes run strictly after the diagnostics stream settles (or after warmupMs
// when a server publishes nothing), because most servers answer hover from an
// index that is still being built right after didOpen.

import { spawn } from 'node:child_process';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const specPath = process.argv[2];
if (!specPath) {
  process.stderr.write('usage: lsp-probe.mjs <spec.json>\n');
  process.exit(2);
}
const spec = JSON.parse(readFileSync(specPath, 'utf8'));

const workspace = spec.workspace;
const workspaceUri = pathToFileURL(workspace).toString();
const WARMUP_MS = spec.warmupMs ?? 2000;
const SETTLE_MS = spec.settleMs ?? 1500;
const INDEX_TIMEOUT_MS = spec.indexTimeoutMs ?? 30000;
const TIMEOUT_MS = spec.timeoutMs ?? 120000;
const PROBE_TIMEOUT_MS = spec.probeTimeoutMs ?? 15000;

const [cmd, ...args] = spec.command;
const server = spawn(cmd, args, {
  cwd: spec.cwd ?? workspace,
  env: { ...process.env, ...(spec.env ?? {}) },
  stdio: ['pipe', 'pipe', 'inherit'],
});
server.on('error', (e) => fail(`spawn: ${e.message}`));

function uriFor(rel) {
  return pathToFileURL(path.join(workspace, rel)).toString();
}
function relFor(uri) {
  const prefix = workspaceUri.endsWith('/') ? workspaceUri : workspaceUri + '/';
  return uri.startsWith(prefix) ? decodeURIComponent(uri.slice(prefix.length)) : uri;
}

// ---- JSON-RPC over stdio ----

let seq = 0;
const pending = new Map(); // id -> {resolve}
function notify(method, params) {
  write({ jsonrpc: '2.0', method, params });
}
function request(method, params) {
  const id = ++seq;
  write({ jsonrpc: '2.0', id, method, params });
  return new Promise((resolve) => pending.set(id, resolve));
}
function respond(id, result) {
  write({ jsonrpc: '2.0', id, result });
}
function write(msg) {
  const body = Buffer.from(JSON.stringify(msg), 'utf8');
  server.stdin.write(`Content-Length: ${body.length}\r\n\r\n`);
  server.stdin.write(body);
}

let buffer = Buffer.alloc(0);
server.stdout.on('data', (chunk) => {
  buffer = Buffer.concat([buffer, chunk]);
  for (;;) {
    const headerEnd = buffer.indexOf('\r\n\r\n');
    if (headerEnd === -1) break;
    const header = buffer.slice(0, headerEnd).toString('utf8');
    const m = /Content-Length:\s*(\d+)/i.exec(header);
    if (!m) { buffer = buffer.slice(headerEnd + 4); continue; }
    const len = parseInt(m[1], 10);
    const start = headerEnd + 4;
    if (buffer.length < start + len) break;
    const body = buffer.slice(start, start + len).toString('utf8');
    buffer = buffer.slice(start + len);
    try { handle(JSON.parse(body)); } catch {}
  }
});

// ---- Server messages ----

const diagnosticsByRel = new Map();
const dynamicRegistrations = new Set();
const activeProgress = new Set();
let settleTimer = null;
let onSettled = null;

function scheduleSettle() {
  if (onSettled === null) return;
  clearTimeout(settleTimer);
  settleTimer = setTimeout(() => {
    // An indexer that reports progress ($/progress begin without end yet)
    // is still building the answers the probes will ask for; hold the
    // probes until every token ends, capped by INDEX_TIMEOUT_MS overall.
    if (activeProgress.size > 0) { scheduleSettle(); return; }
    const f = onSettled; onSettled = null; f();
  }, SETTLE_MS);
}

function handle(msg) {
  if (msg.id !== undefined && msg.method === undefined) {
    const resolve = pending.get(msg.id);
    if (resolve) { pending.delete(msg.id); resolve(msg); }
    return;
  }
  if (msg.method === '$/progress') {
    const { token, value } = msg.params ?? {};
    if (value && value.kind === 'begin') activeProgress.add(token);
    if (value && value.kind === 'end') { activeProgress.delete(token); scheduleSettle(); }
    return;
  }
  if (msg.method === 'textDocument/publishDiagnostics') {
    const { uri, diagnostics } = msg.params;
    diagnosticsByRel.set(relFor(uri), diagnostics.map((d) => ({
      line: d.range.start.line + 1,
      character: d.range.start.character,
      severity: d.severity ?? null,
      message: d.message,
      code: d.code !== undefined && d.code !== null ? String(d.code) : '',
    })));
    scheduleSettle();
    return;
  }
  if (msg.id !== undefined && msg.method) {
    // Server-initiated requests. Answer configuration pulls from the spec's
    // settings so servers that ignore didChangeConfiguration still get them;
    // record dynamic capability registrations, which count as advertised.
    if (msg.method === 'workspace/configuration') {
      respond(msg.id, (msg.params.items ?? []).map((item) => {
        const section = item.section ?? null;
        if (section === null) return spec.settings ?? null;
        return section.split('.').reduce((s, key) => (s === null || s === undefined ? null : s[key] ?? null), spec.settings ?? null);
      }));
      return;
    }
    if (msg.method === 'client/registerCapability') {
      for (const reg of msg.params.registrations ?? []) dynamicRegistrations.add(reg.method);
      respond(msg.id, null);
      return;
    }
    if (msg.method === 'workspace/workspaceFolders') {
      respond(msg.id, [{ uri: workspaceUri, name: 'conformance' }]);
      return;
    }
    respond(msg.id, null);
  }
}

// ---- Probe parameter building ----

// Which ServerCapabilities field advertises each probe method. A probe whose
// field is absent/false is recorded as skipped instead of sent, because a
// server that does not advertise a method commonly never answers it at all
// and every such probe would cost a full timeout.
const CAPABILITY_FIELD = {
  'textDocument/diagnostic': 'diagnosticProvider',
  'textDocument/hover': 'hoverProvider',
  'textDocument/completion': 'completionProvider',
  'textDocument/signatureHelp': 'signatureHelpProvider',
  'textDocument/definition': 'definitionProvider',
  'textDocument/typeDefinition': 'typeDefinitionProvider',
  'textDocument/implementation': 'implementationProvider',
  'textDocument/declaration': 'declarationProvider',
  'textDocument/references': 'referencesProvider',
  'textDocument/documentHighlight': 'documentHighlightProvider',
  'textDocument/documentSymbol': 'documentSymbolProvider',
  'workspace/symbol': 'workspaceSymbolProvider',
  'textDocument/codeAction': 'codeActionProvider',
  'textDocument/rename': 'renameProvider',
  'textDocument/formatting': 'documentFormattingProvider',
  'textDocument/foldingRange': 'foldingRangeProvider',
  'textDocument/selectionRange': 'selectionRangeProvider',
  'textDocument/semanticTokens/full': 'semanticTokensProvider',
  'textDocument/inlayHint': 'inlayHintProvider',
  'textDocument/prepareCallHierarchy': 'callHierarchyProvider',
  'textDocument/prepareTypeHierarchy': 'typeHierarchyProvider',
};

function isAdvertised(method) {
  const field = CAPABILITY_FIELD[method];
  if (field === undefined) return true; // no known gate; send it
  const value = output.capabilities[field];
  if (value !== undefined && value !== null && value !== false) return true;
  return dynamicRegistrations.has(method);
}

function probeParams(probe) {
  const doc = probe.file === undefined ? null : { uri: uriFor(probe.file) };
  const pos = probe.line === undefined ? null : { line: probe.line - 1, character: probe.character ?? 0 };
  switch (probe.method) {
    case 'textDocument/hover':
    case 'textDocument/definition':
    case 'textDocument/typeDefinition':
    case 'textDocument/implementation':
    case 'textDocument/declaration':
    case 'textDocument/documentHighlight':
    case 'textDocument/signatureHelp':
    case 'textDocument/prepareCallHierarchy':
    case 'textDocument/prepareTypeHierarchy':
    case 'textDocument/selectionRange':
      return probe.method === 'textDocument/selectionRange'
        ? { textDocument: doc, positions: [pos] }
        : { textDocument: doc, position: pos };
    case 'textDocument/completion':
      return { textDocument: doc, position: pos, context: { triggerKind: 1 } };
    case 'textDocument/references':
      return { textDocument: doc, position: pos, context: { includeDeclaration: true } };
    case 'textDocument/rename':
      return { textDocument: doc, position: pos, newName: probe.newName ?? 'renamedByProbe' };
    case 'textDocument/documentSymbol':
    case 'textDocument/foldingRange':
    case 'textDocument/semanticTokens/full':
    case 'textDocument/diagnostic':
      return { textDocument: doc };
    case 'textDocument/formatting':
      return { textDocument: doc, options: { tabSize: 4, insertSpaces: true } };
    case 'textDocument/codeAction': {
      const line = pos ? pos.line : 0;
      return {
        textDocument: doc,
        range: { start: { line, character: 0 }, end: { line: line + 1, character: 0 } },
        context: { diagnostics: [] },
      };
    }
    case 'textDocument/inlayHint': {
      const endLine = probe.endLine === undefined ? (pos ? pos.line + 1 : 200) : probe.endLine - 1;
      return {
        textDocument: doc,
        range: { start: { line: 0, character: 0 }, end: { line: endLine, character: 0 } },
      };
    }
    case 'workspace/symbol':
      return { query: probe.query ?? '' };
    default:
      return probe.params ?? {};
  }
}

// ---- Lifecycle ----

const output = {
  serverInfo: null,
  capabilities: null,
  dynamicRegistrations: [],
  diagnostics: {},
  probes: [],
};

function fail(reason) {
  process.stdout.write(JSON.stringify({ ...output, failure: reason }));
  try { server.kill(); } catch {}
  process.exit(1);
}

const globalTimer = setTimeout(() => fail('global timeout'), TIMEOUT_MS);

async function main() {
  const init = await request('initialize', {
    processId: process.pid,
    rootUri: workspaceUri,
    workspaceFolders: [{ uri: workspaceUri, name: 'conformance' }],
    initializationOptions: spec.initializationOptions ?? {},
    capabilities: {
      textDocument: {
        publishDiagnostics: { relatedInformation: false },
        synchronization: { didSave: false, dynamicRegistration: false },
        hover: { contentFormat: ['markdown', 'plaintext'] },
        completion: { completionItem: { snippetSupport: false } },
        documentSymbol: { hierarchicalDocumentSymbolSupport: true },
        rename: { prepareSupport: false },
      },
      workspace: { configuration: true, workspaceFolders: true, symbol: {} },
      window: { workDoneProgress: true },
    },
  });
  if (init.error) fail(`initialize: ${JSON.stringify(init.error)}`);
  output.serverInfo = init.result.serverInfo ?? null;
  output.capabilities = init.result.capabilities ?? {};

  notify('initialized', {});
  if (spec.settings !== undefined) {
    notify('workspace/didChangeConfiguration', { settings: spec.settings });
  }
  for (const rel of spec.open ?? []) {
    notify('textDocument/didOpen', {
      textDocument: {
        uri: uriFor(rel),
        languageId: 'php',
        version: 1,
        text: readFileSync(path.join(workspace, rel), 'utf8'),
      },
    });
  }

  // Probes must not race the server's first analysis: Phan takes many
  // seconds to produce its first publishDiagnostics under the polyfill
  // parser, and probing before it lands answers every request from a cold
  // index. So wait for the diagnostics stream to go quiet — each publish
  // (re)arms a SETTLE_MS timer — and give a server that never publishes at
  // all INDEX_TIMEOUT_MS before probing anyway. The warmup floor keeps one
  // instant publish from starting the probes against a half-built index.
  const settled = new Promise((resolve) => {
    onSettled = resolve;
    setTimeout(() => { if (onSettled !== null) { onSettled = null; resolve(); } }, INDEX_TIMEOUT_MS);
  });
  await Promise.all([settled, new Promise((resolve) => setTimeout(resolve, WARMUP_MS))]);

  for (const probe of spec.probes ?? []) {
    if (!isAdvertised(probe.method)) {
      output.probes.push({
        id: probe.id, method: probe.method,
        ok: false, skipped: true, result: null, error: 'not advertised', ms: 0,
      });
      continue;
    }
    const started = Date.now();
    const reply = await Promise.race([
      request(probe.method, probeParams(probe)),
      new Promise((resolve) => setTimeout(() => resolve({ timeout: true }), PROBE_TIMEOUT_MS)),
    ]);
    output.probes.push({
      id: probe.id,
      method: probe.method,
      ok: reply.timeout !== true && reply.error === undefined,
      result: reply.timeout === true ? null : reply.result ?? null,
      error: reply.timeout === true ? 'timeout' : (reply.error ? JSON.stringify(reply.error) : null),
      ms: Date.now() - started,
    });
  }

  output.dynamicRegistrations = [...dynamicRegistrations].sort();
  output.diagnostics = Object.fromEntries(diagnosticsByRel);
  clearTimeout(globalTimer);
  process.stdout.write(JSON.stringify(output));
  try {
    const bye = request('shutdown', null);
    await Promise.race([bye, new Promise((r) => setTimeout(r, 1000))]);
    notify('exit', null);
  } catch {}
  setTimeout(() => { try { server.kill(); } catch {}; process.exit(0); }, 200);
}

main().catch((e) => fail(String(e && e.stack ? e.stack : e)));
