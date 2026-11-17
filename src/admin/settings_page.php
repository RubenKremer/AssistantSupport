<?php
/**
 * Admin: Settings. Passphrase, public address, lockout limits and the public
 * welcome page.
 */

declare(strict_types=1);

function view_settings(): never
{
    $s = settings();

    $status = $s['passphrase_hash'] !== ''
        ? '<span class="badge ok">Set</span> <span class="muted">Last changed ' . e(lcfirst(format_date($s['passphrase_set_at']))) . '</span>'
        : '<span class="badge bad">Not set</span> <span class="muted">The private area stays closed until you set one.</span>';

    $passphrase = '<section class="card"><div class="stack" style="gap:6px"><h2>Passphrase</h2><div>' . $status . '</div></div>'
        . '<form method="post" action="?p=settings" class="stack">' . csrf_field() . '<input type="hidden" name="action" value="save_passphrase">'
        . '<div class="field"><label for="pp">New passphrase</label>'
        . '<input id="pp" type="text" name="passphrase" autocomplete="off" spellcheck="false" required minlength="5" placeholder="the purple elephant sings loudly"></div>'
        . '<p class="muted">A sentence of at least ' . MIN_PASSPHRASE_WORDS . ' words. This is what you say to your assistant, so pick something easy to pronounce. '
        . 'Capitals, spaces and punctuation don\'t matter. It is stored as a one-way hash: it can be replaced here, but never shown again.</p>'
        . '<div><button type="submit" class="btn">Save passphrase</button></div></form></section>';

    $general = '<section class="card"><h2>Assistant side</h2>'
        . '<form method="post" action="?p=settings" class="stack">' . csrf_field() . '<input type="hidden" name="action" value="save_general">'
        . '<div class="field"><label for="bu">Public address <span class="hint">without a trailing slash</span></label>'
        . '<input id="bu" type="text" inputmode="url" name="base_url" maxlength="300" placeholder="https://assistant.example.com" value="' . e((string) $s['base_url']) . '"></div>'
        . '<p class="muted">Used in the links on the assistant pages. Leave empty to take it from each request.</p>'
        . '<div class="grid2">'
        . '<div class="field"><label for="ma">Wrong passphrases before lockout</label><input id="ma" type="number" name="max_attempts" min="1" max="100" value="' . (int) $s['max_attempts'] . '"></div>'
        . '<div class="field"><label for="lm">Lockout duration <span class="hint">minutes</span></label><input id="lm" type="number" name="lockout_minutes" min="1" max="1440" value="' . (int) $s['lockout_minutes'] . '"></div>'
        . '</div><div><button type="submit" class="btn">Save</button></div></form></section>';

    $welcome = '<section class="card"><div><h2>Welcome page</h2><p class="muted" style="margin-top:4px">The public page your assistant reads first. It explains how to ask for the passphrase.</p></div>'
        . '<form method="post" action="?p=settings" class="stack">' . csrf_field() . '<input type="hidden" name="action" value="save_welcome">'
        . '<div class="field"><div class="field-head"><label for="wp">Markdown</label><span class="hint"><code>{{base}}</code> becomes the public address</span></div>'
        . '<textarea id="wp" name="welcome" rows="22" class="mono">' . e(read_content('public/index.md')) . '</textarea></div>'
        . '<div><button type="submit" class="btn">Save welcome page</button></div></form></section>';

    admin_page('settings', 'Settings', page_header('Settings', 'How the assistant side works and how it is protected.') . $passphrase . $general . $welcome);
}

function post_settings(array $in): never
{
    switch ((string) ($in['action'] ?? '')) {
        case 'save_passphrase':
            $error = set_passphrase((string) ($in['passphrase'] ?? ''));
            $error === null ? flash('Passphrase saved. Use it the next time you talk to your assistant.') : flash($error, 'error');
            break;

        case 'save_general':
            $baseUrl = rtrim(trim((string) ($in['base_url'] ?? '')), '/');
            if ($baseUrl !== '' && normalize_url($baseUrl) === null) {
                flash('The public address must be a valid http(s) address.', 'error');
                break;
            }
            update_settings(function (array $s) use ($in, $baseUrl) {
                $s['base_url']        = $baseUrl === '' ? '' : rtrim((string) normalize_url($baseUrl), '/');
                $s['max_attempts']    = max(1, min(100, (int) ($in['max_attempts'] ?? 5)));
                $s['lockout_minutes'] = max(1, min(1440, (int) ($in['lockout_minutes'] ?? 15)));
                return $s;
            });
            flash('Settings saved.');
            break;

        case 'save_welcome':
            $file = CONTENT_DIR . '/public/index.md';
            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0775, true);
            }
            file_put_contents($file, clean_text((string) ($in['welcome'] ?? ''), 50000) . "\n", LOCK_EX);
            flash('Welcome page saved.');
            break;
    }
    redirect_to(['p' => 'settings']);
}
