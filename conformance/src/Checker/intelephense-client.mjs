// Minimal LSP client that drives the Intelephense language server over stdio
// and prints the diagnostics for every PHP file in a directory as JSON.
//
// Usage: node intelephense-client.mjs <server.js> <dir>
// Output (stdout): {"diagnostics": {"<basename>.php": [{"line": 1, "message": "...", "code": "..."}]}}
//
// One server session measures the whole corpus. Intelephense spends its time
// starting up and indexing rather than analysing — a file on its own cost
// ~2.1s, of which the analysis was a few milliseconds — so paying start-up
// once instead of 218 times is the whole point of this shape.
//
// Two things about it are load-bearing and were established by measurement,
// not by reading Intelephense's source:
//
//   * Documents are opened ONE AT A TIME. Opening all 218 at once makes the
//     server publish empty diagnostics for most of them (168 of 218 came back
//     bare), and no amount of waiting or nudging with didChange brings them
//     back — the results are simply wrong, not late. A three-file workspace
//     reproduces the per-file results exactly, so the limit is the number of
//     open documents, not the size of the workspace.
//   * The first publishDiagnostics for a document is its final one.
//     Instrumented with a 400ms window over the whole corpus, not one document
//     republished. So the collector takes the first publish and moves on,
//     which makes the run deterministic (four consecutive runs produced
//     byte-identical output) rather than dependent on a settle timer.
//
// Files are copied into a private temp workspace so Intelephense indexes the
// corpus and nothing else. Every test file shares that workspace, which the
// earlier one-workspace-per-test client deliberately avoided; a per-file diff
// against the stored results showed the sharing changes no diagnostic, and the
// test corpus namespaces every file separately, which is why.

import { spawn } from 'node:child_process';
import { promises as fs } from 'node:fs';
import { mkdtempSync, copyFileSync, readFileSync, readdirSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const [, , serverJs, sourceDir] = process.argv;
if (!serverJs || !sourceDir) {
  process.stderr.write('usage: intelephense-client.mjs <server.js> <dir>\n');
  process.exit(2);
}

const INDEX_SETTLE_MS = 1500; // quiet period that ends the initial index
const DOC_TIMEOUT_MS = 10000; // a document that never publishes is not fatal
const TIMEOUT_MS = 600000;

const workspace = mkdtempSync(path.join(os.tmpdir(), 'iph-ws-'));
const storagePath = mkdtempSync(path.join(os.tmpdir(), 'iph-store-'));

const docs = readdirSync(sourceDir)
  .filter((f) => f.endsWith('.php'))
  .sort()
  .map((name) => {
    const dest = path.join(workspace, name);
    copyFileSync(path.join(sourceDir, name), dest);
    return { name, uri: pathToFileURL(dest).toString(), text: readFileSync(dest, 'utf8') };
  });

const server = spawn(process.execPath, [serverJs, '--stdio'], { stdio: ['pipe', 'pipe', 'inherit'] });

let seq = 0;
const pending = new Map();

function send(method, params, isRequest) {
  const msg = { jsonrpc: '2.0', method, params };
  if (isRequest) msg.id = ++seq;
  const body = Buffer.from(JSON.stringify(msg), 'utf8');
  server.stdin.write(`Content-Length: ${body.length}\r\n\r\n`);
  server.stdin.write(body);
  return msg.id;
}

function request(method, params) {
  return new Promise((resolve) => {
    pending.set(send(method, params, true), resolve);
  });
}

// Parse the LSP framed stream.
let buffer = Buffer.alloc(0);
const activeProgress = new Set();
let indexSettleTimer = null;
let onIndexSettled = null;

// The document currently being measured, if any.
let current = null;

function scheduleIndexSettle() {
  if (onIndexSettled === null) return;
  clearTimeout(indexSettleTimer);
  indexSettleTimer = setTimeout(() => {
    // An indexer still reporting progress is not done building the answers
    // the first documents will be measured against.
    if (activeProgress.size > 0) { scheduleIndexSettle(); return; }
    const settled = onIndexSettled; onIndexSettled = null; settled();
  }, INDEX_SETTLE_MS);
}

function handle(msg) {
  if (msg.id !== undefined && msg.method === undefined) {
    const resolve = pending.get(msg.id);
    if (resolve) { pending.delete(msg.id); resolve(msg); }
    return;
  }

  if (msg.method === '$/progress') {
    const { token, value } = msg.params ?? {};
    if (value?.kind === 'begin') activeProgress.add(token);
    if (value?.kind === 'end') { activeProgress.delete(token); scheduleIndexSettle(); }
    return;
  }

  if (msg.method === 'textDocument/publishDiagnostics') {
    const { uri, diagnostics } = msg.params;
    scheduleIndexSettle();
    if (current !== null && uri === current.uri) {
      const done = current; current = null;
      done.resolve(diagnostics);
    }
    return;
  }

  // Reply to server-initiated requests so it does not block.
  if (msg.id !== undefined && msg.method) {
    const result = msg.method === 'workspace/configuration'
      ? (msg.params.items ?? []).map(() => settings.intelephense)
      : null;
    const body = Buffer.from(JSON.stringify({ jsonrpc: '2.0', id: msg.id, result }), 'utf8');
    server.stdin.write(`Content-Length: ${body.length}\r\n\r\n`);
    server.stdin.write(body);
  }
}

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

const settings = {
  intelephense: {
    // Intelephense's own default is 8.3.0, which would silently analyse the
    // corpus below the PHP version the rest of the suite targets.
    environment: { phpVersion: '8.5.0' },
    files: { associations: ['*.php'], maxSize: 5000000 },
    diagnostics: {
      enable: true,
      run: 'onType',
      relaxedTypeCheck: false,
      noMixedTypeCheck: false,
      undefinedTypes: true,
      undefinedFunctions: true,
      undefinedConstants: true,
      undefinedClassConstants: true,
      undefinedMethods: true,
      undefinedProperties: true,
      undefinedVariables: true,
      typeErrors: true,
    },
  },
};

async function cleanup() {
  await fs.rm(workspace, { recursive: true, force: true }).catch(() => {});
  await fs.rm(storagePath, { recursive: true, force: true }).catch(() => {});
}

async function main() {
  await request('initialize', {
    processId: process.pid,
    rootUri: pathToFileURL(workspace).toString(),
    workspaceFolders: [{ uri: pathToFileURL(workspace).toString(), name: 'ws' }],
    initializationOptions: { storagePath, clearCache: true },
    capabilities: {
      textDocument: {
        publishDiagnostics: { relatedInformation: false },
        synchronization: { didSave: false, dynamicRegistration: false },
      },
      workspace: { configuration: true, workspaceFolders: true },
      window: { workDoneProgress: true },
    },
  });

  send('initialized', {}, false);
  send('workspace/didChangeConfiguration', { settings }, false);

  await new Promise((resolve) => { onIndexSettled = resolve; scheduleIndexSettle(); });

  const diagnostics = {};
  for (const doc of docs) {
    const published = new Promise((resolve) => {
      current = { uri: doc.uri, resolve };
      setTimeout(() => {
        if (current !== null && current.uri === doc.uri) {
          const timedOut = current; current = null; timedOut.resolve([]);
        }
      }, DOC_TIMEOUT_MS);
    });

    send('textDocument/didOpen', {
      textDocument: { uri: doc.uri, languageId: 'php', version: 1, text: doc.text },
    }, false);

    const published_ = await published;
    send('textDocument/didClose', { textDocument: { uri: doc.uri } }, false);

    diagnostics[doc.name] = published_.map((d) => ({
      line: d.range.start.line + 1,
      character: d.range.start.character,
      severity: d.severity ?? null,
      message: d.message,
      code: d.code !== undefined && d.code !== null ? String(d.code) : '',
    }));
  }

  process.stdout.write(JSON.stringify({ diagnostics }));

  try { send('shutdown', null, true); } catch {}
  try { send('exit', null, false); } catch {}
  setTimeout(async () => { server.kill(); await cleanup(); process.exit(0); }, 100);
}

setTimeout(async () => { server.kill(); await cleanup(); process.exit(3); }, TIMEOUT_MS).unref();

server.on('exit', () => { cleanup(); });

main();
