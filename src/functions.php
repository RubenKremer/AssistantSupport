<?php
/**
 * Shared bootstrap for both the assistant side and the admin panel.
 */

declare(strict_types=1);

use League\CommonMark\CommonMarkConverter;

// Unraid-style layout: everything you edit or the app writes lives in /data,
// settings live in /config. Both can be overridden for local development.
define('DATA_DIR', rtrim(getenv('DATA_DIR') ?: '/data', '/\\'));
define('CONFIG_DIR', rtrim(getenv('CONFIG_DIR') ?: '/config', '/\\'));

const SETTINGS_FILE = CONFIG_DIR . '/settings.json';
const CONTENT_DIR   = DATA_DIR . '/content';
const CONTEXT_FILE  = DATA_DIR . '/context.json';
const MEMORIES_DIR  = DATA_DIR . '/memories';
const VISITORS_FILE = DATA_DIR . '/visitors.json';
const FAILURES_FILE = DATA_DIR . '/failures.json';
const GEO_FILE      = DATA_DIR . '/geo/dbip-country-lite.mmdb';

// PHP ignores the TZ variable by itself; Unraid passes it to every container.
$tz = getenv('TZ');
date_default_timezone_set($tz && in_array($tz, timezone_identifiers_list(), true) ? $tz : 'UTC');

require __DIR__ . '/storage.php';
require __DIR__ . '/settings.php';
require __DIR__ . '/context.php';
require __DIR__ . '/memories.php';
require __DIR__ . '/countries.php';
require __DIR__ . '/security.php';
require __DIR__ . '/layout.php';

function base_url(): string
{
    $configured = rtrim((string) settings()['base_url'], '/');
    if ($configured !== '') {
        return $configured;
    }
    $scheme = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function is_valid_page_name(string $page): bool
{
    return (bool) preg_match('/^[a-z0-9-]{1,64}$/', $page);
}

function read_content(string $relativePath): string
{
    $file = CONTENT_DIR . '/' . $relativePath;
    return is_file($file) ? (string) file_get_contents($file) : '';
}

/** Replaces {{base}} (and {{pass}} when known) in Markdown. */
function fill_placeholders(string $markdown, string $baseUrl, ?string $pass): string
{
    $replacements = ['{{base}}' => $baseUrl];
    if ($pass !== null) {
        $replacements['{{pass}}'] = urlencode($pass);
    }
    return strtr($markdown, $replacements);
}

function page_title(string $markdown): string
{
    return preg_match('/^#\s+(.+)$/m', $markdown, $m) ? trim($m[1]) : 'AssistantSupport';
}

function markdown_to_html(string $markdown): string
{
    static $converter = null;
    $converter ??= new CommonMarkConverter([
        'html_input'         => 'escape',
        'allow_unsafe_links' => false,
    ]);
    return (string) $converter->convert($markdown);
}

/** Makes every heading one level smaller, so "## x" inside a section becomes "### x". */
function demote_headings(string $markdown): string
{
    return (string) preg_replace('/^(#{1,5})(?=\s)/m', '#$1', $markdown);
}

function format_date(?string $iso, bool $withTime = true): string
{
    $ts = $iso ? strtotime($iso) : false;
    if ($ts === false) {
        return '';
    }
    if (date('Y-m-d', $ts) === date('Y-m-d')) {
        return $withTime ? 'Today, ' . date('H:i', $ts) : 'Today';
    }
    return date($withTime ? 'j M Y, H:i' : 'j M Y', $ts);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function send_security_headers(): void
{
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer'); // never leak ?pass= to other sites
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
}
