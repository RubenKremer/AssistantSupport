<?php
/**
 * Admin: Settings. Assistant access (passphrase, lockout), the assistant page
 * (public address, welcome text) and the admin account (read-only: it comes
 * from the container's environment).
 */

declare(strict_types=1);

const ATTEMPT_OPTIONS = [3, 5, 10];
const LOCKOUT_OPTIONS = [5 => '5 minutes', 15 => '15 minutes', 60 => '1 hour', 1440 => '1 day'];

/** A random, easy-to-say sentence like "seven orange owls read maps by the river". */
function suggest_passphrase(): string
{
    $pick = fn (array $words) => $words[random_int(0, count($words) - 1)];
    return implode(' ', [
        $pick(['two', 'three', 'five', 'seven', 'nine', 'twelve']),
        $pick(['orange', 'quiet', 'purple', 'sleepy', 'golden', 'little', 'silver', 'happy', 'brave', 'windy']),
        $pick(['owls', 'boats', 'bicycles', 'lanterns', 'pianos', 'turtles', 'kites', 'teapots', 'lighthouses', 'penguins']),
        $pick(['read maps', 'sing softly', 'dream loudly', 'dance slowly', 'count stars', 'hum tunes', 'bake bread', 'paint clouds']),
        $pick(['by the river', 'on tuesdays', 'in the attic', 'near the harbour', 'after midnight', 'under the bridge', 'in the rain']),
    ]);
}

function select_options(array $options, int $current): string
{
    if (!isset($options[$current])) {
        $options[$current] = (string) $current; // keep a value set by hand in settings.json
        ksort($options);
    }
    $html = '';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . $value . '"' . ($value === $current ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $html;
}

function view_settings(): never
{
    $s    = settings();
    $lock = read_lockout_state();

    // Passphrase status line under the field.
    $suggestion = isset($_GET['suggest']) ? suggest_passphrase() : '';
    if ($suggestion !== '') {
        $status = '<span class="good">' . passphrase_word_count($suggestion) . ' words · ready to use once you save</span>';
    } elseif ($s['passphrase_hash'] !== '') {
        $status = '<span class="good">Set · last changed ' . e(lcfirst(format_date($s['passphrase_set_at']))) . '</span>';
    } else {
        $status = '<span class="bad">Not set: the private area stays closed until you set one</span>';
    }

    // Lockout box (where the design shows open sessions).
    $locked   = $lock['locked_until'] > time();
    $lockText = $locked
        ? 'Locked until ' . date('H:i', $lock['locked_until'])
        : 'Not locked · ' . count($lock['failures']) . ' of ' . max_attempts() . ' wrong attempts in the last ' . lockout_minutes() . ' minutes';

    $attempts = [];
    foreach (ATTEMPT_OPTIONS as $n) {
        $attempts[$n] = (string) $n;
    }

    $saved = $s['updated'] ?? null ? 'Last saved ' . lcfirst(format_date($s['updated'])) : '';

    $html = page_header('Settings', 'How assistants get in, and your own admin account.',
            '<span class="muted">' . e($saved) . '</span><button type="submit" form="settings-form" class="btn">Save changes</button>')

        . '<form id="clear-lockout" method="post" action="?p=settings">' . csrf_field() . '<input type="hidden" name="action" value="clear_lockout"></form>'
        . '<form id="settings-form" method="post" action="?p=settings" class="stack" style="gap:32px">' . csrf_field()
        . '<input type="hidden" name="action" value="save_settings">'

        // Assistant access
        . '<section class="panel"><div class="panel-head"><h2>Assistant access</h2>'
        . '<p class="muted">You speak the passphrase to your assistant; it puts the sentence in the link to open your private page.</p></div>'
        . '<div class="panel-body stack" style="gap:24px">'
        . '<div class="field" style="gap:8px"><label for="pp">Passphrase</label>'
        . '<div class="form-row" style="gap:8px"><input id="pp" class="big" type="text" name="passphrase" autocomplete="off" spellcheck="false" aria-describedby="pph"'
        . ' placeholder="Type a new sentence to replace the current one" value="' . e($suggestion) . '" style="flex:1 1 320px">'
        . '<a class="btn ghost" href="?p=settings&amp;suggest=' . random_int(1, 999999) . '">' . icon('refresh') . 'Suggest one</a></div>'
        . '<div id="pph" class="field-foot">' . $status . '<span class="hint">A full sentence of at least ' . MIN_PASSPHRASE_WORDS . ' words. Stored as a one-way hash, so it can be replaced but never shown.</span></div></div>'
        . '<div class="grid2">'
        . '<div class="field"><label for="ma">Wrong attempts before lockout</label><select id="ma" name="max_attempts">' . select_options($attempts, (int) $s['max_attempts']) . '</select></div>'
        . '<div class="field"><label for="lm">Lockout lasts</label><select id="lm" name="lockout_minutes">' . select_options(LOCKOUT_OPTIONS, (int) $s['lockout_minutes']) . '</select></div>'
        . '</div>'
        . '<div class="box row"><div class="stack" style="gap:2px"><span class="label" style="font-size:14px">' . e($lockText) . '</span>'
        . '<span class="muted">Changing the passphrase does not clear the lockout.</span></div>'
        . ($locked || $lock['failures'] !== [] ? '<button type="submit" form="clear-lockout" class="btn ghost small danger-text">Clear lockout</button>' : '')
        . '</div></div></section>'

        // Assistant page
        . '<section class="panel"><div class="panel-head"><h2>Assistant page</h2>'
        . '<p class="muted">Where your assistant finds you, and the public page it reads first.</p></div>'
        . '<div class="panel-body stack" style="gap:20px">'
        . '<div class="field"><label for="bu">Public address <span class="hint">without a trailing slash</span></label>'
        . '<input id="bu" type="text" inputmode="url" name="base_url" maxlength="300" placeholder="https://assistant.example.com" value="' . e((string) $s['base_url']) . '">'
        . '<span class="hint">Used in the links on the assistant pages. Leave empty to take it from each request.</span></div>'
        . '<div class="field"><div class="field-head"><label for="wp">Welcome page</label><span class="hint">Markdown · <code>{{base}}</code> becomes the public address</span></div>'
        . '<textarea id="wp" name="welcome" rows="18" class="mono">' . e(read_content('public/index.md')) . '</textarea></div>'
        . '</div></section>'
        . '</form>'

        // Admin account (read-only)
        . '<section class="panel"><div class="panel-head"><h2>Admin account</h2>'
        . '<p class="muted">Set on the container, so nobody can change it from inside this panel.</p></div>'
        . '<div class="panel-body grid2">'
        . '<div class="field"><label for="un">Username</label><input id="un" type="text" value="' . e(admin_user()) . '" readonly></div>'
        . '<div class="field"><span class="label">Password</span><p class="muted" style="padding-top:10px">Set with the <code>ADMIN_PASSWORD</code> variable. To change it, edit the container (in Unraid: Docker → AssistantSupport → Edit), then apply.</p></div>'
        . '</div></section>';

    admin_page('settings', 'Settings', $html);
}

function post_settings(array $in): never
{
    $action = (string) ($in['action'] ?? '');

    if ($action === 'clear_lockout') {
        clear_lockout();
        flash('Lockout cleared.');
        redirect_to(['p' => 'settings']);
    }
    if ($action !== 'save_settings') {
        redirect_to(['p' => 'settings']);
    }

    $baseUrl = rtrim(trim((string) ($in['base_url'] ?? '')), '/');
    if ($baseUrl !== '' && normalize_url($baseUrl) === null) {
        flash('The public address must be a valid http(s) address. Nothing was saved.', 'error');
        redirect_to(['p' => 'settings']);
    }

    update_settings(function (array $s) use ($in, $baseUrl) {
        $s['base_url']        = $baseUrl === '' ? '' : rtrim((string) normalize_url($baseUrl), '/');
        $s['max_attempts']    = max(1, min(100, (int) ($in['max_attempts'] ?? 5)));
        $s['lockout_minutes'] = max(1, min(1440, (int) ($in['lockout_minutes'] ?? 15)));
        $s['updated']         = date('c');
        return $s;
    });

    $welcome = CONTENT_DIR . '/public/index.md';
    if (!is_dir(dirname($welcome))) {
        mkdir(dirname($welcome), 0775, true);
    }
    file_put_contents($welcome, clean_text((string) ($in['welcome'] ?? ''), 50000) . "\n", LOCK_EX);

    // The passphrase field is only used when something was typed in it.
    $passphrase = trim((string) ($in['passphrase'] ?? ''));
    if ($passphrase === '') {
        flash('Settings saved.');
    } elseif (($error = set_passphrase($passphrase)) !== null) {
        flash('Settings saved, but the passphrase was not changed. ' . $error, 'error');
    } else {
        flash('Settings saved, including the new passphrase. Use it the next time you talk to your assistant.');
    }
    redirect_to(['p' => 'settings']);
}
