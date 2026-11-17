<?php
/**
 * Admin: Backstory, Skills and Links (the assistant's context).
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Backstory
// ---------------------------------------------------------------------------

function view_backstory(): never
{
    $c = load_context();
    $a = $c['assistant'];
    $saved = $c['updated'] ? 'Last saved ' . lcfirst(format_date($c['updated'])) : 'Not saved yet';

    $tones = '';
    foreach (TONES as $tone) {
        $checked = $tone === $a['tone'] ? ' checked' : '';
        $tones .= '<label class="chip"><input type="radio" name="tone" value="' . e($tone) . '"' . $checked . '><span>' . e($tone) . '</span></label>';
    }
    $languages = '';
    foreach (LANGUAGES as $language) {
        $selected = $language === $a['language'] ? ' selected' : '';
        $languages .= '<option' . $selected . '>' . e($language) . '</option>';
    }

    $html = '<form method="post" action="?p=backstory" class="stack" style="gap:32px">' . csrf_field()
        . '<input type="hidden" name="action" value="save_backstory">'
        . page_header('Backstory', 'Who your assistant is and how it should behave. Sent at the start of every session.',
            '<span class="muted">' . e($saved) . '</span><button type="submit" class="btn">Save changes</button>')
        . '<div class="cols"><section class="main-col">'
        . '<div class="grid2">'
        . '<div class="field"><label for="nm">Assistant name</label><input id="nm" type="text" name="name" maxlength="100" value="' . e($a['name']) . '"></div>'
        . '<div class="field"><label for="rl">Role</label><input id="rl" type="text" name="role" maxlength="200" value="' . e($a['role']) . '"></div>'
        . '</div>'
        . '<div class="field"><div class="field-head"><label for="bs">Backstory</label><span class="hint">Markdown supported</span></div>'
        . '<textarea id="bs" name="backstory" rows="18">' . e($c['backstory']) . '</textarea></div>'
        . '</section><aside class="side-col">'
        . '<fieldset class="card"><legend class="label" style="float:left;margin-bottom:14px">Tone</legend><div class="chips" style="clear:both">' . $tones . '</div></fieldset>'
        . '<div class="card" style="gap:8px"><label for="lg">Default language</label><select id="lg" name="language">' . $languages . '</select></div>'
        . '<div class="card soft"><span class="label">Sent with every session</span><span class="muted">The assistant gets this backstory together with its enabled skills, links and recent memories on its private page.</span></div>'
        . '</aside></div></form>';

    admin_page('backstory', 'Backstory', $html);
}

function post_backstory(array $in): never
{
    update_context(function (array $c) use ($in) {
        $c['assistant']['name']     = clean_line((string) ($in['name'] ?? ''), 100);
        $c['assistant']['role']     = clean_line((string) ($in['role'] ?? ''), 200);
        $c['assistant']['tone']     = in_array($in['tone'] ?? '', TONES, true) ? $in['tone'] : TONES[0];
        $c['assistant']['language'] = in_array($in['language'] ?? '', LANGUAGES, true) ? $in['language'] : LANGUAGES[0];
        $c['backstory']             = clean_text((string) ($in['backstory'] ?? ''), 20000);
        return $c;
    });
    flash('Backstory saved.');
    redirect_to(['p' => 'backstory']);
}

// ---------------------------------------------------------------------------
// Skills
// ---------------------------------------------------------------------------

function view_skills(): never
{
    $skills = load_context()['skills'];

    if (isset($_GET['edit'])) {
        $id    = (string) $_GET['edit'];
        $skill = $id === 'new' ? ['id' => 'new', 'name' => '', 'tag' => '', 'description' => '', 'link' => '', 'enabled' => true] : find_by_id($skills, $id);
        if ($skill === null) {
            flash('That skill no longer exists.', 'error');
            redirect_to(['p' => 'skills']);
        }
        skill_editor($skill);
    }

    $query    = clean_line((string) ($_GET['q'] ?? ''), 100);
    $enabled  = count(array_filter($skills, fn ($s) => !empty($s['enabled'])));
    $filtered = $query === '' ? $skills : array_filter($skills, fn ($s) => str_contains(
        mb_strtolower(($s['name'] ?? '') . ' ' . ($s['tag'] ?? '') . ' ' . ($s['description'] ?? '')), mb_strtolower($query)));

    $usage = skill_usage_summary();
    $rows  = '';
    foreach ($filtered as $s) {
        $on   = !empty($s['enabled']);
        $name = (string) ($s['name'] ?? '');
        $tag  = ($s['tag'] ?? '') !== '' ? '<span class="tag">' . e($s['tag']) . '</span>' : '';
        $uses = '';
        if (($s['link'] ?? '') !== '') {
            $count = $usage[$s['id']]['total'] ?? 0;
            $uses  = '<a class="uses" href="?p=usage&amp;skill=' . e($s['id']) . '">' . icon('links') . $count . ' ' . ($count === 1 ? 'use' : 'uses') . '</a>';
        }
        $rows .= '<li' . ($on ? '' : ' class="off"') . '>'
            . '<div class="row-main"><span class="row-title">' . e($name) . $tag . $uses . '</span><span class="muted">' . e(markdown_excerpt((string) ($s['description'] ?? ''))) . '</span></div>'
            . '<div class="actions">'
            . post_button('skills', 'toggle_skill', ['id' => $s['id']], '', 'switch', ($on ? 'Disable ' : 'Enable ') . $name, ' role="switch" aria-checked="' . ($on ? 'true' : 'false') . '"')
            . '<a class="icon-btn" href="?p=skills&amp;edit=' . e($s['id']) . '" aria-label="Edit ' . e($name) . '">' . icon('edit') . '</a>'
            . post_button('skills', 'delete_skill', ['id' => $s['id']], icon('trash'), 'icon-btn', 'Delete ' . $name)
            . '</div></li>';
    }

    $list = $rows !== '' ? '<ul class="list">' . $rows . '</ul>'
        : '<div class="empty"><strong>' . ($query === '' ? 'No skills yet' : 'No skills match “' . e($query) . '”') . '</strong><span class="muted">Skills tell your assistant what it can and may do for you.</span></div>';

    $html = page_header('Skills', 'Things your assistant knows how to do. Only enabled skills are sent.',
            '<a class="btn" href="?p=skills&amp;edit=new">' . icon('plus') . 'New skill</a>')
        . '<div class="toolbar">' . search_form('skills', $query, 'Search skills')
        . '<span class="muted">' . $enabled . ' of ' . count($skills) . ' enabled</span></div>'
        . $list;

    admin_page('skills', 'Skills', $html);
}

function skill_editor(array $skill): never
{
    $isNew = $skill['id'] === 'new';
    $html = page_header($isNew ? 'New skill' : 'Edit skill', 'Describe what your assistant can do, and how to do it.')
        . '<form method="post" action="?p=skills" class="card stack">' . csrf_field()
        . '<input type="hidden" name="action" value="save_skill"><input type="hidden" name="id" value="' . e($skill['id']) . '">'
        . '<div class="grid2">'
        . '<div class="field"><label for="sn">Name</label><input id="sn" type="text" name="name" maxlength="100" required value="' . e((string) $skill['name']) . '"></div>'
        . '<div class="field"><label for="st">Tag <span class="hint">optional</span></label><input id="st" type="text" name="tag" maxlength="40" value="' . e((string) $skill['tag']) . '"></div>'
        . '</div>'
        . '<div class="field"><div class="field-head"><label for="sd">Description</label><span class="hint">Markdown supported</span></div>'
        . '<textarea id="sd" name="description" rows="12" maxlength="5000">' . e((string) $skill['description']) . '</textarea></div>'
        . markdown_help()
        . '<div class="field"><label for="sl">Link template <span class="hint">optional · every use is logged</span></label>'
        . '<input id="sl" type="text" inputmode="url" name="link" maxlength="1000" placeholder="https://ovi.rdw.nl/?kenteken={kenteken}" value="' . e((string) ($skill['link'] ?? '')) . '">'
        . '<span class="hint">The website this skill opens. Put <code>{name}</code> where the value goes, e.g. <code>{kenteken}</code>. '
        . 'The assistant then gets a link through AssistantSupport instead: it logs the use and forwards to this website. '
        . 'You don\'t need to put the website address in the description as well.</span></div>'
        . '<label class="check"><input type="checkbox" name="enabled" value="1"' . (!empty($skill['enabled']) ? ' checked' : '') . '> Enabled (sent to the assistant)</label>'
        . '<div class="head-actions"><button type="submit" class="btn">Save skill</button><a class="btn ghost" href="?p=skills">Cancel</a></div>'
        . '</form>';
    admin_page('skills', $isNew ? 'New skill' : 'Edit skill', $html);
}

function post_skills(array $in): never
{
    $action = (string) ($in['action'] ?? '');
    $id     = (string) ($in['id'] ?? '');

    if ($action === 'save_skill') {
        $name = clean_line((string) ($in['name'] ?? ''), 100);
        if ($name === '') {
            flash('A skill needs a name.', 'error');
            redirect_to(['p' => 'skills', 'edit' => $id]);
        }
        $link = normalize_link_template(clean_line((string) ($in['link'] ?? ''), 1000));
        if ($link === null) {
            flash('The link template must be a valid http(s) address, like https://ovi.rdw.nl/?kenteken={kenteken}.', 'error');
            redirect_to(['p' => 'skills', 'edit' => $id]);
        }
        $skill = [
            'id'          => $id === 'new' ? new_id() : $id,
            'name'        => $name,
            'tag'         => clean_line((string) ($in['tag'] ?? ''), 40),
            'description' => clean_text((string) ($in['description'] ?? ''), 5000),
            'link'        => $link,
            'enabled'     => !empty($in['enabled']),
        ];
        update_context(function (array $c) use ($skill, $id) {
            if ($id === 'new') {
                $c['skills'][] = $skill;
            } else {
                $c['skills'] = array_map(fn ($s) => ($s['id'] ?? '') === $id ? $skill : $s, $c['skills']);
            }
            return $c;
        });
        flash('Skill “' . $name . '” saved.');
    } elseif ($action === 'toggle_skill') {
        update_context(function (array $c) use ($id) {
            $c['skills'] = array_map(fn ($s) => ($s['id'] ?? '') === $id ? array_merge($s, ['enabled' => empty($s['enabled'])]) : $s, $c['skills']);
            return $c;
        });
    } elseif ($action === 'delete_skill') {
        update_context(function (array $c) use ($id) {
            $c['skills'] = array_values(array_filter($c['skills'], fn ($s) => ($s['id'] ?? '') !== $id));
            return $c;
        });
        flash('Skill deleted.');
    }
    redirect_to(['p' => 'skills']);
}

// ---------------------------------------------------------------------------
// Links
// ---------------------------------------------------------------------------

function view_links(): never
{
    $links = load_context()['links'];

    if (isset($_GET['edit'])) {
        $link = find_by_id($links, (string) $_GET['edit']);
        if ($link === null) {
            flash('That link no longer exists.', 'error');
            redirect_to(['p' => 'links']);
        }
        $html = page_header('Edit link', 'Change the title or address.')
            . '<form method="post" action="?p=links" class="card stack">' . csrf_field()
            . '<input type="hidden" name="action" value="save_link"><input type="hidden" name="id" value="' . e($link['id']) . '">'
            . link_fields((string) $link['title'], (string) $link['url'])
            . '<div class="head-actions"><button type="submit" class="btn">Save link</button><a class="btn ghost" href="?p=links">Cancel</a></div></form>';
        admin_page('links', 'Edit link', $html);
    }

    $rows = '';
    foreach ($links as $l) {
        $title = (string) ($l['title'] ?? '');
        $rows .= '<tr>'
            . '<td><span class="title-cell"><span class="initial" aria-hidden="true">' . e(mb_strtoupper(mb_substr($title, 0, 1))) . '</span>' . e($title) . '</span></td>'
            . '<td class="muted nowrap"><a href="' . e((string) $l['url']) . '" target="_blank" rel="noopener noreferrer" class="plain-link">' . e(url_without_scheme((string) $l['url'])) . '</a></td>'
            . '<td class="muted nowrap">' . e(format_date($l['added'] ?? null, false)) . '</td>'
            . '<td class="right nowrap"><a class="icon-btn" href="?p=links&amp;edit=' . e($l['id']) . '" aria-label="Edit ' . e($title) . '">' . icon('edit') . '</a>'
            . post_button('links', 'delete_link', ['id' => $l['id']], icon('trash'), 'icon-btn', 'Delete ' . $title) . '</td>'
            . '</tr>';
    }

    $table = $rows === ''
        ? '<div class="empty"><strong>No links yet</strong><span class="muted">Add dashboards, docs and tools you use often.</span></div>'
        : '<div class="table-wrap"><table><thead><tr><th scope="col">Title</th><th scope="col">URL</th><th scope="col">Added</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead><tbody>' . $rows . '</tbody></table></div>';

    $html = page_header('Links', 'Places your assistant should know about: dashboards, docs and tools you use often.')
        . '<form method="post" action="?p=links" class="card well form-row">' . csrf_field()
        . '<input type="hidden" name="action" value="add_link">' . link_fields('', '')
        . '<button type="submit" class="btn">Add link</button></form>'
        . $table;

    admin_page('links', 'Links', $html);
}

function link_fields(string $title, string $url): string
{
    return '<div class="field"><label for="lt">Title</label><input id="lt" type="text" name="title" maxlength="100" required placeholder="Team roadmap" value="' . e($title) . '"></div>'
        . '<div class="field grow"><label for="lu">URL</label><input id="lu" type="text" inputmode="url" name="url" maxlength="2000" required placeholder="https://" value="' . e($url) . '"></div>';
}

function post_links(array $in): never
{
    $action = (string) ($in['action'] ?? '');
    $id     = (string) ($in['id'] ?? '');

    if ($action === 'add_link' || $action === 'save_link') {
        $title = clean_line((string) ($in['title'] ?? ''), 100);
        $url   = normalize_url((string) ($in['url'] ?? ''));
        if ($title === '' || $url === null) {
            flash('A link needs a title and a valid http(s) address.', 'error');
            redirect_to($action === 'save_link' ? ['p' => 'links', 'edit' => $id] : ['p' => 'links']);
        }
        update_context(function (array $c) use ($action, $id, $title, $url) {
            if ($action === 'add_link') {
                array_unshift($c['links'], ['id' => new_id(), 'title' => $title, 'url' => $url, 'added' => date('c')]);
            } else {
                $c['links'] = array_map(fn ($l) => ($l['id'] ?? '') === $id ? array_merge($l, ['title' => $title, 'url' => $url]) : $l, $c['links']);
            }
            return $c;
        });
        flash('Link “' . $title . '” saved.');
    } elseif ($action === 'delete_link') {
        update_context(function (array $c) use ($id) {
            $c['links'] = array_values(array_filter($c['links'], fn ($l) => ($l['id'] ?? '') !== $id));
            return $c;
        });
        flash('Link deleted.');
    }
    redirect_to(['p' => 'links']);
}
