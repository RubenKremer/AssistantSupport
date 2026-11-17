<?php
/**
 * JSON file storage. Every write goes to a temporary file first and is then
 * renamed, so a crash never leaves a half-written file behind.
 */

declare(strict_types=1);

function read_json(string $file, array $default = []): array
{
    if (!is_file($file)) {
        return $default;
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : $default;
}

function write_json(string $file, array $data): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    rename($tmp, $file);
}

/**
 * Read-change-write under an exclusive lock, so two requests at the same
 * moment can't overwrite each other's changes.
 */
function update_json(string $file, array $default, callable $change): array
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $lock = fopen($dir . '/.' . basename($file) . '.lock', 'c');
    flock($lock, LOCK_EX);
    try {
        $data = $change(read_json($file, $default));
        write_json($file, $data);
        return $data;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function new_id(): string
{
    return bin2hex(random_bytes(6));
}

/** Trims, collapses whitespace and limits the length of a single-line value. */
function clean_line(string $value, int $max): string
{
    return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $value)), 0, $max);
}

/** Like clean_line, but keeps line breaks (for Markdown and longer text). */
function clean_text(string $value, int $max): string
{
    return mb_substr(trim(str_replace("\r\n", "\n", $value)), 0, $max);
}
