<?php
/**
 * AssistantSupport — assistant side (port 80). Every request is a plain GET,
 * because voice assistants cannot POST.
 *
 *   /                                         public welcome page (instructions for the AI)
 *   /llms.txt                                 the same page as plain Markdown
 *   /?page=NAME                               another public page (/data/content/public/NAME.md)
 *   /?pass=PASSPHRASE                         private context: backstory, skills, links, recent memories
 *   /?pass=PASSPHRASE&page=history[&week=2026-W46]  memory history per week
 *   /?pass=PASSPHRASE&page=memories[&q=word]  search memories (or the 50 newest)
 *   /?pass=PASSPHRASE&page=remember&title=&summary=&points=a|b   save a memory
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/functions.php';
require __DIR__ . '/../src/assistant.php';

send_security_headers();

// Client and country rules from the admin's Security page.
if (!check_access()) {
    render('Access denied', '<h1>Access denied</h1><p>This server does not accept requests from this client or location.</p>', 403);
}

$baseUrl = base_url();

// /llms.txt: the public welcome page as plain Markdown, for machines.
if (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === '/llms.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo fill_placeholders(read_content('public/index.md'), $baseUrl, null);
    exit;
}

$page = (string) ($_GET['page'] ?? 'index');
if (!is_valid_page_name($page)) {
    render('Not found', '<h1>Page not found</h1>', 404);
}

// ---------------------------------------------------------------------------
// Public area: no passphrase given.
// ---------------------------------------------------------------------------
if (!isset($_GET['pass'])) {
    $markdown = read_content("public/$page.md");
    if ($markdown === '') {
        render('Not found', '<h1>Page not found</h1>', 404);
    }
    $markdown = fill_placeholders($markdown, $baseUrl, null);
    render(page_title($markdown), markdown_to_html($markdown));
}

// ---------------------------------------------------------------------------
// Private area: check the passphrase first.
// ---------------------------------------------------------------------------
if (is_locked_out()) {
    render('Locked', '<h1>Temporarily locked</h1><p>Too many wrong passphrases. Tell your user to try again in ' . lockout_minutes() . ' minutes.</p>');
}

$pass = normalize_passphrase((string) $_GET['pass']);
if (!passphrase_matches($pass)) {
    record_failure();
    sleep(1); // slow down guessing
    render('Wrong passphrase', '<h1>Wrong passphrase</h1>'
        . '<p>The passphrase was not accepted. Ask your user to say the passphrase sentence again, then retry.</p>'
        . '<p>Format: all words in lowercase, joined by dashes. Example:</p>'
        . '<pre><code>' . e($baseUrl) . '/?pass=the-purple-elephant-sings-loudly</code></pre>');
}

$privateBase = $baseUrl . '/?pass=' . urlencode($pass);

switch ($page) {
    case 'index':
        render('Your context', render_context_page($privateBase));
    case 'history':
        $week = (string) ($_GET['week'] ?? '');
        if ($week !== '' && !preg_match('/^\d{4}-W(0[1-9]|[1-4]\d|5[0-3])$/', $week)) {
            $week = '';
        }
        render('Memory history', render_history_page($privateBase, $week));
    case 'memories':
        render('Memories', render_memories_page($privateBase, clean_line((string) ($_GET['q'] ?? ''), 100)));
    case 'remember':
        render('Remember', handle_remember($privateBase));
    default:
        render('Not found', '<h1>Page not found</h1><p><a href="' . e($privateBase) . '">Back to your context</a></p>', 404);
}
