<?php
/**
 * Skill links and usage logging.
 *
 * A skill can have a link template, e.g. https://ovi.rdw.nl/?kenteken={kenteken}.
 * The assistant does not open that website directly but a link through this
 * server (&page=go&skill=ID&q=VALUE). The server logs the use and redirects to
 * the website, so every use is logged without an extra step for the assistant.
 */

declare(strict_types=1);

const SKILL_USAGE_FILE     = DATA_DIR . '/skill-usage.json';
const MAX_SKILL_USAGE_ROWS = 1000;
const SKILL_PLACEHOLDER    = '/\{[A-Za-z0-9_-]+\}/';

/**
 * Checks a link template and returns it with https:// added when needed, or
 * null when it is not a valid http(s) address. Empty stays empty.
 */
function normalize_link_template(string $template): ?string
{
    $template = trim($template);
    if ($template === '') {
        return '';
    }
    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $template)) {
        $template = 'https://' . $template;
    }
    // Validate with the placeholders filled in.
    return normalize_url((string) preg_replace(SKILL_PLACEHOLDER, 'x', $template)) === null ? null : $template;
}

/** The placeholder name in a template ("kenteken" for {kenteken}), or "" when there is none. */
function link_placeholder(string $template): string
{
    return preg_match(SKILL_PLACEHOLDER, $template, $m) ? trim($m[0], '{}') : '';
}

/** The website address for a query: every {placeholder} becomes the URL-encoded value. */
function skill_target_url(string $template, string $query): string
{
    return (string) preg_replace_callback(SKILL_PLACEHOLDER, fn () => rawurlencode($query), $template);
}

/** The link the assistant opens, with the placeholder still in it for the assistant to fill. */
function skill_go_link(string $privateBase, array $skill): string
{
    $placeholder = link_placeholder((string) ($skill['link'] ?? ''));
    return $privateBase . '&page=go&skill=' . $skill['id'] . ($placeholder !== '' ? '&q={' . $placeholder . '}' : '');
}

function record_skill_use(array $skill, string $query): void
{
    update_json(SKILL_USAGE_FILE, [], function (array $data) use ($skill, $query) {
        $uses = (array) ($data['uses'] ?? []);
        array_unshift($uses, [
            'skill' => (string) $skill['id'],
            'name'  => (string) ($skill['name'] ?? ''),
            'q'     => $query,
            'when'  => date('c'),
        ]);
        return ['uses' => array_slice($uses, 0, MAX_SKILL_USAGE_ROWS)];
    });
}

/** Newest first. Optionally only one skill. */
function list_skill_uses(string $skillId = '', int $limit = 0): array
{
    $uses = (array) (read_json(SKILL_USAGE_FILE)['uses'] ?? []);
    if ($skillId !== '') {
        $uses = array_values(array_filter($uses, fn ($u) => ($u['skill'] ?? '') === $skillId));
    }
    return $limit > 0 ? array_slice($uses, 0, $limit) : $uses;
}

/** Per skill id: ['total' => n, 'week' => n (last 7 days), 'last' => ISO date]. */
function skill_usage_summary(): array
{
    $summary = [];
    $weekAgo = date('c', time() - 7 * 86400);
    foreach (list_skill_uses() as $use) {
        $id = (string) ($use['skill'] ?? '');
        $summary[$id] ??= ['total' => 0, 'week' => 0, 'last' => (string) $use['when'], 'name' => (string) ($use['name'] ?? '')];
        $summary[$id]['total']++;
        if (strcmp((string) $use['when'], $weekAgo) >= 0) {
            $summary[$id]['week']++;
        }
    }
    return $summary;
}

function clear_skill_uses(): void
{
    write_json(SKILL_USAGE_FILE, ['uses' => []]);
}
