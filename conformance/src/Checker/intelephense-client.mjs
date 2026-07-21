// Minimal LSP client that drives the Intelephense language server over stdio
// and prints the diagnostics for a target PHP file as JSON.
//
// Usage: node intelephense-client.mjs <server.js> <supportFile>... <targetFile>
// Output (stdout): {"version": "x", "diagnostics": [{"line": 1, "message": "...", "code": "..."}]}
//
// The last path argument is the analysed target; earlier paths are companion
// files opened so cross-file symbols resolve. Files are copied into a private
// temp workspace so Intelephense only scans them (not the whole tests dir).

import { spawn } from 'node:child_process';
import { promises as fs } from 'node:fs';
import { mkdtempSync, copyFileSync, readFileSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const [, , serverJs, ...files] = process.argv;
if (!serverJs || files.length === 0) {
  process.stderr.write('usage: intelephense-client.mjs <server.js> <file>...\n');
  process.exit(2);
}

const SETTLE_MS = 1500; // quiet period after the target's diagnostics before finishing
const TIMEOUT_MS = 45000;

const workspace = mkdtempSync(path.join(os.tmpdir(), 'iph-ws-'));
const storagePath = mkdtempSync(path.join(os.tmpdir(), 'iph-store-'));

// Copy each input file into the workspace, keeping basenames.
const copied = files.map((f) => {
  const dest = path.join(workspace, path.basename(f));
  copyFileSync(f, dest);
  return dest;
});
const targetUri = pathToFileURL(copied[copied.length - 1]).toString();

const server = spawn(process.execPath, [serverJs, '--stdio'], { stdio: ['pipe', 'pipe', 'inherit'] });

let seq = 0;
function send(method, params, isRequest) {
  const msg = { jsonrpc: '2.0', method, params };
  if (isRequest) msg.id = ++seq;
  const body = Buffer.from(JSON.stringify(msg), 'utf8');
  server.stdin.write(`Content-Length: ${body.length}\r\n\r\n`);
  server.stdin.write(body);
}

// Parse the LSP framed stream.
let buffer = Buffer.alloc(0);
const diagnosticsByUri = new Map();
let sawTarget = false;
let settleTimer = null;

function finish() {
  clearTimeout(settleTimer);
  const diags = (diagnosticsByUri.get(targetUri) ?? []).map((d) => ({
    line: d.range.start.line + 1,
    character: d.range.start.character,
    severity: d.severity ?? null,
    message: d.message,
    code: d.code !== undefined && d.code !== null ? String(d.code) : '',
  }));
  process.stdout.write(JSON.stringify({ diagnostics: diags }));
  try { send('shutdown', null, true); } catch {}
  try { send('exit', null, false); } catch {}
  setTimeout(() => { server.kill(); cleanup(); process.exit(0); }, 100);
}

async function cleanup() {
  await fs.rm(workspace, { recursive: true, force: true }).catch(() => {});
  await fs.rm(storagePath, { recursive: true, force: true }).catch(() => {});
}

function scheduleSettle() {
  clearTimeout(settleTimer);
  settleTimer = setTimeout(finish, SETTLE_MS);
}

function handle(msg) {
  if (msg.method === 'textDocument/publishDiagnostics') {
    const { uri, diagnostics } = msg.params;
    diagnosticsByUri.set(uri, diagnostics);
    if (uri === targetUri) { sawTarget = true; scheduleSettle(); }
    else if (sawTarget) { scheduleSettle(); }
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
    return;
  }
  if (msg.id === 1 && msg.result) onInitialized();
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

function onInitialized() {
  send('initialized', {}, false);
  send('workspace/didChangeConfiguration', { settings }, false);
  for (const dest of copied) {
    const uri = pathToFileURL(dest).toString();
    const text = readFileSync(dest, 'utf8');
    send('textDocument/didOpen', {
      textDocument: { uri, languageId: 'php', version: 1, text },
    }, false);
  }
  // Fallback: if the target never publishes, finish after the global timeout.
  setTimeout(finish, TIMEOUT_MS);
}

send('initialize', {
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
  },
}, true);

server.on('exit', () => { cleanup(); });
