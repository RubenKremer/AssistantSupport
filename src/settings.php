<?php
/**
 * Settings in /config/settings.json, edited from the admin panel.
 */

declare(strict_types=1);

const DEFAULT_SETTINGS = [
    'passphrase_hash'   => '',
    'passphrase_set_at' => null,
    'base_url'          => '',
    'max_attempts'      => 5,
    'lockout_minutes'   => 15,
    'updated'           => null,
    'security'          => [
        'clients'   => ['mode' => 'monitor', 'rules' => []],     // rules: [{client, min_version}]
        'countries' => ['mode' => 'monitor', 'allowed' => []],   // allowed: ["NL", "US", ...]
    ],
];

const MIN_PASSPHRASE_WORDS = 3;

function settings(): array
{
    $stored = read_json(SETTINGS_FILE);
    $merged = array_replace_recursive(DEFAULT_SETTINGS, $stored);
    // Lists must come from the file as a whole, not merged index by index.
    $merged['security']['clients']['rules']     = array_values((array) ($stored['security']['clients']['rules'] ?? []));
    $merged['security']['countries']['allowed'] = array_values((array) ($stored['security']['countries']['allowed'] ?? []));
    return $merged;
}

/** @param callable(array): array $change */
function update_settings(callable $change): void
{
    update_json(SETTINGS_FILE, [], fn (array $stored) => $change(array_replace_recursive(DEFAULT_SETTINGS, $stored)));
}

// ---------------------------------------------------------------------------
// Passphrase: a spoken sentence of at least three words
// ---------------------------------------------------------------------------

/**
 * Voice assistants are sloppy with spaces, capitals and punctuation, so
 * "The purple elephant, sings!" and "the-purple-elephant-sings" are the same.
 */
function normalize_passphrase(string $value): string
{
    $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($value), -1, PREG_SPLIT_NO_EMPTY);
    return implode('-', $words);
}

function passphrase_word_count(string $value): int
{
    $normalized = normalize_passphrase($value);
    return $normalized === '' ? 0 : count(explode('-', $normalized));
}

/** Stores only a one-way hash. Returns an error message, or null when saved. */
function set_passphrase(string $sentence): ?string
{
    if (passphrase_word_count($sentence) < MIN_PASSPHRASE_WORDS) {
        return 'The passphrase must be a sentence of at least ' . MIN_PASSPHRASE_WORDS . ' words.';
    }
    // SHA-256 first, because bcrypt ignores everything after 72 bytes.
    $hash = password_hash(hash('sha256', normalize_passphrase($sentence)), PASSWORD_DEFAULT);
    update_settings(function (array $s) use ($hash) {
        $s['passphrase_hash']   = $hash;
        $s['passphrase_set_at'] = date('c');
        return $s;
    });
    return null;
}

function passphrase_is_set(): bool
{
    return settings()['passphrase_hash'] !== '';
}

function passphrase_matches(string $normalized): bool
{
    $hash = settings()['passphrase_hash'];
    if ($hash === '' || $normalized === '') {
        return false; // fail closed when no passphrase is configured
    }
    return password_verify(hash('sha256', $normalized), $hash);
}

// ---------------------------------------------------------------------------
// Lockout after too many wrong passphrases
// ---------------------------------------------------------------------------

function max_attempts(): int
{
    return max(1, (int) settings()['max_attempts']);
}

function lockout_minutes(): int
{
    return max(1, (int) settings()['lockout_minutes']);
}

/** {"failures": [timestamps of recent wrong attempts], "locked_until": timestamp} */
function read_lockout_state(): array
{
    $state  = read_json(FAILURES_FILE);
    $cutoff = time() - lockout_minutes() * 60;
    return [
        'failures'     => array_values(array_filter((array) ($state['failures'] ?? []), fn ($t) => is_int($t) && $t > $cutoff)),
        'locked_until' => (int) ($state['locked_until'] ?? 0),
    ];
}

function is_locked_out(): bool
{
    return read_lockout_state()['locked_until'] > time();
}

/** Remembers a wrong attempt; the last allowed one starts a full lockout. */
function record_failure(): void
{
    $state = read_lockout_state();
    $state['failures'][] = time();
    if (count($state['failures']) >= max_attempts()) {
        $state = ['failures' => [], 'locked_until' => time() + lockout_minutes() * 60];
    }
    write_json(FAILURES_FILE, $state);
}

function clear_lockout(): void
{
    @unlink(FAILURES_FILE);
}
