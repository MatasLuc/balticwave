<?php
/**
 * Live preview renderer for the builder's device toggle. Renders the exact
 * same markup/CSS as the public site (real render_page_canvas() + real
 * stylesheet + real media queries), so an iframe sized to a phone width
 * shows a pixel-accurate mobile preview of unsaved edits.
 */
require_once __DIR__ . '/includes/boot.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
$sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$sent)) {
    http_response_code(419);
    exit('Sesija pasibaigė.');
}

$layoutJson = (string)($_POST['layout'] ?? '{}');
?><!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= e(BW_VERSION) ?>">
<style>body{margin:0}</style>
</head>
<body class="bw-site">
<main class="bw-main">
<?= render_page_canvas($layoutJson) ?>
</main>
</body>
</html>
