<?php
/**
 * Admin: Security. Which clients (simplified user agents) and countries may
 * open the assistant side, plus the visitor overview and the lockout status.
 */

declare(strict_types=1);

function mode_switch(string $action, string $mode): string
{
    $buttons = '';
    foreach (['monitor' => 'Monitor', 'enforce' => 'Enforce'] as $value => $label) {
        $buttons .= '<button type="submit" name="mode" value="' . $value . '" aria-pressed="' . ($mode === $value ? 'true' : 'false') . '">' . $label . '</button>';
    }
    return '<form method="post" action="?p=security" class="inline">' . csrf_field()
        . '<input type="hidden" name="action" value="' . e($action) . '">'
        . '<span class="segmented" role="group" aria-label="Mode">' . $buttons . '</span></form>';
}

function mode_hint(string $mode, bool $hasRules): string
{
    if (!$hasRules) {
        return 'No rules yet, so everything is allowed.';
    }
    return $mode === 'enforce'
        ? 'Enforce: requests that don\'t match are blocked.'
        : 'Monitor: nothing is blocked, the visitor list shows what would be.';
}

function view_security(): never
{
    $s     = settings()['security'];
    $lock  = read_lockout_state();
    $rules = $s['clients']['rules'];
    $countries = $s['countries']['allowed'];

    // Lockout card
    $lockText = $lock['locked_until'] > time()
        ? '<span class="badge bad">Locked until ' . e(date('H:i', $lock['locked_until'])) . '</span>'
        : '<span class="badge ok">Not locked</span> <span class="muted">' . count($lock['failures']) . ' of ' . max_attempts() . ' wrong passphrases in the last ' . lockout_minutes() . ' minutes</span>';
    $lockCard = '<section class="card"><div class="card-head"><div class="stack" style="gap:6px"><h2>Passphrase lockout</h2><div>' . $lockText . '</div></div>'
        . ($lock['locked_until'] > time() || $lock['failures'] !== [] ? post_button('security', 'clear_lockout', [], 'Clear lockout') : '')
        . '</div></section>';

    // Client whitelist
    $ruleRows = '';
    foreach ($rules as $i => $rule) {
        $min = (int) ($rule['min_version'] ?? 0);
        $ruleRows .= '<tr><td>' . e((string) $rule['client']) . '</td><td class="muted">' . ($min > 0 ? 'Version ' . $min . ' or newer' : 'Any version') . '</td>'
            . '<td class="right">' . post_button('security', 'delete_client_rule', ['index' => $i], icon('trash'), 'icon-btn', 'Remove ' . $rule['client']) . '</td></tr>';
    }
    $options = '';
    foreach (client_names() as $name) {
        $options .= '<option>' . e($name) . '</option>';
    }
    $clientsCard = '<section class="card">'
        . '<div class="card-head"><div><h2>Allowed clients</h2><p class="muted" style="margin-top:4px">' . e(mode_hint($s['clients']['mode'], $rules !== [])) . '</p></div>'
        . mode_switch('set_client_mode', $s['clients']['mode']) . '</div>'
        . ($ruleRows !== '' ? '<div class="table-wrap"><table style="min-width:0"><thead><tr><th scope="col">Client</th><th scope="col">Minimum version</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead><tbody>' . $ruleRows . '</tbody></table></div>' : '')
        . '<form method="post" action="?p=security" class="form-row">' . csrf_field() . '<input type="hidden" name="action" value="add_client_rule">'
        . '<div class="field"><label for="cc">Client</label><select id="cc" name="client">' . $options . '</select></div>'
        . '<div class="field"><label for="cv">Minimum version <span class="hint">0 = any</span></label><input id="cv" type="number" name="min_version" min="0" max="99999" value="0"></div>'
        . '<button type="submit" class="btn ghost">Add client</button></form>'
        . '</section>';

    // Country allowlist
    $chips = '';
    foreach ($countries as $code) {
        $chips .= '<span class="badge neutral" style="gap:4px;padding:2px 2px 2px 10px">' . e($code)
            . post_button('security', 'remove_country', ['country' => $code], '×', 'icon-btn', 'Remove ' . $code, ' style="width:24px;height:24px"') . '</span>';
    }
    $geo = geo_database_available()
        ? 'Geo database loaded, updated ' . e(format_date(date('c', (int) filemtime(GEO_FILE)), false)) . '.'
        : 'The geo database has not been downloaded yet, so countries show as unknown and are not checked.';
    $countriesCard = '<section class="card">'
        . '<div class="card-head"><div><h2>Allowed countries</h2><p class="muted" style="margin-top:4px">' . e(mode_hint($s['countries']['mode'], $countries !== [])) . '</p></div>'
        . mode_switch('set_country_mode', $s['countries']['mode']) . '</div>'
        . ($chips !== '' ? '<div class="chips">' . $chips . '</div>' : '')
        . '<form method="post" action="?p=security" class="form-row">' . csrf_field() . '<input type="hidden" name="action" value="add_country">'
        . '<div class="field"><label for="co">Country code <span class="hint">two letters, e.g. NL or US</span></label><input id="co" type="text" name="country" maxlength="2" pattern="[A-Za-z]{2}" required></div>'
        . '<button type="submit" class="btn ghost">Add country</button></form>'
        . '<p class="muted">Requests from your own network and from addresses without a known country are always allowed. ' . $geo
        . ' <a href="https://db-ip.com" target="_blank" rel="noopener noreferrer">IP Geolocation by DB-IP</a>.</p>'
        . '</section>';

    // Visitors
    $visitorRows = '';
    foreach (list_visitors() as $v) {
        $client  = (string) $v['client'];
        $version = $v['version'];
        $country = (string) $v['country'];
        $label   = $client . ($version !== null ? ' ' . $version : '');
        $badge   = match ($v['status'] ?? '') {
            'allowed'     => '<span class="badge ok">Allowed</span>',
            'would_block' => '<span class="badge warn">Would be blocked</span>',
            'blocked'     => '<span class="badge bad">Blocked</span>',
            default       => '',
        };
        $actions = '';
        if (!client_allowed($client, $version, $rules) || $rules === []) {
            $actions .= post_button('security', 'allow_client', ['client' => $client, 'version' => (int) $version], 'Allow client');
        }
        if ($country !== '' && $country !== 'LAN' && !in_array($country, $countries, true)) {
            $actions .= ' ' . post_button('security', 'add_country', ['country' => $country], 'Allow ' . e($country));
        }
        $visitorRows .= '<tr><td><strong style="font-weight:500">' . e($label) . '</strong><span class="ua" title="' . e((string) ($v['ua'] ?? '')) . '">' . e((string) ($v['ua'] ?? '')) . '</span></td>'
            . '<td class="nowrap">' . e($country === '' ? 'Unknown' : $country) . '</td>'
            . '<td class="muted nowrap">' . e(format_date($v['first'])) . '</td>'
            . '<td class="muted nowrap">' . e(format_date($v['last'])) . '</td>'
            . '<td class="nowrap">' . (int) $v['count'] . '</td>'
            . '<td>' . $badge . '</td>'
            . '<td class="right nowrap">' . $actions . '</td></tr>';
    }
    $visitors = $visitorRows === ''
        ? '<div class="empty"><strong>No visitors yet</strong><span class="muted">Ask your assistant to open the assistant page; it will show up here.</span></div>'
        : '<div class="table-wrap"><table style="min-width:860px"><thead><tr><th scope="col">Client</th><th scope="col">Country</th><th scope="col">First seen</th><th scope="col">Last seen</th><th scope="col">Requests</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead><tbody>'
          . $visitorRows . '</tbody></table></div>';

    $html = page_header('Security', 'Decide which clients and countries may open the assistant side. Start in Monitor mode to see what your assistant really sends.')
        . '<p class="notice warn">Your assistant fetches pages from its own servers, not from your car. Its client name and country are those of the AI provider. Check the visitor list before you switch to Enforce.</p>'
        . $lockCard
        . '<div class="cols"><div class="main-col" style="flex-basis:360px">' . $clientsCard . '</div><div class="main-col" style="flex-basis:360px">' . $countriesCard . '</div></div>'
        . '<section class="stack"><div class="card-head"><div><h2>Recent visitors</h2><p class="muted" style="margin-top:4px">Every client and country that opened the assistant side, newest first.</p></div>'
        . ($visitorRows !== '' ? post_button('security', 'clear_visitors', [], 'Clear list') : '') . '</div>'
        . $visitors . '</section>';

    admin_page('security', 'Security', $html, 'wide');
}

function post_security(array $in): never
{
    $action = (string) ($in['action'] ?? '');
    $mode   = ($in['mode'] ?? '') === 'enforce' ? 'enforce' : 'monitor';

    switch ($action) {
        case 'clear_lockout':
            clear_lockout();
            flash('Lockout cleared.');
            break;

        case 'set_client_mode':
        case 'set_country_mode':
            $key = $action === 'set_client_mode' ? 'clients' : 'countries';
            update_settings(function (array $s) use ($key, $mode) {
                $s['security'][$key]['mode'] = $mode;
                return $s;
            });
            flash(($key === 'clients' ? 'Clients' : 'Countries') . ': ' . ($mode === 'enforce' ? 'now enforcing.' : 'now monitoring only.'));
            break;

        case 'add_client_rule':
        case 'allow_client':
            $client = (string) ($in['client'] ?? '');
            $min    = max(0, min(99999, (int) ($in['min_version'] ?? $in['version'] ?? 0)));
            if (!in_array($client, client_names(), true)) {
                flash('Unknown client.', 'error');
                break;
            }
            update_settings(function (array $s) use ($client, $min) {
                // One rule per client: replace an existing one.
                $rules = array_filter((array) $s['security']['clients']['rules'], fn ($r) => ($r['client'] ?? '') !== $client);
                $rules[] = ['client' => $client, 'min_version' => $min];
                $s['security']['clients']['rules'] = array_values($rules);
                return $s;
            });
            flash($client . ($min > 0 ? ' ' . $min . ' and newer' : '') . ' is now allowed.');
            break;

        case 'delete_client_rule':
            $index = (int) ($in['index'] ?? -1);
            update_settings(function (array $s) use ($index) {
                $rules = (array) $s['security']['clients']['rules'];
                unset($rules[$index]);
                $s['security']['clients']['rules'] = array_values($rules);
                return $s;
            });
            flash('Client rule removed.');
            break;

        case 'add_country':
            $country = strtoupper(trim((string) ($in['country'] ?? '')));
            if (!preg_match('/^[A-Z]{2}$/', $country)) {
                flash('Use a two-letter country code, like NL or US.', 'error');
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
            flash($country . ' is now allowed.');
            break;

        case 'remove_country':
            $country = (string) ($in['country'] ?? '');
            update_settings(function (array $s) use ($country) {
                $s['security']['countries']['allowed'] = array_values(array_filter((array) $s['security']['countries']['allowed'], fn ($c) => $c !== $country));
                return $s;
            });
            flash($country . ' removed.');
            break;

        case 'clear_visitors':
            clear_visitors();
            flash('Visitor list cleared.');
            break;
    }
    redirect_to(['p' => 'security']);
}
