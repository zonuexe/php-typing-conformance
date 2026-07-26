<?php

declare(strict_types=1);

use Conformance\Reporting\Report;

/**
 * Router for PHP's built-in server, so the report can be read locally the way
 * it is published.
 *
 *     php -S localhost:8080 conformance/src/router.php
 *
 * or `make serve`, which is the same command.
 *
 * Every page is rendered per request, straight out of the committed
 * results/<tool>/*.toml data, the test sources and the templates. Nothing is
 * read from -- or written to -- the generated report: editing a template or a
 * metadata class shows up on reload, and a checkout that has never run
 * `make render-report-html` serves exactly the same site as one that has.
 *
 * The document root is left at the repository root rather than pointed at
 * conformance/results with `-t`: the built-in server chdirs into its document
 * root, which would leave this file unreachable at a relative path. It never
 * matters, because no request is ever handed back to the server to serve as a
 * file -- the repository around this script stays unreachable.
 */

const AUTOLOAD_FILE = __DIR__ . '/../vendor/autoload.php';

if (!is_file(AUTOLOAD_FILE)) {
    notice(
        'The runner&rsquo;s dependencies are not installed yet.',
        'composer install --working-dir=conformance',
    );

    return true;
}

require_once AUTOLOAD_FILE;

$request_uri = filter_var($_SERVER['REQUEST_URI'] ?? null);
assert($request_uri !== false);
// parse_url() answers null as well as false for a path it cannot give back.
$raw_path = parse_url($request_uri, PHP_URL_PATH);
assert(is_string($raw_path));
$path = urldecode($raw_path);

$report = Report::fromRootDir(dirname(__DIR__));

// `/` and `/index.html` are one page, as they are on the published site.
if ($path === '/' || $path === '/index.html') {
    serve('text/html; charset=UTF-8', $report->index());

    return true;
}

if ($path === '/report.css') {
    serve('text/css; charset=UTF-8', $report->stylesheet());

    return true;
}

if (preg_match('~^/tests/([^/]+)\.html$~', $path, $matches) === 1) {
    $detail = $report->detail($matches[1]);

    if ($detail !== null) {
        serve('text/html; charset=UTF-8', $detail);

        return true;
    }
}

notFound($path);

return true;

function serve(string $contentType, string $body): void
{
    header('Content-Type: ' . $contentType);
    // The report is rebuilt per request, so a cached copy would only hide the
    // edit being previewed.
    header('Cache-Control: no-store');
    echo $body;
}

function notFound(string $path): void
{
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    printf(
        '<!DOCTYPE html><meta charset="UTF-8"><title>Not found</title>'
        . '<p>No such page: <code>%s</code></p><p><a href="/">All results</a></p>',
        htmlspecialchars($path),
    );
}

function notice(string $problem, string $command): void
{
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    printf(
        '<!DOCTYPE html><meta charset="UTF-8"><title>Report not available</title>'
        . '<p>%s</p><p>Run this, then reload:</p><pre>%s</pre>',
        $problem,
        htmlspecialchars($command),
    );
}
