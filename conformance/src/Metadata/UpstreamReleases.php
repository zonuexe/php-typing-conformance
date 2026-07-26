<?php

declare(strict_types=1);

namespace Conformance\Metadata;

use function is_array;
use function is_string;
use function json_decode;
use function ltrim;
use function stream_context_create;
use function substr;

/**
 * Read the newest release a project has published.
 *
 * Every registry answers the same two questions in its own shape; this is the
 * only place that knows which key means what. Anything unreachable or
 * unparseable comes back as null: a release feed being down is not a reason
 * for the run to fail.
 */
final class UpstreamReleases
{
    private const TIMEOUT_SECONDS = 15;

    /** GitHub rejects requests without one. */
    private const USER_AGENT = 'php-typing-conformance';

    /** Why the last lookup came back empty, for the caller to report. */
    private ?string $failure = null;

    public function latest(ReleaseFeed $feed): ?Release
    {
        $this->failure = null;
        $payload = $this->fetch($feed->url(), $feed->kind === ReleaseFeedKind::GitHub);

        if ($payload === null) {
            return null;
        }

        return match ($feed->kind) {
            ReleaseFeedKind::GitHub => $this->fromGitHub($payload),
            ReleaseFeedKind::Npm => $this->fromNpm($payload),
            ReleaseFeedKind::Packagist => $this->fromPackagist($payload, $feed->id),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function fromGitHub(array $payload): ?Release
    {
        $tag = $payload['tag_name'] ?? null;
        $published = $payload['published_at'] ?? null;

        return is_string($tag) && is_string($published)
            ? $this->release($tag, $published)
            : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function fromNpm(array $payload): ?Release
    {
        $tags = $payload['dist-tags'] ?? null;
        $version = is_array($tags) ? ($tags['latest'] ?? null) : null;
        $times = $payload['time'] ?? null;
        $published = is_array($times) && is_string($version) ? ($times[$version] ?? null) : null;

        return is_string($version) && is_string($published)
            ? $this->release($version, $published)
            : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function fromPackagist(array $payload, string $package): ?Release
    {
        $packages = $payload['packages'] ?? null;
        $versions = is_array($packages) ? ($packages[$package] ?? null) : null;
        // Newest first, which is how the v2 metadata is ordered.
        $newest = is_array($versions) ? ($versions[0] ?? null) : null;

        if (!is_array($newest)) {
            return null;
        }

        $version = $newest['version'] ?? null;
        $published = $newest['time'] ?? null;

        return is_string($version) && is_string($published)
            ? $this->release($version, $published)
            : null;
    }

    /**
     * Tags are spelled with and without a leading `v` across projects, and the
     * table shows the number alone; timestamps are ISO 8601 of one shape or
     * another, and the table shows the calendar date.
     */
    private function release(string $version, string $publishedAt): ?Release
    {
        $date = substr($publishedAt, 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
            ? new Release(ltrim($version, 'vV'), $date)
            : null;
    }

    public function failure(): ?string
    {
        return $this->failure;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $url, bool $authenticate): ?array
    {
        $headers = 'User-Agent: ' . self::USER_AGENT . "\r\nAccept: application/json\r\n";

        // Sixty requests an hour unauthenticated is not many when a run asks
        // about ten repositories; a token raises it to thousands. Read from
        // the environment so none is ever written down here.
        $token = $authenticate ? (getenv('GITHUB_TOKEN') ?: getenv('GH_TOKEN')) : false;
        if (is_string($token) && $token !== '') {
            $headers .= 'Authorization: Bearer ' . $token . "\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => self::TIMEOUT_SECONDS,
                'header' => $headers,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            $this->failure = 'unreachable';

            return null;
        }

        $payload = json_decode($body, true);

        if (!is_array($payload)) {
            $this->failure = 'unreadable response';

            return null;
        }

        $status = $this->statusOf($http_response_header ?? []);

        if ($status !== null && $status >= 400) {
            $message = $payload['message'] ?? '';
            $this->failure = sprintf('HTTP %d%s', $status, is_string($message) && $message !== '' ? ': ' . $this->firstSentence($message) : '');

            return null;
        }

        return $payload;
    }

    /**
     * @param list<string> $headers
     */
    private function statusOf(array $headers): ?int
    {
        return preg_match('~^HTTP/\S+\s+(\d{3})~', $headers[0] ?? '', $matches) === 1
            ? (int) $matches[1]
            : null;
    }

    private function firstSentence(string $message): string
    {
        $end = strpos($message, '. ');

        return $end === false ? $message : substr($message, 0, $end + 1);
    }
}
