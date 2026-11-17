<?php
/**
 * Pages the AI assistant reads after giving the right passphrase.
 * Everything it needs is on one page, so it only has to open one URL.
 */

declare(strict_types=1);

/** How many memories the private page shows; older ones are in the history. */
const RECENT_MEMORIES_ON_CONTEXT = 5;

function memory_item_html(array $m): string
{
    $html = '<li><strong>' . e(format_date($m['created'])) . ' — ' . e($m['title']) . '</strong>'
        . markdown_to_html(demote_headings($m['summary'], 3));
    if ($m['points'] !== []) {
        $html .= '<ul>';
        foreach ($m['points'] as $point) {
            $html .= '<li>' . e($point) . '</li>';
        }
        $html .= '</ul>';
    }
    return $html . '</li>';
}

function remember_example(string $privateBase): string
{
    return $privateBase . '&page=remember&title=Short+title&summary=What+happened+and+what+to+remember&points=First+point|Second+point';
}

function render_context_page(string $privateBase): string
{
    $c = load_context();
    $a = $c['assistant'];

    $html = '<p>Private context</p><h1>Welcome back</h1>'
        . '<p>The passphrase was correct. Below is everything you need to know about your user and how to help them. Read it completely before you answer.</p>';

    // Who you are
    $facts = '';
    foreach (['Name' => $a['name'], 'Role' => $a['role'], 'Tone' => $a['tone'], 'Language' => $a['language']] as $label => $value) {
        if ($value !== '') {
            $facts .= '<li><strong>' . $label . ':</strong> ' . e($value) . '</li>';
        }
    }
    $html .= '<h2>Who you are</h2><ul>' . $facts . '</ul>';

    // Backstory
    if (trim($c['backstory']) !== '') {
        $html .= '<h2>Backstory</h2>' . markdown_to_html(demote_headings($c['backstory']));
    }

    // Skills (enabled only)
    $skills = array_filter($c['skills'], fn ($s) => !empty($s['enabled']));
    if ($skills !== []) {
        $html .= '<h2>Skills</h2><p>Things you can and may do for your user. Follow the instructions of a skill when it applies.</p>';
        foreach ($skills as $s) {
            $tag   = ($s['tag'] ?? '') !== '' ? ' <small>(' . e($s['tag']) . ')</small>' : '';
            $html .= '<h3>' . e($s['name'] ?? '') . $tag . '</h3>'
                . markdown_to_html(demote_headings((string) ($s['description'] ?? ''), 2));
            if (($s['link'] ?? '') !== '') {
                $placeholder = link_placeholder((string) $s['link']);
                $html .= '<p><strong>Link for this skill:</strong> open this link'
                    . ($placeholder !== '' ? ', with <code>{' . e($placeholder) . '}</code> replaced by the value you look up' : '')
                    . '. It takes you straight to the right page.</p>'
                    . '<pre><code>' . e(skill_go_link($privateBase, $s)) . '</code></pre>';
            }
        }
    }

    // Links
    if ($c['links'] !== []) {
        $html .= '<h2>Links</h2><p>Places your user uses often. You may open them when they are relevant.</p><ul>';
        foreach ($c['links'] as $l) {
            $html .= '<li><a href="' . e($l['url'] ?? '') . '">' . e($l['title'] ?? '') . '</a> — <code>' . e($l['url'] ?? '') . '</code></li>';
        }
        $html .= '</ul>';
    }

    // Recent memories: only the newest few; older ones are one link away.
    $recent = list_memories('', RECENT_MEMORIES_ON_CONTEXT);
    $older  = max(0, count_memories() - count($recent));
    $html .= '<h2>Recent memories</h2>';
    if ($recent === []) {
        $html .= '<p>No memories saved yet.</p>';
    } else {
        $html .= '<p>The ' . count($recent) . ' most recent things you saved, newest first.</p><ul>';
        foreach ($recent as $m) {
            $html .= memory_item_html($m);
        }
        $html .= '</ul>';
    }

    // Older memories
    $html .= '<h2>Older memories</h2>'
        . ($older > 0
            ? '<p>There ' . ($older === 1 ? 'is 1 older memory' : 'are ' . $older . ' older memories') . '. Open the history, organised per week, when the conversation needs them:</p>'
            : '<p>There are no older memories yet. Later, the history per week is here:</p>')
        . '<pre><code>' . e($privateBase . '&page=history') . '</code></pre>'
        . '<p>Or search all memories by keyword. Replace <code>word</code> with what you are looking for.</p>'
        . '<pre><code>' . e($privateBase . '&page=memories&q=word') . '</code></pre>'
        . '<h2>Save a memory</h2>'
        . '<p>When your user tells you something worth keeping, or at the end of a conversation, save a short memory. Use <code>+</code> instead of spaces and separate key points with <code>|</code>. Confirm briefly to your user that it was saved.</p>'
        . '<pre><code>' . e(remember_example($privateBase)) . '</code></pre>';

    return $html;
}

/** "2026-W46" for a timestamp (ISO week: Monday to Sunday). */
function week_key(int $timestamp): string
{
    return date('o-\WW', $timestamp);
}

/** "9 – 15 Nov 2026" or "27 Oct – 2 Nov 2026" for an ISO week like "2026-W46". */
function week_range(string $week): string
{
    [$year, $number] = [(int) substr($week, 0, 4), (int) substr($week, 6, 2)];
    $monday = (new DateTimeImmutable())->setISODate($year, $number, 1);
    $sunday = $monday->modify('+6 days');
    if ($monday->format('Y-m') === $sunday->format('Y-m')) {
        return $monday->format('j') . ' – ' . $sunday->format('j M Y');
    }
    return $monday->format('j M') . ' – ' . $sunday->format('j M Y');
}

/** All memories grouped per ISO week: ["2026-W46" => [memories, newest first]], newest week first. */
function memories_by_week(): array
{
    $weeks = [];
    foreach (list_memories() as $m) {
        $weeks[week_key((int) strtotime($m['created']))][] = $m;
    }
    return $weeks;
}

/** &page=history (list of weeks) and &page=history&week=2026-W46 (one week). */
function render_history_page(string $privateBase, string $week): string
{
    $weeks = memories_by_week();
    $back  = '<p><a href="' . e($privateBase) . '">Back to your context</a></p>';

    // Overview: every week with memories.
    if ($week === '') {
        $html = '<h1>Memory history</h1>'
            . '<p>Everything you saved, organised per week, newest week first. Open a week to read its memories.</p>';
        if ($weeks === []) {
            return $html . '<p>No memories saved yet.</p>' . $back;
        }
        $html .= '<ul>';
        foreach ($weeks as $key => $memories) {
            $url    = $privateBase . '&page=history&week=' . $key;
            $titles = implode('; ', array_map(fn ($m) => $m['title'], array_slice($memories, 0, 3))) . (count($memories) > 3 ? '; …' : '');
            $html  .= '<li><strong><a href="' . e($url) . '">Week ' . (int) substr($key, 6) . ' · ' . e(week_range($key)) . '</a></strong>'
                . ' — ' . count($memories) . ' ' . (count($memories) === 1 ? 'memory' : 'memories') . ': ' . e($titles)
                . '<br><code>' . e($url) . '</code></li>';
        }
        return $html . '</ul>' . $back;
    }

    // One week.
    $keys  = array_keys($weeks);
    $index = array_search($week, $keys, true);
    $html  = '<h1>Week ' . (int) substr($week, 6) . ' · ' . e(week_range($week)) . '</h1>';
    if ($index === false) {
        return $html . '<p>No memories in this week.</p>'
            . '<p><a href="' . e($privateBase . '&page=history') . '">All weeks</a></p>' . $back;
    }

    $html .= '<p>' . count($weeks[$week]) . ' ' . (count($weeks[$week]) === 1 ? 'memory' : 'memories') . ' from this week, newest first.</p><ul>';
    foreach ($weeks[$week] as $m) {
        $html .= memory_item_html($m);
    }
    $html .= '</ul><h2>Other weeks</h2><ul>';
    if (isset($keys[$index + 1])) {
        $html .= '<li>Older: <a href="' . e($privateBase . '&page=history&week=' . $keys[$index + 1]) . '">Week ' . (int) substr($keys[$index + 1], 6) . ' · ' . e(week_range($keys[$index + 1])) . '</a></li>';
    }
    if ($index > 0) {
        $html .= '<li>Newer: <a href="' . e($privateBase . '&page=history&week=' . $keys[$index - 1]) . '">Week ' . (int) substr($keys[$index - 1], 6) . ' · ' . e(week_range($keys[$index - 1])) . '</a></li>';
    }
    return $html . '<li><a href="' . e($privateBase . '&page=history') . '">All weeks</a></li></ul>' . $back;
}

function render_memories_page(string $privateBase, string $query): string
{
    $memories = list_memories($query, 50);
    $html = '<h1>Memories</h1>'
        . '<p>' . ($query === '' ? 'The 50 most recent memories, newest first. For older ones, use the history per week.' : 'Memories containing “' . e($query) . '”.') . '</p>';

    if ($memories === []) {
        $html .= '<p>Nothing found.</p>';
    } else {
        $html .= '<ul>';
        foreach ($memories as $m) {
            $html .= memory_item_html($m);
        }
        $html .= '</ul>';
    }
    return $html . '<p><a href="' . e($privateBase) . '">Back to your context</a></p>';
}

/**
 * Handles &page=go&skill=ID&q=VALUE: logs the use of the skill and forwards
 * the assistant to the skill's website. Never returns.
 */
function handle_go(string $privateBase): never
{
    $back  = '<p><a href="' . e($privateBase) . '">Back to your context</a></p>';
    $skill = find_by_id(load_context()['skills'], (string) ($_GET['skill'] ?? ''));
    if ($skill === null || empty($skill['enabled']) || ($skill['link'] ?? '') === '') {
        render('Unknown skill', '<h1>Unknown skill</h1><p>This skill does not exist, is switched off, or has no link. Check the skills on your private page.</p>' . $back, 404);
    }

    $query       = clean_line((string) ($_GET['q'] ?? ''), 200);
    $placeholder = link_placeholder((string) $skill['link']);
    if ($placeholder !== '' && ($query === '' || preg_match(SKILL_PLACEHOLDER, $query))) {
        render('Fill in the value', '<h1>Fill in the value first</h1>'
            . '<p>Replace <code>{' . e($placeholder) . '}</code> in the link with the value you want to look up, then open it again:</p>'
            . '<pre><code>' . e(skill_go_link($privateBase, $skill)) . '</code></pre>' . $back);
    }

    $target = skill_target_url((string) $skill['link'], $query);
    record_skill_use($skill, $query);

    // Forward. The page body is a fallback for clients that don't follow redirects.
    http_response_code(302);
    header('Location: ' . $target);
    header('Content-Type: text/html; charset=utf-8');
    echo assistant_page_html('Forwarding', '<h1>Opening ' . e((string) $skill['name']) . '</h1>'
        . '<p>If you are not forwarded automatically, open this address:</p>'
        . '<pre><code>' . e($target) . '</code></pre>', base_url());
    exit;
}

/** Handles &page=remember. Returns the page HTML. */
function handle_remember(string $privateBase): string
{
    // Markdown is allowed; keeps line breaks the assistant sends as %0A.
    $summary = clean_text((string) ($_GET['summary'] ?? $_GET['note'] ?? ''), MAX_SUMMARY_LENGTH);
    $title   = clean_line((string) ($_GET['title'] ?? ''), MAX_TITLE_LENGTH);
    $points  = parse_points((string) ($_GET['points'] ?? ''));

    if ($summary === '' && $title === '') {
        return '<h1>Nothing saved</h1><p>Add at least a summary to the URL:</p>'
            . '<pre><code>' . e(remember_example($privateBase)) . '</code></pre>';
    }
    if ($title === '') {
        $title = title_from_summary($summary);
    }

    $memory = find_recent_duplicate($title, $summary) ?? save_new_memory($title, $summary, $points);

    return '<h1>Saved to memory</h1><p>Tell your user briefly that it was saved.</p>'
        . '<ul>' . memory_item_html($memory) . '</ul>'
        . '<p><a href="' . e($privateBase) . '">Back to your context</a></p>';
}
