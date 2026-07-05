<?php
/**
 * Dynamic page renderer. Reached either directly (?slug=…) or through an
 * auto-generated stub file that sets $__slug before requiring this script.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/render.php';

bw_session_start();

$slug = $__slug ?? (string)($_GET['slug'] ?? '');
$page = null;
if (preg_match('/^[a-z0-9-]{1,80}$/', $slug)) {
    $page = q_row('SELECT * FROM pages WHERE slug = ?', [$slug]);
}

if (!$page || (!$page['published'] && !is_logged_in())) {
    http_response_code(404);
    $pageTitle = 'Page not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="bw-canvas" style="--h:420px">'
       . '<div class="bw-block bw-heading" style="--x:10%;--y:80px;--w:80%"><h1 class="bw-h bw-h1" style="text-align:center">404</h1></div>'
       . '<div class="bw-block bw-text" style="--x:10%;--y:210px;--w:80%"><div class="bw-richtext" style="text-align:center"><p>The page you are looking for does not exist.</p></div></div>'
       . '<div class="bw-block bw-button" style="--x:10%;--y:290px;--w:80%"><div style="text-align:center"><a class="bw-btn" href="' . e(page_url('home')) . '">Back home<span class="bw-btn-arrow">&rarr;</span></a></div></div>'
       . '</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Contact form submission (block type "contactForm" posts back to the page).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bw_contact'])) {
    csrf_check();
    $name  = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $msg   = trim((string)($_POST['message'] ?? ''));
    if ($name !== '' && $msg !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        q('INSERT INTO messages (name, email, message, created_at) VALUES (?, ?, ?, NOW())',
          [mb_substr($name, 0, 120), mb_substr($email, 0, 190), mb_substr($msg, 0, 5000)]);
        $_SESSION['bw_contact_sent'] = true;
    }
    header('Location: ' . page_url($slug));
    exit;
}

$pageTitle       = $slug === 'home' ? '' : $page['title'];
$pageDescription = $page['meta_description'];
$activeSlug      = $slug;

require __DIR__ . '/includes/header.php';

if (!$page['published']) {
    echo '<div class="flash flash-warning">Šis puslapis nepaskelbtas — jį matote tik jūs (administratorius).</div>';
}
if (is_logged_in()) {
    echo '<a class="bw-edit-fab" href="' . e(base_url()) . '/admin/builder.php?id=' . (int)$page['id'] . '" title="Redaguoti šį puslapį">✎ Redaguoti</a>';
}

echo render_page_canvas($page['content']);

require __DIR__ . '/includes/footer.php';
