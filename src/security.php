<?php
/**
 * Access rules for the assistant side (port 80):
 *   - client whitelist: simplified user agent ("Safari 18", "Grok") + minimum version
 *   - country allowlist: based on the requesting IP address (DB-IP Lite)
 * Each has a mode: "monitor" only logs what would be blocked, "enforce" blocks.
 */

declare(strict_types=1);

use MaxMind\Db\Reader;

const MAX_VISITOR_ROWS = 300;

/**
 * Simplified client names, checked in this order. AI fetchers first, because
 * they often also contain browser tokens. The number in group 1 is the version.
 */
const CLIENT_PATTERNS = [
    'Grok'             => '~(?:Grok|xAI)[\w-]*(?:/(\d+))?~i',
    'ChatGPT'          => '~(?:ChatGPT-User|GPTBot|OAI-SearchBot)(?:/(\d+))?~i',
    'Claude'           => '~(?:Claude-User|ClaudeBot|Claude-SearchBot)(?:/(\d+))?~i',
    'Perplexity'       => '~Perplexity[\w-]*(?:/(\d+))?~i',
    'Gemini'           => '~Google-(?:Extended|Gemini)[\w-]*(?:/(\d+))?~i',
    'Googlebot'        => '~Googlebot(?:/(\d+))?~i',
    'Bingbot'          => '~bingbot(?:/(\d+))?~i',
    'curl'             => '~^curl/(\d+)~i',
    'Wget'             => '~^Wget/(\d+)~i',
    'Python'           => '~python-(?:requests|urllib|httpx)(?:/(\d+))?~i',
    'Go HTTP client'   => '~Go-http-client/(\d+)~',
    'Tesla browser'    => '~Tesla/(\d+)~',
    'Edge'             => '~Edg(?:e|A|iOS)?/(\d+)~',
    'Opera'            => '~(?:OPR|Opera)/(\d+)~',
    'Samsung Internet' => '~SamsungBrowser/(\d+)~',
    'Firefox'          => '~(?:Firefox|FxiOS)/(\d+)~',
    'Chrome'           => '~(?:CriOS|Chromium|Chrome)/(\d+)~',
    'Safari'           => '~Version/(\d+)[\d.]*(?: Mobile/\S+)? Safari/~',
    'Other bot'        => '~bot|crawler|spider|fetch~i',
];

/** All names the admin can pick for a rule. */
function client_names(): array
{
    return array_merge(array_keys(CLIENT_PATTERNS), ['Unknown', 'No user agent']);
}

/** @return array{0:string,1:?int} [client name, major version or null] */
function parse_user_agent(string $ua): array
{
    if (trim($ua) === '') {
        return ['No user agent', null];
    }
    foreach (CLIENT_PATTERNS as $name => $pattern) {
        if (preg_match($pattern, $ua, $m)) {
            return [$name, isset($m[1]) && $m[1] !== '' ? (int) $m[1] : null];
        }
    }
    return ['Unknown', null];
}

function is_public_ip(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

/**
 * The visitor's IP. Behind a reverse proxy on your own network the direct
 * connection comes from a private address; only then is X-Forwarded-For
 * trusted (its last public address, which the proxy added).
 */
function client_ip(): string
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if ($remote === '' || is_public_ip($remote)) {
        return $remote;
    }
    $forwarded = array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));
    foreach (array_reverse($forwarded) as $ip) {
        if (is_public_ip($ip)) {
            return $ip;
        }
    }
    return $remote;
}

function geo_database_available(): bool
{
    return is_file(GEO_FILE);
}

/** ISO country code, "LAN" for local addresses, or "" when unknown. */
function country_for_ip(string $ip): string
{
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return '';
    }
    if (!is_public_ip($ip)) {
        return 'LAN';
    }
    if (!geo_database_available()) {
        return '';
    }
    static $reader = null;
    try {
        $reader ??= new Reader(GEO_FILE);
        $record = $reader->get($ip);
        return strtoupper((string) ($record['country']['iso_code'] ?? ''));
    } catch (Throwable) {
        return '';
    }
}

/** No rules = every client is allowed. */
function client_allowed(string $client, ?int $version, array $rules): bool
{
    if ($rules === []) {
        return true;
    }
    foreach ($rules as $rule) {
        if (strcasecmp((string) ($rule['client'] ?? ''), $client) === 0 && (int) ($version ?? 0) >= (int) ($rule['min_version'] ?? 0)) {
            return true;
        }
    }
    return false;
}

/** No countries = every country is allowed. Local and unknown addresses always pass. */
function country_allowed(string $country, array $allowed): bool
{
    return $allowed === [] || $country === 'LAN' || $country === '' || in_array($country, $allowed, true);
}

/**
 * Checks the current request against the rules and records it in the visitor
 * log. Returns false when the request must be blocked.
 */
function check_access(): bool
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    [$client, $version] = parse_user_agent($ua);
    $country = country_for_ip(client_ip());
    $rules   = settings()['security'];

    $clientOk  = client_allowed($client, $version, $rules['clients']['rules']);
    $countryOk = country_allowed($country, $rules['countries']['allowed']);
    $blocked   = (!$clientOk && $rules['clients']['mode'] === 'enforce')
              || (!$countryOk && $rules['countries']['mode'] === 'enforce');
    $status    = $blocked ? 'blocked' : ($clientOk && $countryOk ? 'allowed' : 'would_block');

    record_visit($client, $version, $country, $ua, $status);
    return !$blocked;
}

function record_visit(string $client, ?int $version, string $country, string $ua, string $status): void
{
    $key = $client . '|' . ($version ?? '') . '|' . $country;
    update_json(VISITORS_FILE, [], function (array $data) use ($key, $client, $version, $country, $ua, $status) {
        $visitors = (array) ($data['visitors'] ?? []);
        $row = $visitors[$key] ?? ['client' => $client, 'version' => $version, 'country' => $country, 'first' => date('c'), 'count' => 0];
        $row['last']   = date('c');
        $row['count']  = (int) $row['count'] + 1;
        $row['status'] = $status;
        $row['ua']     = mb_substr($ua, 0, 300);
        $visitors[$key] = $row;

        // Keep the list short: drop the rows that were seen longest ago.
        if (count($visitors) > MAX_VISITOR_ROWS) {
            uasort($visitors, fn ($a, $b) => strcmp($b['last'], $a['last']));
            $visitors = array_slice($visitors, 0, MAX_VISITOR_ROWS, true);
        }
        return ['visitors' => $visitors];
    });
}

/** Newest first. */
function list_visitors(): array
{
    $visitors = array_values((array) (read_json(VISITORS_FILE)['visitors'] ?? []));
    usort($visitors, fn ($a, $b) => strcmp((string) $b['last'], (string) $a['last']));
    return $visitors;
}

function clear_visitors(): void
{
    write_json(VISITORS_FILE, ['visitors' => []]);
}
