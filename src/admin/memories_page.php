<?php
/**
 * Admin: Memories. A list on the left, the selected memory on the right.
 */

declare(strict_types=1);

function view_memories(): never
{
    $query    = clean_line((string) ($_GET['q'] ?? ''), 100);
    $memories = list_memories($query);
    $selected = isset($_GET['id']) ? load_memory((string) $_GET['id']) : ($memories[0] ?? null);
    $keep     = $query !== '' ? ['q' => $query] : [];

    // List
    $items = '';
    foreach ($memories as $m) {
        $current = $selected !== null && $m['id'] === $selected['id'] ? 'true' : 'false';
        $href    = '?' . http_build_query(['p' => 'memories', 'id' => $m['id']] + $keep);
        $items  .= '<li><a class="mem" href="' . e($href) . '" aria-current="' . $current . '">'
            . '<span class="mem-top"><span>' . e($m['title']) . '</span><span class="mem-when">' . e(format_date($m['created'], false)) . '</span></span>'
            . '<span class="mem-sum">' . e(markdown_excerpt($m['summary'], 160)) . '</span></a></li>';
    }
    if ($items === '') {
        $items = '<li class="muted" style="padding:12px">' . ($query === '' ? 'No memories yet.' : 'Nothing matches “' . e($query) . '”.') . '</li>';
    }

    $html = page_header('Memories', 'Sessions your assistant saved. ' . count_memories() . ' in total.')
        . '<div class="split">'
        . '<section class="mem-list" aria-label="Memory list">' . search_form('memories', $query, 'Search memories')
        . '<ul class="mem-items">' . $items . '</ul></section>'
        . '<section class="mem-detail" aria-label="Memory detail">' . memory_detail($selected, $keep) . '</section>'
        . '</div>';

    admin_page('memories', 'Memories', $html, 'wide');
}

function memory_detail(?array $m, array $keep): string
{
    if ($m === null) {
        return '<div class="empty" style="margin:auto;border:0"><strong>No memory selected</strong>'
            . '<span class="muted">New sessions will appear here once your assistant saves them.</span></div>';
    }

    $self = ['p' => 'memories', 'id' => $m['id']] + $keep;

    // Edit form
    if (isset($_GET['edit'])) {
        return '<form method="post" action="?' . e(http_build_query($self)) . '" class="stack">' . csrf_field()
            . '<input type="hidden" name="action" value="save_memory"><input type="hidden" name="id" value="' . e($m['id']) . '">'
            . '<h2>Edit memory</h2>'
            . '<div class="field"><label for="mt">Title</label><input id="mt" type="text" name="title" maxlength="' . MAX_TITLE_LENGTH . '" required value="' . e($m['title']) . '"></div>'
            . '<div class="field"><div class="field-head"><label for="ms">Summary</label><span class="hint">Markdown supported</span></div>'
            . '<textarea id="ms" name="summary" rows="12" maxlength="' . MAX_SUMMARY_LENGTH . '">' . e($m['summary']) . '</textarea></div>'
            . markdown_help()
            . '<div class="field"><label for="mp">Key points <span class="hint">one per line</span></label><textarea id="mp" name="points" rows="5">' . e(implode("\n", $m['points'])) . '</textarea></div>'
            . '<div class="head-actions"><button type="submit" class="btn">Save</button><a class="btn ghost" href="?' . e(http_build_query($self)) . '">Cancel</a></div>'
            . '</form>';
    }

    $confirm = '';
    if (isset($_GET['confirm'])) {
        $confirm = '<div class="notice warn" role="alert" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px">'
            . '<span>Delete this memory? This cannot be undone.</span><span class="head-actions">'
            . post_button('memories', 'delete_memory', ['id' => $m['id']], 'Delete', 'btn danger small')
            . '<a class="btn ghost small" href="?' . e(http_build_query($self)) . '">Cancel</a></span></div>';
    }

    $points = '';
    foreach ($m['points'] as $point) {
        $points .= '<li>' . e($point) . '</li>';
    }

    return '<article>'
        . '<header class="card-head" style="align-items:flex-start"><div><h2 style="font-size:20px;letter-spacing:-.02em">' . e($m['title']) . '</h2>'
        . '<div class="meta"><span>' . e(format_date($m['created'])) . '</span><span aria-hidden="true">·</span><code>' . e($m['id']) . '</code></div></div>'
        . '<div class="head-actions">'
        . '<a class="btn ghost small" href="?' . e(http_build_query($self + ['edit' => 1])) . '">Edit</a>'
        . '<a class="btn ghost small" href="?' . e(http_build_query($self + ['confirm' => 1])) . '">Delete</a>'
        . '</div></header>'
        . $confirm
        . '<div class="stack" style="gap:8px"><span class="section-label">Summary</span><div class="md-body">' . markdown_to_html(demote_headings($m['summary'], 2)) . '</div></div>'
        . ($points !== '' ? '<div class="stack" style="gap:8px"><span class="section-label">Key points</span><ul class="points">' . $points . '</ul></div>' : '')
        . '</article>';
}

function post_memories(array $in): never
{
    $action = (string) ($in['action'] ?? '');
    $id     = (string) ($in['id'] ?? '');

    if ($action === 'save_memory') {
        $title = clean_line((string) ($in['title'] ?? ''), MAX_TITLE_LENGTH);
        if ($title === '' || !update_memory($id, $title, clean_text((string) ($in['summary'] ?? ''), MAX_SUMMARY_LENGTH), parse_points((string) ($in['points'] ?? '')))) {
            flash('The memory could not be saved.', 'error');
        } else {
            flash('Memory saved.');
        }
        redirect_to(['p' => 'memories', 'id' => $id]);
    }
    if ($action === 'delete_memory') {
        delete_memory($id);
        flash('Memory deleted.');
    }
    redirect_to(['p' => 'memories']);
}
