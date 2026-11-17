<?php
/**
 * Memories: one JSON file per memory in /data/memories/.
 * The file name starts with the date and time, so sorting by name = by age.
 */

declare(strict_types=1);

const MAX_TITLE_LENGTH   = 120;
const MAX_SUMMARY_LENGTH = 2000;
const MAX_POINTS         = 10;

function is_valid_memory_id(string $id): bool
{
    return (bool) preg_match('/^\d{8}-\d{6}-[a-f0-9]{6}$/', $id);
}

function memory_file(string $id): string
{
    return MEMORIES_DIR . "/$id.json";
}

/** Splits "first|second" or one point per line into a clean list. */
function parse_points(string $raw): array
{
    $points = array_map(fn ($p) => clean_line($p, 300), preg_split('/[|\n;]+/', $raw) ?: []);
    return array_slice(array_values(array_filter($points, fn ($p) => $p !== '')), 0, MAX_POINTS);
}

/** A title from the first words of the summary, for when none was given. */
function title_from_summary(string $summary): string
{
    $words = preg_split('/\s+/', $summary, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $title = implode(' ', array_slice($words, 0, 8));
    return count($words) > 8 ? $title . '…' : $title;
}

function save_new_memory(string $title, string $summary, array $points): array
{
    $memory = [
        'id'      => date('Ymd-His') . '-' . bin2hex(random_bytes(3)),
        'created' => date('c'),
        'title'   => $title,
        'summary' => $summary,
        'points'  => $points,
    ];
    write_json(memory_file($memory['id']), $memory);
    return $memory;
}

function load_memory(string $id): ?array
{
    if (!is_valid_memory_id($id) || !is_file(memory_file($id))) {
        return null;
    }
    $m = read_json(memory_file($id));
    return [
        'id'      => $id,
        'created' => (string) ($m['created'] ?? ''),
        'title'   => (string) ($m['title'] ?? ''),
        'summary' => (string) ($m['summary'] ?? ''),
        'points'  => array_values(array_map('strval', (array) ($m['points'] ?? []))),
    ];
}

function update_memory(string $id, string $title, string $summary, array $points): bool
{
    $memory = load_memory($id);
    if ($memory === null) {
        return false;
    }
    write_json(memory_file($id), array_merge($memory, ['title' => $title, 'summary' => $summary, 'points' => $points]));
    return true;
}

function delete_memory(string $id): void
{
    if (is_valid_memory_id($id)) {
        @unlink(memory_file($id));
    }
}

/** Newest first. Optionally only memories containing $query (case-insensitive). */
function list_memories(string $query = '', int $limit = 0): array
{
    $files = glob(MEMORIES_DIR . '/*.json') ?: [];
    rsort($files);

    $result = [];
    foreach ($files as $file) {
        $memory = load_memory(basename($file, '.json'));
        if ($memory === null) {
            continue;
        }
        if ($query !== '') {
            $haystack = mb_strtolower($memory['title'] . ' ' . $memory['summary'] . ' ' . implode(' ', $memory['points']));
            if (!str_contains($haystack, mb_strtolower($query))) {
                continue;
            }
        }
        $result[] = $memory;
        if ($limit > 0 && count($result) >= $limit) {
            break;
        }
    }
    return $result;
}

function count_memories(): int
{
    return count(glob(MEMORIES_DIR . '/*.json') ?: []);
}

/**
 * Assistants sometimes open the same "remember" URL twice. Returns the
 * existing memory when an identical one was saved in the last 10 minutes.
 */
function find_recent_duplicate(string $title, string $summary): ?array
{
    foreach (list_memories('', 5) as $memory) {
        if (strtotime($memory['created']) < time() - 600) {
            break;
        }
        if ($memory['title'] === $title && $memory['summary'] === $summary) {
            return $memory;
        }
    }
    return null;
}
