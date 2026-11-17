<?php
/**
 * Admin: Security. Which browsers (simplified user agents) and countries may
 * open the assistant side. Each list has an on/off switch: off = monitor only
 * (the tables show what would be blocked), on = enforce.
 */

declare(strict_types=1);

function security_switch(string $action, bool $on, string $label): string
{
    return post_button('security', $action, ['mode' => $on ? 'monitor' : 'enforce'], '', 'switch lg', $label,
        ' role="switch" aria-checked="' . ($on ? 'true' : 'false') . '"');
}

/** Status pill for one browser row, given the current rules. */
function client_status(array $c, array $rules, bool $enforcing): string
{
    if ($rules === []) {
        return '<span class="pill ok">Allowed</span>';
    }
    $rule = client_rule_for($c['client'], $rules);
    $min  = (int) ($rule['min_version'] ?? 0);
    $lo   = (int) ($c['min'] ?? 0);
    $hi   = (int) ($c['max'] ?? 0);

    if ($rule !== null && $lo >= $min) {
        return '<span class="pill ok">Allowed</span>';
    }
    $text = $rule !== null && $hi >= $min ? 'Below ' . $min . ' blocked' : 'Blocked';
    return $enforcing
        ? '<span class="pill ' . ($rule !== null && $hi >= $min ? 'ok' : 'bad') . '">' . e($text) . '</span>'
        : '<span class="pill warn">Would be: ' . e(lcfirst($text)) . '</span>';
}

function view_security(): never
{
    $s          = settings()['security'];
    $rules      = $s['clients']['rules'];
    $countries  = $s['countries']['allowed'];
    $uaOn       = $s['clients']['mode'] === 'enforce';
    $geoOn      = $s['countries']['mode'] === 'enforce';
    $seen       = list_clients_seen(30);

    // --- Browsers seen ------------------------------------------------------
    $rows = '';
    foreach ($seen as $c) {
        $range = $c['min'] === null ? '–' : ($c['min'] === $c['max'] ? (string) $c['min'] : $c['min'] . ' – ' . $c['max']);
        $allow = client_rule_for($c['client'], $rules) === null
            ? ' ' . post_button('security', 'add_client_rule', ['client' => $c['client'], 'min_version' => (int) ($c['min'] ?? 0)], 'Allow', 'btn ghost small', 'Allow ' . $c['client'])
            : '';
        $rows .= '<tr>'
            . '<td><span class="title-cell"><span class="initial" aria-hidden="true">' . e(mb_substr($c['client'], 0, 1)) . '</span>'
            . '<span class="stack" style="gap:0"><span>' . e($c['client']) . '</span><span class="muted" style="font-weight:400;font-size:12px">' . e($c['kind']) . '</span></span></span></td>'
            . '<td class="nowrap">' . e($range) . '</td>'
            . '<td class="nowrap">' . number_format($c['requests']) . '</td>'
            . '<td class="muted nowrap">' . e(format_date($c['last'])) . '</td>'
            . '<td class="nowrap"><span class="status-cell">' . client_status($c, $rules, $uaOn) . $allow . '</span></td>'
            . '</tr>';
    }
    $seenTable = $rows === ''
        ? '<div class="empty"><strong>No requests yet</strong><span class="muted">Ask your assistant to open the assistant page; it will show up here.</span></div>'
        : '<div class="table-wrap"><table><thead><tr><th scope="col">Browser</th><th scope="col">Versions seen</th><th scope="col">Requests</th><th scope="col">Last seen</th><th scope="col">Status</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';

    $seenSection = '<section class="stack">'
        . '<div class="card-head"><div><h2>Browsers seen</h2><p class="muted" style="margin-top:4px">User agents from the last 30 days, grouped by browser.</p></div>'
        . ($rows !== '' ? post_button('security', 'clear_visitors', [], 'Clear history') : '') . '</div>'
        . $seenTable . '</section>';

    // --- Browser allowlist --------------------------------------------------
    $ruleItems = '';
    foreach ($rules as $i => $rule) {
        $ruleItems .= '<li><span class="row-title" style="flex:1 1 auto">' . e((string) $rule['client']) . '</span>'
            . '<span class="muted">Version ≥</span><span class="tag" style="font-size:13px">' . (int) ($rule['min_version'] ?? 0) . '</span>'
            . post_button('security', 'delete_client_rule', ['index' => $i], icon('trash'), 'icon-btn', 'Remove rule for ' . $rule['client']) . '</li>';
    }
    $names   = array_unique(array_merge(array_column($seen, 'client'), client_names()));
    $options = '';
    foreach ($names as $name) {
        $options .= '<option>' . e($name) . '</option>';
    }
    $allowlist = '<section class="panel">'
        . '<div class="panel-head row"><div><h2>Browser allowlist</h2><p class="muted">'
        . ($uaOn ? 'On: only these browsers at or above the minimum version get through.' : 'Off: nothing is blocked yet. The table above shows what would be blocked once you switch this on.')
        . '</p></div>' . security_switch('set_client_mode', $uaOn, 'Only allow listed browsers') . '</div>'
        . ($ruleItems !== '' ? '<ul class="list flat">' . $ruleItems . '</ul>' : '<p class="panel-body muted">No browsers on the list yet, so every browser is allowed.</p>')
        . '<form method="post" action="?p=security" class="panel-body form-row" style="border-top:1px solid #EDEDED">' . csrf_field()
        . '<input type="hidden" name="action" value="add_client_rule">'
        . '<div class="field"><label for="rb">Browser</label><select id="rb" name="client">' . $options . '</select></div>'
        . '<div class="field"><label for="rv">Minimum version</label><input id="rv" type="number" name="min_version" min="0" max="99999" value="0"></div>'
        . '<button type="submit" class="btn">Add rule</button></form>'
        . '</section>';

    // --- Geo lock -----------------------------------------------------------
    $chips = '';
    foreach ($countries as $code) {
        $chips .= '<span class="country"><span class="code">' . e($code) . '</span>' . e(country_name($code))
            . post_button('security', 'remove_country', ['country' => $code], '×', 'icon-btn tiny', 'Remove ' . country_name($code)) . '</span>';
    }
    $addable = '';
    foreach (countries_by_name() as $code => $name) {
        if (!in_array($code, $countries, true)) {
            $addable .= '<option value="' . $code . '">' . e($name) . '</option>';
        }
    }
    $blockedRows = '';
    foreach (list_blocked(8) as $b) {
        $blockedRows .= '<li><code>' . e((string) $b['ip']) . '</code><span>' . e(country_name((string) $b['country'])) . '</span>'
            . '<span class="muted" style="flex:1 1 auto">' . e((string) $b['reason']) . (empty($b['enforced']) ? ' <em>(not blocked, switch was off)</em>' : '') . '</span>'
            . '<span class="muted nowrap">' . e(format_date((string) $b['when'])) . '</span></li>';
    }
    $geoStatus = geo_database_available()
        ? 'Country data updated ' . e(format_date(date('c', (int) filemtime(GEO_FILE)), false)) . '.'
        : 'The country database has not been downloaded yet, so countries are unknown and not checked.';

    $geo = '<section class="panel">'
        . '<div class="panel-head row"><div><h2>Geo lock</h2><p class="muted">'
        . ($geoOn ? 'On: requests are only allowed from these countries, based on the requesting IP address.' : 'Off: nothing is blocked yet. Recently blocked shows what would be.')
        . '</p></div>' . security_switch('set_country_mode', $geoOn, 'Geo lock') . '</div>'
        . '<div class="panel-body cols">'
        . '<div class="main-col" style="flex-basis:320px;gap:12px"><div class="chips">' . ($chips !== '' ? $chips : '<span class="muted">No countries yet, so every country is allowed.</span>') . '</div>'
        . '<form method="post" action="?p=security" class="form-row">' . csrf_field() . '<input type="hidden" name="action" value="add_country">'
        . '<div class="field"><label for="ac">Add country</label><select id="ac" name="country" required><option value="">Choose a country</option>' . $addable . '</select></div>'
        . '<button type="submit" class="btn ghost">Add</button></form>'
        . '<p class="muted">Your own network and addresses without a known country are always allowed. ' . $geoStatus
        . ' <a href="https://db-ip.com" target="_blank" rel="noopener noreferrer">IP Geolocation by DB-IP</a>.</p></div>'
        . '<div class="main-col" style="flex-basis:320px;gap:8px"><span class="section-label">Recently blocked</span>'
        . ($blockedRows !== '' ? '<ul class="blocked">' . $blockedRows . '</ul>' : '<p class="muted">Nothing blocked yet.</p>') . '</div>'
        . '</div></section>';

    $html = page_header('Security', 'Choose which browsers and countries may open the assistant side. Applies to every request, before the passphrase check.')
        . '<p class="notice warn">Your assistant fetches pages from its own servers, not from your car, so it shows up here with the AI provider\'s name and country. Allow it before you switch anything on.</p>'
        . $seenSection . $allowlist . $geo;

    admin_page('security', 'Security', $html);
}

function post_security(array $in): never
{
    $action = (string) ($in['action'] ?? '');
    $mode   = ($in['mode'] ?? '') === 'enforce' ? 'enforce' : 'monitor';

    switch ($action) {
        case 'set_client_mode':
        case 'set_country_mode':
            $key = $action === 'set_client_mode' ? 'clients' : 'countries';
            update_settings(function (array $s) use ($key, $mode) {
                $s['security'][$key]['mode'] = $mode;
                return $s;
            });
            flash(($key === 'clients' ? 'Browser allowlist' : 'Geo lock') . ($mode === 'enforce' ? ' is on.' : ' is off.'));
            break;

        case 'add_client_rule':
            $client = (string) ($in['client'] ?? '');
            $min    = max(0, min(99999, (int) ($in['min_version'] ?? 0)));
            if (!in_array($client, client_names(), true)) {
                flash('Unknown browser.', 'error');
                break;
            }
            update_settings(function (array $s) use ($client, $min) {
                // One rule per browser: replace an existing one.
                $rules = array_filter((array) $s['security']['clients']['rules'], fn ($r) => ($r['client'] ?? '') !== $client);
                $rules[] = ['client' => $client, 'min_version' => $min];
                $s['security']['clients']['rules'] = array_values($rules);
                return $s;
            });
            flash($client . ($min > 0 ? ' ' . $min . ' and newer' : '') . ' is on the allowlist.');
            break;

        case 'delete_client_rule':
            $index = (int) ($in['index'] ?? -1);
            update_settings(function (array $s) use ($index) {
                $rules = (array) $s['security']['clients']['rules'];
                unset($rules[$index]);
                $s['security']['clients']['rules'] = array_values($rules);
                return $s;
            });
            flash('Rule removed.');
            break;

        case 'add_country':
            $country = strtoupper(trim((string) ($in['country'] ?? '')));
            if (!isset(COUNTRIES[$country])) {
                flash('Choose a country from the list.', 'error');
                break;
            }
            update_settings(function (array $s) use ($country) {
                $allowed = (array) $s['security']['countries']['allowed'];
                if (!in_array($country, $allowed, true)) {
                    $allowed[] = $country;
                    sort($allowed);
                }
                $s['security']['countries']['allowed'] = array_values($allowed);
                return $s;
            });
            flash(country_name($country) . ' added.');
            break;

        case 'remove_country':
            $country = (string) ($in['country'] ?? '');
            update_settings(function (array $s) use ($country) {
                $s['security']['countries']['allowed'] = array_values(array_filter((array) $s['security']['countries']['allowed'], fn ($c) => $c !== $country));
                return $s;
            });
            flash(country_name($country) . ' removed.');
            break;

        case 'clear_visitors':
            clear_visitors();
            flash('History cleared.');
            break;
    }
    redirect_to(['p' => 'security']);
}
