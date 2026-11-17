<?php
/**
 * Pages the AI assistant reads after giving the right passphrase.
 * Everything it needs is on one page, so it only has to open one URL.
 */

declare(strict_types=1);

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

    // Recent memories
    $recent = list_memories('', 10);
    $html .= '<h2>Recent memories</h2>';
    if ($recent === []) {
        $html .= '<p>No memories saved yet.</p>';
    } else {
        $html .= '<p>What you saved in earlier conversations, newest first.</p><ul>';
        foreach ($recent as $m) {
            $html .= memory_item_html($m);
        }
        $html .= '</ul>';
    }

    // How to use memory
    $html .= '<h2>Search older memories</h2>'
        . '<p>Replace <code>word</code> with what you are looking for.</p>'
        . '<pre><code>' . e($privateBase . '&page=memories&q=word') . '</code></pre>'
        . '<h2>Save a memory</h2>'
        . '<p>When your user tells you something worth keeping, or at the end of a conversation, save a short memory. Use <code>+</code> instead of spaces and separate key points with <code>|</code>. Confirm briefly to your user that it was saved.</p>'
        . '<pre><code>' . e(remember_example($privateBase)) . '</code></pre>';

    return $html;
}

function render_memories_page(string $privateBase, string $query): string
{
    $memories = list_memories($query, 50);
    $html = '<h1>Memories</h1>'
        . '<p>' . ($query === '' ? 'All saved memories, newest first.' : 'Memories containing “' . e($query) . '”.') . '</p>';

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
