<?php

declare(strict_types=1);

namespace Conformance\Lsp;

use CurlHandle;
use JsonException;
use RuntimeException;

/**
 * A minimal MCP client for the server PhpStorm runs inside the IDE
 * (Settings | Tools | MCP Server), spoken over its streamable HTTP endpoint.
 *
 * Just enough protocol for the probes: the initialize handshake, tools/list,
 * and tools/call. The server tracks a session per `mcp-session-id` header and
 * forgets it once the connection that opened it closes, so every request goes
 * through one curl handle that keeps the connection alive — and a request
 * the server answers with 404, because the connection was dropped anyway
 * mid-run, is retried once on a fresh session. A reply arrives as plain JSON
 * or as a server-sent-event stream depending on the request; both are read,
 * and notifications get an empty 202.
 */
final class PhpStormMcpClient
{
    private CurlHandle $curl;

    private ?string $session = null;

    private int $nextId = 0;

    /** @var array{name: string, version: string} */
    public readonly array $serverInfo;

    public function __construct(private readonly string $url)
    {
        $this->curl = curl_init();

        $info = $this->handshake();
        $this->serverInfo = [
            'name' => (string) ($info['name'] ?? ''),
            'version' => (string) ($info['version'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed> the server's serverInfo
     */
    private function handshake(): array
    {
        $this->session = null;
        $result = $this->request('initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'php-typing-conformance', 'version' => '1'],
        ]);
        $this->send(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);

        return is_array($result['serverInfo'] ?? null) ? $result['serverInfo'] : [];
    }

    /**
     * @return list<string> the names of the tools the server exposes
     */
    public function toolNames(): array
    {
        $result = $this->request('tools/list', (object) []);
        $names = [];
        foreach (is_array($result['tools'] ?? null) ? $result['tools'] : [] as $tool) {
            if (is_array($tool) && is_string($tool['name'] ?? null)) {
                $names[] = $tool['name'];
            }
        }
        sort($names);

        return $names;
    }

    /**
     * Call one tool. The answer is the tool's text content, decoded when it is
     * JSON — PhpStorm's tools mostly answer a JSON document as text.
     *
     * @param array<string, mixed> $arguments
     * @return array{error: bool, text: string, json: mixed}
     */
    public function call(string $tool, array $arguments): array
    {
        $result = $this->request('tools/call', ['name' => $tool, 'arguments' => (object) $arguments]);

        $text = '';
        foreach (is_array($result['content'] ?? null) ? $result['content'] : [] as $part) {
            if (is_array($part) && is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }

        try {
            $json = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $json = null;
        }

        return ['error' => ($result['isError'] ?? false) === true, 'text' => $text, 'json' => $json];
    }

    /**
     * @param array<string, mixed>|object $params
     * @return array<string, mixed>
     */
    private function request(string $method, array|object $params): array
    {
        $message = ['jsonrpc' => '2.0', 'id' => ++$this->nextId, 'method' => $method, 'params' => $params];
        try {
            $reply = $this->send($message);
        } catch (SessionLost) {
            // Never thrown for initialize itself, which carries no session.
            $this->handshake();
            $reply = $this->send($message);
        }

        if ($reply === null) {
            throw new RuntimeException("{$method}: no reply from {$this->url}");
        }
        if (isset($reply['error'])) {
            throw new RuntimeException("{$method}: " . json_encode($reply['error']));
        }

        return is_array($reply['result'] ?? null) ? $reply['result'] : [];
    }

    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>|null the JSON-RPC reply, null for a notification
     */
    private function send(array $message): ?array
    {
        $headers = ['Content-Type: application/json', 'Accept: application/json, text/event-stream'];
        if ($this->session !== null) {
            $headers[] = 'Mcp-Session-Id: ' . $this->session;
        }

        curl_setopt_array($this->curl, [
            CURLOPT_URL => $this->url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => (string) json_encode($message, JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 600,
        ]);

        $raw = curl_exec($this->curl);
        if (!is_string($raw)) {
            throw new RuntimeException(sprintf('Cannot reach PhpStorm\'s MCP server at %s: %s', $this->url, curl_error($this->curl)));
        }

        $headerSize = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        $head = substr($raw, 0, $headerSize);
        $body = trim(substr($raw, $headerSize));

        if (preg_match('/^mcp-session-id:\s*(\S+)/mi', $head, $match) === 1) {
            $this->session = $match[1];
        }

        $status = curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);
        if ($status === 202) {
            return null;
        }
        if ($status === 404 && $this->session !== null) {
            throw new SessionLost($body);
        }
        if ($status !== 200) {
            throw new RuntimeException(sprintf('PhpStorm\'s MCP server answered HTTP %d: %s', $status, substr($body, 0, 300)));
        }

        if (stripos($head, 'text/event-stream') !== false) {
            $reply = null;
            foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
                if (!str_starts_with($line, 'data:')) {
                    continue;
                }
                $event = json_decode(trim(substr($line, 5)), true);
                if (is_array($event) && isset($event['id'])) {
                    $reply = $event;
                }
            }

            return $reply;
        }

        $reply = json_decode($body, true);

        return is_array($reply) ? $reply : null;
    }
}
