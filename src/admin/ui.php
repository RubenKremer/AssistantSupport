<?php
/**
 * Admin panel basics: login session, CSRF tokens, flash messages, icons and
 * the page layout (sidebar + content) from the designs. No JavaScript: every
 * button is a normal link or form.
 */

declare(strict_types=1);

const ADMIN_IDLE_SECONDS = 12 * 3600;

// ---------------------------------------------------------------------------
// Session, login and CSRF
// ---------------------------------------------------------------------------

function start_admin_session(): void
{
    session_name('assistant_admin');
    session_start([
        'cookie_httponly'  => true,
        'cookie_samesite'  => 'Strict',
        'use_strict_mode'  => true,
        'use_only_cookies' => true,
    ]);
    $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function admin_user(): string
{
    return (string) (getenv('ADMIN_USER') ?: 'admin');
}

function is_logged_in(): bool
{
    if (empty($_SESSION['admin'])) {
        return false;
    }
    if (time() - (int) ($_SESSION['seen'] ?? 0) > ADMIN_IDLE_SECONDS) {
        logout();
        return false;
    }
    $_SESSION['seen'] = time();
    return true;
}

function logout(): void
{
    $_SESSION = ['csrf' => bin2hex(random_bytes(16))];
    session_regenerate_id(true);
}

/** Shows the login page, or logs in and redirects. Never returns. */
function handle_login(): never
{
    $password = (string) getenv('ADMIN_PASSWORD');
    if ($password === '') {
        login_page('The admin panel is disabled. Set the ADMIN_PASSWORD environment variable on the container and restart it.', 503);
    }

    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $user = (string) ($_POST['username'] ?? '');
        $pass = (string) ($_POST['password'] ?? '');
        if (hash_equals(admin_user(), $user) && hash_equals($password, $pass)) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['seen']  = time();
            redirect_to([]);
        }
        sleep(1); // slow down guessing
        $error = 'Wrong username or password.';
    }
    login_page($error);
}

function require_csrf(): void
{
    if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        flash('The form had expired. Please try again.', 'error');
        redirect_to($_GET);
    }
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function redirect_to(array $params): never
{
    header('Location: ./' . ($params === [] ? '' : '?' . http_build_query($params)));
    exit;
}

function flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'] = [$type, $message];
}

function flash_html(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    [$type, $message] = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<p class="notice' . ($type === 'error' ? ' error' : '') . '" role="status">' . e($message) . '</p>';
}

// ---------------------------------------------------------------------------
// Small HTML helpers
// ---------------------------------------------------------------------------

const ICONS = [
    'backstory' => '<path d="M4 5h6a2 2 0 0 1 2 2v12a2 2 0 0 0-2-2H4z"/><path d="M20 5h-6a2 2 0 0 0-2 2v12a2 2 0 0 1 2-2h6z"/>',
    'skills'    => '<path d="M13 3L5 14h6l-1 7 8-11h-6l1-7z"/>',
    'links'     => '<path d="M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1"/><path d="M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1"/>',
    'memories'  => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
    'security'  => '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"/>',
    'settings'  => '<path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2"/><circle cx="10" cy="17" r="2"/>',
    'external'  => '<path d="M14 4h6v6"/><path d="M20 4l-9 9"/><path d="M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/>',
    'signout'   => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 16l-4-4 4-4"/><path d="M6 12h10"/>',
    'plus'      => '<path d="M12 5v14M5 12h14"/>',
    'search'    => '<circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5"/>',
    'edit'      => '<path d="M4 20h4L19 9l-4-4L4 16v4z"/>',
    'trash'     => '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/>',
    'usage'     => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>',
    'public'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18a14 14 0 0 1 0-18z"/>',
    'refresh'   => '<path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/>',
];

function icon(string $name): string
{
    return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ICONS[$name] . '</svg>';
}

/** A form with one button that POSTs an action (plus hidden fields) to the current page. */
function post_button(string $page, string $action, array $fields, string $label, string $class = 'btn ghost small', string $ariaLabel = '', string $extraAttrs = ''): string
{
    $html = '<form class="inline" method="post" action="?p=' . e($page) . '">' . csrf_field()
        . '<input type="hidden" name="action" value="' . e($action) . '">';
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . e($name) . '" value="' . e((string) $value) . '">';
    }
    $aria = $ariaLabel !== '' ? ' aria-label="' . e($ariaLabel) . '"' : '';
    return $html . '<button type="submit" class="' . e($class) . '"' . $aria . $extraAttrs . '>' . $label . '</button></form>';
}

/** A short, collapsible Markdown cheat sheet for under text areas. No JavaScript: <details>. */
function markdown_help(): string
{
    $rows = [
        ['**important**', 'bold, for things the assistant must not miss'],
        ['`https://example.com/?q={value}`', 'code, for URLs and exact text to use'],
        ['- item', 'a bullet list (one per line)'],
        ['1. step', 'numbered steps'],
        ['## Heading', 'a heading inside the text'],
        ['[label](https://…)', 'a link'],
        ['> note', 'a highlighted note'],
    ];
    $html = '';
    foreach ($rows as [$syntax, $meaning]) {
        $html .= '<tr><td><code>' . e($syntax) . '</code></td><td class="muted">' . e($meaning) . '</td></tr>';
    }
    return '<details class="md-help"><summary>Markdown cheat sheet</summary>'
        . '<table><tbody>' . $html . '</tbody></table></details>';
}

function page_header(string $title, string $subtitle, string $actions = ''): string
{
    return '<header class="page-head"><div><h1>' . e($title) . '</h1><p class="sub">' . e($subtitle) . '</p></div>'
        . ($actions !== '' ? '<div class="head-actions">' . $actions . '</div>' : '') . '</header>';
}

function search_form(string $page, string $query, string $placeholder, array $extra = []): string
{
    $hidden = '<input type="hidden" name="p" value="' . e($page) . '">';
    foreach ($extra as $name => $value) {
        $hidden .= '<input type="hidden" name="' . e($name) . '" value="' . e((string) $value) . '">';
    }
    return '<form class="search" method="get" role="search">' . $hidden . icon('search')
        . '<label class="sr-only" for="q">' . e($placeholder) . '</label>'
        . '<input id="q" type="search" name="q" value="' . e($query) . '" placeholder="' . e($placeholder) . '"></form>';
}

// ---------------------------------------------------------------------------
// Layout
// ---------------------------------------------------------------------------

const ADMIN_NAV = [
    'Context'   => ['backstory' => 'Backstory', 'skills' => 'Skills', 'links' => 'Links', 'public' => 'Public page'],
    'History'   => ['memories' => 'Memories', 'usage' => 'Skill usage'],
    'Configure' => ['security' => 'Security', 'settings' => 'Settings'],
];

function admin_page(string $active, string $title, string $content, string $pageClass = ''): never
{
    $nav = '';
    foreach (ADMIN_NAV as $group => $items) {
        $nav .= '<div class="group"><span class="group-label">' . $group . '</span>';
        foreach ($items as $key => $label) {
            $current = $key === $active ? ' aria-current="page"' : '';
            $count   = match ($key) {
                'memories' => '<span class="count">' . count_memories() . '</span>',
                'usage'    => '<span class="count">' . count(list_skill_uses()) . '</span>',
                default    => '',
            };
            $nav .= '<a href="?p=' . $key . '"' . $current . '>' . icon($key) . '<span>' . $label . '</span>' . $count . '</a>';
        }
        $nav .= '</div>';
    }

    $baseUrl = rtrim((string) settings()['base_url'], '/');
    $assistantLink = $baseUrl !== ''
        ? '<a href="' . e($baseUrl) . '/" target="_blank" rel="noopener">' . icon('external') . '<span>Assistant page</span></a>'
        : '';

    $body = '<div class="shell">'
        . '<aside class="side"><div class="brand"><span class="mark">' . logo_mark(16) . '</span><span>Assistant<span class="light">Support</span></span></div>'
        . '<nav class="nav" aria-label="Main">' . $nav . '</nav>'
        . '<div class="nav side-foot">' . $assistantLink . '<a href="?p=logout">' . icon('signout') . '<span>Sign out</span></a></div></aside>'
        . '<main class="content"><div class="page ' . e($pageClass) . '">' . flash_html() . $content . '</div></main>'
        . '</div>';

    admin_document($title, $body);
}

function login_page(string $message = '', int $status = 200): never
{
    $notice = $message !== '' ? '<p class="notice error" role="alert">' . e($message) . '</p>' : '';
    $form = (string) getenv('ADMIN_PASSWORD') === '' ? '' :
        '<form method="post" action="?p=login" class="stack">' . csrf_field()
        . '<div class="field"><label for="u">Username</label><input id="u" type="text" name="username" autocomplete="username" required value="' . e(admin_user()) . '"></div>'
        . '<div class="field"><label for="pw">Password</label><input id="pw" type="password" name="password" autocomplete="current-password" required autofocus></div>'
        . '<button type="submit" class="btn wide">Sign in</button></form>';

    $body = '<div class="login"><main class="login-box">'
        . '<div class="brand big"><span class="mark">' . logo_mark(19) . '</span><span>Assistant<span class="light">Support</span></span><span class="pill">Admin</span></div>'
        . '<div class="login-head"><h1>Welcome back</h1><p class="sub">Manage your assistant\'s context and memories.</p></div>'
        . $notice . $form . '</main>'
        . '<p class="login-foot">AssistantSupport · Admin</p></div>';

    http_response_code($status);
    admin_document('Sign in', $body);
}

function admin_document(string $title, string $body): never
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex, nofollow">'
        . '<title>' . e($title) . ' · AssistantSupport admin</title>'
        . FAVICON_LINKS
        . '<style>' . FONT_CSS . ADMIN_CSS . '</style></head><body>' . $body . '</body></html>';
    exit;
}

const ADMIN_CSS = <<<'CSS'
*{box-sizing:border-box}
body{margin:0;background:#FFFFFF;color:#0A0A0A;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;line-height:1.5}
a{color:#0A0A0A}a:hover{color:#4338CA}
h1{margin:0;font-size:26px;font-weight:600;letter-spacing:-.03em}
h2{margin:0;font-size:16px;font-weight:600}
h3{margin:0;font-size:14px;font-weight:600}
p{margin:0}
code{font-family:"JetBrains Mono",ui-monospace,Consolas,monospace;font-size:12px;background:#F5F5F5;padding:1px 5px;border-radius:5px}
.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
.brand{display:flex;align-items:center;gap:10px;padding:0 8px;font-size:15px;font-weight:600;letter-spacing:-.03em}
.brand .light{color:#8A8A8A}
.mark{width:26px;height:26px;border-radius:7px;background:#0A0A0A;display:flex;align-items:center;justify-content:center;flex:none}
.shell{min-height:100vh;display:flex;flex-wrap:wrap}
.side{flex:1 1 232px;padding:20px 12px;background:#FAFAFA;border-right:1px solid #EDEDED;display:flex;flex-direction:column;gap:24px}
.nav{display:flex;flex-direction:column;gap:20px}
.group{display:flex;flex-direction:column;gap:2px}
.group-label{padding:0 10px 6px;font-size:11px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;color:#737373}
.nav a{display:flex;align-items:center;gap:10px;min-height:36px;padding:0 10px;border-radius:8px;font-size:14px;color:#525252;text-decoration:none}
.nav a:hover{background:#F0F0F0;color:#0A0A0A}
.nav a[aria-current=page]{background:#FFFFFF;box-shadow:0 0 0 1px #E8E8E8;font-weight:500;color:#0A0A0A}
.nav a span:first-of-type{flex:1 1 auto}
.nav .count{flex:none;font-size:12px;color:#737373}
.side-foot{margin-top:auto;gap:2px;border-top:1px solid #EDEDED;padding-top:12px}
.side-foot a{font-size:13px}
.content{flex:999 1 560px;min-width:0;padding:40px 24px 64px}
.page{max-width:960px;margin:0 auto;display:flex;flex-direction:column;gap:28px}
.page.wide{max-width:1200px}
.page-head{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:16px}
.sub{margin-top:6px;font-size:14px;color:#6B6B6B}
.head-actions{display:flex;flex-wrap:wrap;align-items:center;gap:12px}
.muted{font-size:13px;line-height:1.5;color:#6B6B6B}
.stack{display:flex;flex-direction:column;gap:16px}
.btn{min-height:40px;padding:0 18px;border:0;border-radius:10px;background:#0A0A0A;color:#FFFFFF;font:inherit;font-size:14px;font-weight:500;display:inline-flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;cursor:pointer;white-space:nowrap}
.btn:hover{opacity:.88;color:#FFFFFF}
.btn.ghost{background:#FFFFFF;color:#0A0A0A;border:1px solid #E4E4E4}
.btn.ghost:hover{background:#F5F5F5;opacity:1;color:#0A0A0A}
.btn.small{min-height:34px;padding:0 12px;font-size:13px}
.btn.danger{background:#B91C1C}
.btn.wide{width:100%;min-height:44px;margin-top:8px}
.icon-btn{width:36px;height:36px;border:0;border-radius:8px;background:transparent;color:#737373;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}
.icon-btn:hover{background:#F2F2F2;color:#0A0A0A}
form.inline{display:inline-flex;margin:0}
.field{display:flex;flex-direction:column;gap:6px}
.field-head{display:flex;justify-content:space-between;align-items:baseline;gap:8px}
label,.label,legend{font-size:13px;font-weight:500;padding:0}
.hint{font-size:12px;color:#737373;font-weight:400}
input[type=text],input[type=url],input[type=search],input[type=password],input[type=number],select,textarea{width:100%;min-height:42px;padding:0 12px;border:1px solid #E4E4E4;border-radius:10px;font:inherit;font-size:14px;color:#0A0A0A;background:#FFFFFF}
textarea{padding:14px 16px;line-height:1.7;resize:vertical}
textarea.mono{font-family:"JetBrains Mono",ui-monospace,Consolas,monospace;font-size:13px}
input:focus,textarea:focus,select:focus{outline:none;border-color:#0A0A0A;box-shadow:0 0 0 3px rgba(10,10,10,.06)}
:focus-visible{outline:2px solid #4338CA;outline-offset:2px}
input[type=checkbox]{width:18px;height:18px;accent-color:#0A0A0A}
.check{display:flex;align-items:center;gap:10px;font-size:14px;font-weight:400}
fieldset{border:0;margin:0;padding:0;display:flex;flex-direction:column;gap:14px}
.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px}
.cols{display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start}
.main-col{flex:999 1 420px;min-width:0;display:flex;flex-direction:column;gap:20px}
.side-col{flex:1 1 260px;min-width:0;display:flex;flex-direction:column;gap:16px}
.card{border:1px solid #EDEDED;border-radius:14px;padding:20px;display:flex;flex-direction:column;gap:14px}
.card.soft{background:#FAFAFA;border-color:#FAFAFA;gap:6px}
.card.well{background:#FAFAFA}
.card-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px}
.form-row{display:flex;flex-direction:row;flex-wrap:wrap;align-items:flex-end;gap:12px}
.card.form-row{padding:16px}
.form-row>.btn{flex:none}
.form-row>.field{flex:1 1 200px;min-width:0}
.form-row>.field.grow{flex:2 1 280px}
.plain-link{color:inherit;text-decoration:none}
.plain-link:hover{color:#0A0A0A;text-decoration:underline}
.chips{display:flex;flex-wrap:wrap;gap:8px}
.chip{position:relative}
.chip input{position:absolute;opacity:0;inset:0;margin:0}
.chip span{display:inline-flex;align-items:center;min-height:34px;padding:0 14px;border-radius:17px;border:1px solid #E4E4E4;background:#FFFFFF;font-size:13px;font-weight:400;color:#3F3F3F;cursor:pointer}
.chip input:checked+span{background:#0A0A0A;border-color:#0A0A0A;color:#FFFFFF}
.chip input:focus-visible+span{outline:2px solid #4338CA;outline-offset:2px}
.segmented{display:inline-flex;padding:3px;border-radius:10px;background:#F2F2F2;gap:2px}
.segmented button{min-height:30px;padding:0 12px;border:0;border-radius:8px;background:transparent;font:inherit;font-size:13px;color:#525252;cursor:pointer}
.segmented button[aria-pressed=true]{background:#FFFFFF;color:#0A0A0A;font-weight:500;box-shadow:0 1px 2px rgba(0,0,0,.08)}
.toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px}
.search{position:relative;flex:1 1 280px;max-width:360px;margin:0}
.search svg{position:absolute;left:12px;top:13px;color:#737373}
.search input{padding-left:36px;min-height:40px}
.list{list-style:none;margin:0;padding:0;border:1px solid #EDEDED;border-radius:14px;overflow:hidden}
.list>li{display:flex;align-items:center;gap:20px;padding:18px 16px 18px 20px;background:#FFFFFF}
.list>li+li{border-top:1px solid #EDEDED}
.list>li.off .row-main{opacity:.6}
.row-main{flex:1 1 auto;min-width:0;display:flex;flex-direction:column;gap:4px}
.row-title{display:flex;flex-wrap:wrap;align-items:center;gap:8px;font-size:14px;font-weight:600}
.tag{font-size:11px;font-weight:500;padding:2px 8px;border-radius:6px;background:#F4F4F5;color:#52525B}
.actions{flex:none;display:flex;align-items:center;gap:4px}
.switch{position:relative;width:40px;height:24px;padding:0;border:0;border-radius:12px;background:#D4D4D4;cursor:pointer;margin-right:8px}
.switch::after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:9px;background:#FFFFFF;box-shadow:0 1px 2px rgba(0,0,0,.2)}
.switch[aria-checked=true]{background:#0A0A0A}
.switch[aria-checked=true]::after{left:19px}
.table-wrap{overflow-x:auto;border:1px solid #EDEDED;border-radius:14px}
table{width:100%;min-width:620px;border-collapse:collapse;font-size:14px}
th{padding:12px 20px;font-size:12px;font-weight:500;color:#6B6B6B;border-bottom:1px solid #EDEDED;text-align:left;white-space:nowrap}
td{padding:12px 20px;border-bottom:1px solid #F2F2F2;vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
tbody tr:hover{background:#FAFAFA}
td.nowrap,.nowrap{white-space:nowrap}
td.right{text-align:right}
.title-cell{display:flex;align-items:center;gap:12px;font-weight:500}
.initial{flex:none;width:28px;height:28px;border-radius:7px;background:#F4F4F5;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;color:#3F3F46}
.ua{display:block;max-width:360px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:#737373}
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:6px;font-size:12px;font-weight:500;white-space:nowrap}
.badge.ok{background:#F0FDF4;color:#166534}
.badge.warn{background:#FFFBEB;color:#92400E}
.badge.bad{background:#FEF2F2;color:#991B1B}
.badge.neutral{background:#F4F4F5;color:#52525B}
.notice{padding:12px 16px;border-radius:10px;background:#F0FDF4;color:#166534;font-size:14px}
.notice.error{background:#FEF2F2;color:#991B1B}
.notice.warn{background:#FFFBEB;color:#92400E}
.empty{padding:32px 20px;border:1px dashed #E4E4E4;border-radius:14px;text-align:center;display:flex;flex-direction:column;gap:6px}
.split{display:flex;flex-wrap:wrap;gap:0;border:1px solid #EDEDED;border-radius:14px;overflow:hidden;min-height:560px}
.mem-list{flex:1 1 300px;min-width:0;border-right:1px solid #EDEDED;background:#FFFFFF;display:flex;flex-direction:column}
.mem-list .search{max-width:none;flex:none;margin:12px}
.mem-items{list-style:none;margin:0;padding:0 8px 8px;display:flex;flex-direction:column;gap:2px;overflow-y:auto;max-height:720px}
.mem{display:flex;flex-direction:column;gap:4px;padding:12px;border-radius:10px;text-decoration:none;color:inherit}
.mem:hover{background:#F7F7F7;color:inherit}
.mem[aria-current=true]{background:#F2F2F2}
.mem-top{display:flex;justify-content:space-between;gap:8px;font-size:14px;font-weight:500}
.mem-when{flex:none;font-size:12px;font-weight:400;color:#737373}
.mem-sum{font-size:13px;line-height:1.5;color:#6B6B6B;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.mem-detail{flex:999 1 420px;min-width:0;padding:28px;display:flex;flex-direction:column;gap:24px}
.mem-detail article{display:flex;flex-direction:column;gap:24px}
.meta{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;font-size:13px;color:#6B6B6B}
.section-label{font-size:12px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;color:#737373}
.points{margin:0;padding-left:20px;font-size:14px;line-height:1.7;color:#3F3F3F}
.summary{font-size:15px;line-height:1.7;color:#262626;white-space:pre-line}
.login{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 16px;background:#FAFAFA;position:relative}
.login-box{width:100%;max-width:360px;display:flex;flex-direction:column;gap:28px}
.brand.big{font-size:18px;padding:0}
.brand.big .mark{width:32px;height:32px;border-radius:9px}
.pill{margin-left:2px;padding:2px 8px;border-radius:6px;background:#F0F0F0;font-size:11px;font-weight:500;color:#525252;letter-spacing:0}
.login-head h1{font-size:24px;letter-spacing:-.02em}
.login-foot{position:absolute;bottom:24px;left:0;right:0;text-align:center;font-size:12px;color:#737373}
.panel{border:1px solid #EDEDED;border-radius:16px;overflow:hidden}
.panel-head{padding:20px 24px;border-bottom:1px solid #EDEDED;display:flex;flex-direction:column;gap:4px}
.panel-head.row{flex-direction:row;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px}
.panel-head.row>div{display:flex;flex-direction:column;gap:4px;flex:1 1 320px}
.panel-body{padding:24px}
.list.flat{border:0;border-radius:0}
.list.flat>li{padding:12px 24px;gap:12px}
.box{padding:14px 16px;border-radius:12px;background:#FAFAFA}
.box.row{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px}
.field-foot{display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;font-size:13px}
.good{font-weight:500;color:#15803D}
.bad{font-weight:500;color:#B91C1C}
input.big{min-height:44px;font-size:15px}
input[readonly]{background:#FAFAFA;color:#525252}
.danger-text{color:#B91C1C}
.danger-text:hover{color:#B91C1C}
.switch.lg{width:44px;height:26px;border-radius:13px;margin:0;flex:none}
.switch.lg::after{width:20px;height:20px;border-radius:10px}
.switch.lg[aria-checked=true]::after{left:21px}
.pill{display:inline-flex;align-items:center;min-height:24px;padding:0 10px;border-radius:12px;font-size:12px;font-weight:500;white-space:nowrap}
.pill.ok{background:#ECFDF3;color:#15803D}
.pill.bad{background:#FEF2F2;color:#B91C1C}
.pill.warn{background:#FFFBEB;color:#92400E}
.status-cell{display:inline-flex;align-items:center;gap:8px}
.country{display:inline-flex;align-items:center;gap:8px;min-height:34px;padding:0 4px 0 6px;border:1px solid #E4E4E4;border-radius:17px;font-size:13px}
.country .code{padding:2px 6px;border-radius:10px;background:#F4F4F5;font-size:11px;font-weight:600;color:#3F3F46}
.icon-btn.tiny{width:26px;height:26px;border-radius:13px}
.blocked{list-style:none;margin:0;padding:0;border:1px solid #EDEDED;border-radius:12px;overflow:hidden}
.blocked li{display:flex;flex-wrap:wrap;align-items:center;gap:12px;padding:12px 16px;font-size:13px}
.blocked li+li{border-top:1px solid #F2F2F2}
.md-body{font-size:15px;line-height:1.7;color:#262626}
.md-body>*{margin:0 0 10px}
.md-body>:last-child{margin-bottom:0}
.md-body h3,.md-body h4,.md-body h5,.md-body h6{font-size:15px;margin-top:16px}
.md-body ul,.md-body ol{padding-left:22px}
.md-body pre{padding:12px 14px;border-radius:10px;background:#F5F5F5;overflow-x:auto}
.md-body pre code{padding:0;background:none}
.md-body blockquote{margin-left:0;padding:10px 14px;border-radius:10px;background:#FAFAFA;border:1px solid #EDEDED}
.md-body table{min-width:0;width:auto;font-size:14px}
.md-body th,.md-body td{border:1px solid #EDEDED;padding:6px 10px}
.uses{display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:500;color:#4338CA;text-decoration:none}
.uses:hover{text-decoration:underline}
.uses svg{width:13px;height:13px}
tr.selected{background:#F5F5FF}
iframe.preview{width:100%;height:900px;border:1px solid #EDEDED;border-radius:14px;background:#FFFFFF}
.md-help{font-size:13px}
.md-help summary{cursor:pointer;color:#525252;font-weight:500}
.md-help table{min-width:0;width:auto;margin-top:8px}
.md-help td{padding:4px 16px 4px 0;border:0}
.md-help tbody tr:hover{background:transparent}
@media (max-width:600px){.content{padding:28px 16px 48px}.mem-detail{padding:20px}.mem-list{border-right:0;border-bottom:1px solid #EDEDED}}
CSS;
