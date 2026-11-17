<?php
/**
 * Assistant-side page layout: header, footer and the one stylesheet.
 *
 * The stylesheet styles plain Markdown output, so pages stay simple
 * Markdown files:
 *   - a short line right before the "# Title"   -> small uppercase label
 *   - the first paragraph after the title       -> intro text
 *   - a > blockquote                            -> highlighted card with a lock icon
 *   - a 1. numbered list                        -> numbered steps
 *   - a fenced code block                       -> code box
 */

declare(strict_types=1);

// Fonts are served from /fonts/ by this container itself (no Google Fonts),
// variable fonts so one file covers every weight. License files sit beside them.
const FONT_CSS = <<<'CSS'
@font-face{font-family:Inter;font-style:normal;font-weight:100 900;font-display:swap;src:url(/fonts/inter.woff2) format("woff2")}
@font-face{font-family:"JetBrains Mono";font-style:normal;font-weight:100 800;font-display:swap;src:url(/fonts/jetbrains-mono.woff2) format("woff2")}
CSS;

/** Favicons, served from the root of both the assistant and the admin site. */
const FAVICON_LINKS = '<link rel="icon" href="/favicon.svg" type="image/svg+xml">'
    . '<link rel="icon" href="/favicon.ico" sizes="16x16 32x32">'
    . '<link rel="apple-touch-icon" href="/apple-touch-icon.png">';

/** The logo mark: brackets around a dot. */
function logo_mark(int $size = 17): string
{
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 5H6v14h2"/><path d="M16 5h2v14h-2"/><circle cx="12" cy="12" r="2" fill="#FFFFFF" stroke="none"/></svg>';
}

const PAGE_CSS = <<<'CSS'
*{box-sizing:border-box}
body{margin:0;background:#FFFFFF;color:#0A0A0A;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;line-height:1.6}
a{color:#0A0A0A}a:hover{color:#4338CA}
.bar{border-bottom:1px solid #EDEDED}
.wrap{max-width:720px;margin:0 auto;padding-left:24px;padding-right:24px}
.bar .wrap{padding-top:20px;padding-bottom:20px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px}
.brand{display:flex;align-items:center;gap:10px;font-size:16px;font-weight:600;letter-spacing:-.03em}
.brand .light{color:#8A8A8A}
.mark{width:28px;height:28px;border-radius:8px;background:#0A0A0A;display:flex;align-items:center;justify-content:center}
main.wrap{padding-top:64px;padding-bottom:48px}
main>:first-child{margin-top:0}
main>p:first-child:has(+h1){margin:0 0 14px;font-size:12px;font-weight:500;letter-spacing:.08em;text-transform:uppercase;color:#6B6B6B}
h1{margin:0 0 14px;font-size:40px;font-weight:600;letter-spacing:-.035em;line-height:1.1}
h1+p{font-size:16px;line-height:1.65}
h2{margin:48px 0 10px;font-size:16px;font-weight:600}
h3{margin:0 0 10px;font-size:16px;font-weight:600}
h2+h3,p+h3,ul+h3,ol+h3,pre+h3,table+h3,blockquote+h3{margin-top:28px}
h3 small{font-size:13px;font-weight:400;color:#6B6B6B}
h4,h5,h6{margin:16px 0 8px;font-size:14px;font-weight:600}
li>p{margin:6px 0}
table{border-collapse:collapse;margin:0 0 12px;font-size:14px;color:#525252}
th,td{border:1px solid #EDEDED;padding:6px 10px;text-align:left;vertical-align:top}
th{color:#0A0A0A;font-weight:600}
p{margin:0 0 12px;font-size:14px;color:#525252}
h1+p{font-size:16px}
ul{margin:0 0 12px;padding-left:20px;font-size:14px;color:#525252}
ul li{margin-bottom:8px}
strong{color:#0A0A0A;font-weight:600}
code{font-family:"JetBrains Mono",ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;color:#0A0A0A;background:#F5F5F5;padding:2px 6px;border-radius:6px;overflow-wrap:anywhere}
pre{margin:0 0 12px;padding:12px 14px;border-radius:10px;background:#F5F5F5;overflow-x:auto;line-height:1.6}
pre code{padding:0;background:none;white-space:pre;overflow-wrap:normal}
blockquote{margin:12px 0;padding:20px;border-radius:14px;background:#FAFAFA;border:1px solid #EDEDED}
blockquote:has(>h2){margin-top:48px}
blockquote h2{margin:0 0 12px;display:flex;align-items:center;gap:10px}
blockquote h2::before{content:"";flex:none;width:18px;height:18px;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230A0A0A' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='5' y='11' width='14' height='9' rx='2'/%3E%3Cpath d='M8 11V8a4 4 0 0 1 8 0v3'/%3E%3C/svg%3E") no-repeat center/contain}
blockquote pre{background:#FFFFFF;border:1px solid #EDEDED}
blockquote>:last-child,ol>li>:last-child{margin-bottom:0}
ol{margin:0 0 12px;padding-left:22px;font-size:14px;color:#525252}
ol li{margin-bottom:6px}
ol:has(>li>h3){list-style:none;margin:48px 0 0;padding:0;border-top:1px solid #EDEDED;counter-reset:step;font-size:14px;color:#525252}
ol:has(>li>h3)>li{margin:0;counter-increment:step;position:relative;padding:24px 0 24px 48px;border-bottom:1px solid #EDEDED}
ol:has(>li>h3)>li::before{content:counter(step);position:absolute;left:0;top:22px;width:28px;height:28px;border-radius:14px;border:1px solid #E4E4E4;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:500;color:#0A0A0A}
ol:has(>li>h3)>li>h3{margin-top:2px}
footer .wrap{padding-bottom:64px}
footer .line{padding-top:24px;border-top:1px solid #EDEDED;display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;font-size:13px;color:#6B6B6B}
footer code{font-size:12px}
@media (max-width:480px){h1{font-size:32px}main.wrap{padding-top:40px}}
CSS;

/**
 * Sends an assistant-side page. $public = true for the public welcome page:
 * only that page has a plain-text version at /llms.txt, so only it links there.
 */
function render(string $title, string $bodyHtml, int $status = 200, bool $public = false): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo assistant_page_html($title, $bodyHtml, base_url(), $public);
    exit;
}

/** The full assistant-side page; also used for the preview in the admin panel. */
function assistant_page_html(string $title, string $bodyHtml, string $baseUrl, bool $public = false): string
{
    $llms   = $public
        ? '<span>Machine-readable version: <a href="' . e($baseUrl . '/llms.txt') . '"><code>/llms.txt</code></a></span>'
        : '<span></span>';
    $footer = '<footer><div class="wrap"><div class="line">'
        . $llms
        . '<span>Plain GET requests only</span>'
        . '</div></div></footer>';

    return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>' . e($title) . ' · AssistantSupport</title>
' . FAVICON_LINKS . '
<style>' . FONT_CSS . PAGE_CSS . '</style>
</head>
<body>
<header class="bar"><div class="wrap">
<div class="brand"><span class="mark">' . logo_mark() . '</span><span>Assistant<span class="light">Support</span></span></div>
</div></header>
<main class="wrap">
' . $bodyHtml . '
</main>
' . $footer . '
</body>
</html>';
}
