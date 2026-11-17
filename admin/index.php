<?php
/**
 * AssistantSupport — admin panel (port 8080). Keep this port on your own
 * network. Log in with ADMIN_USER (default "admin") and ADMIN_PASSWORD.
 *
 *   ?p=backstory | skills | links | public | memories | security | settings
 *   ?p=logout
 * Every change is a POST with a CSRF token, followed by a redirect.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/functions.php';
require __DIR__ . '/../src/admin/ui.php';
require __DIR__ . '/../src/admin/context_pages.php';
require __DIR__ . '/../src/admin/memories_page.php';
require __DIR__ . '/../src/admin/security_page.php';
require __DIR__ . '/../src/admin/settings_page.php';
require __DIR__ . '/../src/admin/public_page.php';

const ADMIN_PAGES = ['backstory', 'skills', 'links', 'public', 'memories', 'security', 'settings'];

send_security_headers();
start_admin_session();

$page = (string) ($_GET['p'] ?? 'backstory');

if ($page === 'logout') {
    logout();
    redirect_to([]);
}
if (!is_logged_in()) {
    handle_login();
}
if (!in_array($page, ADMIN_PAGES, true)) {
    redirect_to(['p' => 'backstory']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    ('post_' . $page)($_POST);
}
('view_' . $page)();
