<?php
/**
 * Admin: Skill usage. Every time the assistant opened a skill link
 * (&page=go), per skill and as a list of recent uses.
 */

declare(strict_types=1);

const USAGE_PAGE_ROWS = 100;

function view_usage(): never
{
    $skills  = load_context()['skills'];
    $summary = skill_usage_summary();
    $filter  = (string) ($_GET['skill'] ?? '');
    $current = $filter !== '' ? find_by_id($skills, $filter) : null;

    // --- Per skill --------------------------------------------------------------
    $rows = '';
    foreach ($skills as $s) {
        if (($s['link'] ?? '') === '' && !isset($summary[$s['id']])) {
            continue; // skills without a link are never logged
        }
        $u    = $summary[$s['id']] ?? ['total' => 0, 'week' => 0, 'last' => ''];
        $name = (string) ($s['name'] ?? '');
        $rows .= '<tr' . ($filter === $s['id'] ? ' class="selected"' : '') . '>'
            . '<td><span class="title-cell"><span class="initial" aria-hidden="true">' . e(mb_strtoupper(mb_substr($name, 0, 1))) . '</span>' . e($name) . '</span></td>'
            . '<td class="nowrap">' . $u['total'] . '</td>'
            . '<td class="nowrap">' . $u['week'] . '</td>'
            . '<td class="muted nowrap">' . ($u['last'] !== '' ? e(format_date($u['last'])) : 'Never') . '</td>'
            . '<td class="right nowrap">' . ($u['total'] > 0 ? '<a class="btn ghost small" href="?p=usage&amp;skill=' . e($s['id']) . '">Show uses</a>' : '') . '</td>'
            . '</tr>';
    }
    $perSkill = $rows === ''
        ? '<div class="empty"><strong>No skills with a link yet</strong><span class="muted">Give a skill a link template under <a href="?p=skills">Skills</a>; from then on every use is logged here.</span></div>'
        : '<div class="table-wrap"><table><thead><tr><th scope="col">Skill</th><th scope="col">Uses</th><th scope="col">Last 7 days</th><th scope="col">Last used</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead><tbody>' . $rows . '</tbody></table></div>';

    // --- Recent uses ------------------------------------------------------------
    $uses     = list_skill_uses($filter, USAGE_PAGE_ROWS);
    $useRows  = '';
    foreach ($uses as $use) {
        $skill   = find_by_id($skills, (string) $use['skill']);
        $target  = $skill !== null && ($skill['link'] ?? '') !== '' ? skill_target_url((string) $skill['link'], (string) $use['q']) : '';
        $useRows .= '<tr>'
            . '<td class="muted nowrap">' . e(format_date((string) $use['when'])) . '</td>'
            . '<td class="nowrap">' . e((string) ($skill['name'] ?? $use['name'] ?? '')) . '</td>'
            . '<td>' . ((string) $use['q'] !== '' ? '<code>' . e((string) $use['q']) . '</code>' : '<span class="muted">–</span>') . '</td>'
            . '<td class="right nowrap">' . ($target !== '' ? '<a class="plain-link muted" href="' . e($target) . '" target="_blank" rel="noopener noreferrer">' . icon('external') . ' Open</a>' : '') . '</td>'
            . '</tr>';
    }
    $recentTitle = $current !== null ? 'Uses of ' . $current['name'] : 'Recent uses';
    $recent = '<section class="stack">'
        . '<div class="card-head"><div><h2>' . e($recentTitle) . '</h2><p class="muted" style="margin-top:4px">Newest first'
        . (count($uses) >= USAGE_PAGE_ROWS ? ', the latest ' . USAGE_PAGE_ROWS : '') . '.</p></div>'
        . '<div class="head-actions">'
        . ($filter !== '' ? '<a class="btn ghost small" href="?p=usage">Show all skills</a>' : '')
        . ($filter === '' && $useRows !== '' ? post_button('usage', 'clear_usage', [], 'Clear log') : '')
        . '</div></div>'
        . ($useRows === ''
            ? '<div class="empty"><strong>Nothing logged yet</strong><span class="muted">Uses appear here as soon as your assistant opens a skill link.</span></div>'
            : '<div class="table-wrap"><table><thead><tr><th scope="col">When</th><th scope="col">Skill</th><th scope="col">Looked up</th><th scope="col"><span class="sr-only">Open</span></th></tr></thead><tbody>' . $useRows . '</tbody></table></div>')
        . '</section>';

    $html = page_header('Skill usage', 'Every time your assistant opened a skill link. Only skills with a link template are logged.')
        . '<section class="stack"><h2>Per skill</h2>' . $perSkill . '</section>'
        . $recent;

    admin_page('usage', 'Skill usage', $html);
}

function post_usage(array $in): never
{
    if (($in['action'] ?? '') === 'clear_usage') {
        clear_skill_uses();
        flash('Skill usage log cleared.');
    }
    redirect_to(['p' => 'usage']);
}
