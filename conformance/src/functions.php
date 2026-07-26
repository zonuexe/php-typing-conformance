<?php

declare(strict_types=1);

/**
 * Escaping and inline-markup helpers for the report templates.
 *
 * Global on purpose: a template calls these on nearly every line, and `h($x)`
 * reads where `$this->html->esc($x)` does not. They are text-to-markup
 * conversions only — nothing here knows about test results or analyzers.
 *
 * Autoloaded through composer.json's `autoload.files`.
 */

/**
 * Escape text for HTML.
 */
function h(string $text): string
{
    return htmlspecialchars($text);
}

/**
 * Escape text, then turn `code` spans into <code> and linkify bare URLs.
 */
function h_inline(string $text): string
{
    $escaped = htmlspecialchars($text);

    $escaped = preg_replace_callback(
        '/`([^`]+)`/',
        static fn (array $matches): string => '<code>' . $matches[1] . '</code>',
        $escaped,
    ) ?? $escaped;

    return h_linkify($escaped);
}

/**
 * Escape text and linkify bare URLs, leaving `code` spans as written.
 */
function h_linked(string $text): string
{
    return h_linkify(htmlspecialchars($text));
}

/**
 * A cell value, optionally carrying a hover note.
 *
 * Without a note this is plain escaped text, so a cell never picks up the
 * dotted "there is more here" underline unless there really is more.
 */
function h_noted(string $text, string $note): string
{
    if ($note === '') {
        return htmlspecialchars($text);
    }

    return sprintf(
        '<span class="noted" title="%s">%s</span>',
        htmlspecialchars($note),
        htmlspecialchars($text),
    );
}

/**
 * Wrap a calendar year for machine-readable markup.
 *
 * HTML has no &lt;date&gt;; years and dates use &lt;time datetime&gt;.
 */
function h_year(string $year): string
{
    return sprintf(
        '<time datetime="%s">%s</time>',
        htmlspecialchars($year),
        htmlspecialchars($year),
    );
}

/**
 * Render "version (YYYY-MM-DD)" with the date as &lt;time datetime&gt;.
 *
 * The version alone when no release date is recorded.
 */
function h_release(string $version, string $date = ''): string
{
    if ($date === '') {
        return htmlspecialchars($version);
    }

    return sprintf(
        '%s (<time datetime="%s">%s</time>)',
        htmlspecialchars($version),
        htmlspecialchars($date),
        htmlspecialchars($date),
    );
}

/**
 * Turn bare URLs in already-escaped text into links.
 *
 * Internal to the helpers above; templates call h_inline() or h_linked().
 */
function h_linkify(string $escaped): string
{
    $linked = preg_replace_callback(
        '/https?:\/\/[^\s<]+/i',
        static function (array $matches): string {
            $url = $matches[0];

            // Keep trailing sentence punctuation out of the link target.
            $trailing = '';
            while ($url !== '' && str_contains('.,;:)]}', substr($url, -1))) {
                $trailing = substr($url, -1) . $trailing;
                $url = substr($url, 0, -1);
            }

            // Shorten GitHub issue/PR links to `<repo>#<number>`.
            $label = $url;
            if (preg_match('~^https?://github\.com/[^/\s]+/([^/\s]+)/(?:issues|pull)/(\d+)(?:[/#?][^\s<]*)?$~i', $url, $ref) === 1) {
                $label = htmlspecialchars($ref[1] . '#' . $ref[2]);
            }

            return sprintf(
                '<a href="%s" target="_blank" rel="noopener">%s</a>%s',
                $url,
                $label,
                $trailing,
            );
        },
        $escaped,
    );

    return $linked ?? $escaped;
}
