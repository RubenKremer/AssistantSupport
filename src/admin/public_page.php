<?php
/**
 * Admin: Public page. The welcome page (/data/content/public/index.md) the
 * assistant reads first, with a preview that shows it exactly as the
 * assistant sees it, without saving.
 */

declare(strict_types=1);

const PUBLIC_PAGE_FILE = 'public/index.md';

function view_public(?string $draft = null): never
{
    $isPreview = $draft !== null;
    $markdown  = $draft ?? read_content(PUBLIC_PAGE_FILE);
    $baseUrl   = rtrim((string) settings()['base_url'], '/');

    $open = $baseUrl !== ''
        ? '<a class="btn ghost" href="' . e($baseUrl) . '/" target="_blank" rel="noopener">' . icon('external') . 'Open public page</a>'
        : '';

    $html = page_header('Public page', 'The page your assistant reads first. It explains what this is and asks for the passphrase.',
            $open
            . '<button type="submit" form="public-form" name="action" value="preview" class="btn ghost">Preview</button>'
            . '<button type="submit" form="public-form" name="action" value="save" class="btn">Save changes</button>')
        . ($isPreview ? '<p class="notice warn" role="status">This is a preview of your changes. They are not saved yet.</p>' : '')
        . '<form id="public-form" method="post" action="?p=public" class="cols">' . csrf_field()
        . '<section class="main-col"><div class="field"><div class="field-head"><label for="pm">Markdown</label>'
        . '<span class="hint"><code>{{base}}</code> becomes your public address</span></div>'
        . '<textarea id="pm" name="markdown" rows="30" class="mono">' . e($markdown) . '</textarea></div></section>'
        . '<aside class="side-col">'
        . '<div class="card" style="gap:10px"><span class="label">How the page is styled</span><ul class="points" style="font-size:13px;line-height:1.6">'
        . '<li>A short line right above the <code># Title</code> becomes a small label.</li>'
        . '<li>The first paragraph after the title is the intro.</li>'
        . '<li>A <code>&gt;</code> block that starts with <code>## Heading</code> becomes a highlighted card with a lock icon.</li>'
        . '<li>A numbered list whose items start with <code>### Heading</code> becomes the large steps.</li>'
        . '<li>Code blocks (<code>```</code>) become code boxes. Use them for URLs the assistant must open.</li>'
        . '</ul></div>'
        . '<div class="card soft"><span class="label">Also served as plain text</span><span class="muted">The same text is available at <code>/llms.txt</code> for assistants that prefer Markdown.</span></div>'
        . '<div class="card" style="gap:8px">' . markdown_help() . '</div>'
        . '</aside></form>';

    if ($isPreview) {
        $previewBase = $baseUrl !== '' ? $baseUrl : 'https://your-public-address';
        $filled      = fill_placeholders($markdown, $previewBase, null);
        $document    = assistant_page_html(page_title($filled), markdown_to_html($filled), $previewBase, true);
        $html .= '<section class="stack" style="gap:12px"><h2>Preview</h2>'
            . '<iframe class="preview" title="Preview of the public page" sandbox srcdoc="' . e($document) . '"></iframe></section>';
    }

    admin_page('public', 'Public page', $html);
}

function post_public(array $in): never
{
    $markdown = clean_text((string) ($in['markdown'] ?? ''), 50000);

    if (($in['action'] ?? '') === 'preview') {
        view_public($markdown);
    }
    if ($markdown === '') {
        flash('The public page cannot be empty: your assistant would find nothing there.', 'error');
        redirect_to(['p' => 'public']);
    }

    $file = CONTENT_DIR . '/' . PUBLIC_PAGE_FILE;
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0775, true);
    }
    file_put_contents($file, $markdown . "\n", LOCK_EX);
    flash('Public page saved.');
    redirect_to(['p' => 'public']);
}
