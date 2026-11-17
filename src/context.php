<?php
/**
 * The assistant's context in /data/context.json: backstory, skills and links.
 */

declare(strict_types=1);

const TONES     = ['Concise', 'Friendly', 'Formal', 'Playful'];
const LANGUAGES = ['Match the user', 'English', 'Nederlands'];

function load_context(): array
{
    return normalize_context(read_json(CONTEXT_FILE));
}

/** @param callable(array): array $change */
function update_context(callable $change): void
{
    update_json(CONTEXT_FILE, [], function (array $stored) use ($change) {
        $context = $change(normalize_context($stored));
        $context['updated'] = date('c');
        return $context;
    });
}

function normalize_context(array $c): array
{
    $assistant = (array) ($c['assistant'] ?? []);
    return [
        'assistant' => [
            'name'     => (string) ($assistant['name'] ?? ''),
            'role'     => (string) ($assistant['role'] ?? ''),
            'tone'     => in_array($assistant['tone'] ?? '', TONES, true) ? $assistant['tone'] : TONES[0],
            'language' => in_array($assistant['language'] ?? '', LANGUAGES, true) ? $assistant['language'] : LANGUAGES[0],
        ],
        'backstory' => (string) ($c['backstory'] ?? ''),
        'skills'    => array_values(array_filter((array) ($c['skills'] ?? []), 'is_array')),
        'links'     => array_values(array_filter((array) ($c['links'] ?? []), 'is_array')),
        'updated'   => $c['updated'] ?? null,
    ];
}

function find_by_id(array $items, string $id): ?array
{
    foreach ($items as $item) {
        if (($item['id'] ?? '') === $id) {
            return $item;
        }
    }
    return null;
}

/** Accepts "example.com/x" too; returns a full http(s) URL or null. */
function normalize_url(string $url): ?string
{
    $url = trim($url);
    if ($url !== '' && !preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
        $url = 'https://' . $url;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
        return null;
    }
    return $url;
}

function url_without_scheme(string $url): string
{
    return (string) preg_replace('~^https?://~i', '', rtrim($url, '/'));
}
