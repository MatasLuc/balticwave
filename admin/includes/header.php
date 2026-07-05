<?php
/** Admin layout header. Expects: $adminTitle (string), $adminSection (string). */
require_once __DIR__ . '/boot.php';
require_admin();

$unread = (int)q_val('SELECT COUNT(*) FROM messages WHERE is_read = 0');
$nav = [
    'dashboard' => ['index.php',    '◈', 'Skydelis'],
    'pages'     => ['pages.php',    '▤', 'Puslapiai'],
    'menu'      => ['menu.php',     '☰', 'Meniu'],
    'gallery'   => ['gallery.php',  '▣', 'Galerija'],
    'videos'    => ['videos.php',   '▶', 'Video'],
    'messages'  => ['messages.php', '✉', 'Žinutės'],
    'users'     => ['users.php',    '♟', 'Vartotojai'],
    'settings'  => ['settings.php', '⚙', 'Nustatymai'],
];
$section = $adminSection ?? '';
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($adminTitle ?? 'Administravimas') ?> — Baltic Wave CMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/admin.css?v=<?= e(BW_VERSION) ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🌊</text></svg>">
<script>window.BW_CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script src="assets/admin.js?v=<?= e(BW_VERSION) ?>"></script>
</head>
<body class="bw-admin">
<div class="adm-shell">
  <aside class="adm-sidebar">
    <a class="adm-logo" href="index.php">
      <span class="adm-logo-wave">〜</span> Baltic Wave <small>CMS</small>
    </a>
    <nav class="adm-nav">
      <?php foreach ($nav as $key => [$href, $icon, $label]): ?>
        <a href="<?= e($href) ?>" class="<?= $key === $section ? 'active' : '' ?>">
          <span class="adm-nav-ico"><?= $icon ?></span><?= e($label) ?>
          <?php if ($key === 'messages' && $unread > 0): ?><span class="adm-badge"><?= $unread ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="adm-sidebar-foot">
      <a href="<?= e(base_url()) ?>/" target="_blank">↗ Peržiūrėti svetainę</a>
      <a href="logout.php">⏻ Atsijungti (<?= e(current_user()['username']) ?>)</a>
    </div>
  </aside>
  <main class="adm-main">
    <header class="adm-topbar">
      <h1><?= e($adminTitle ?? '') ?></h1>
      <?php if (!empty($adminActions)): ?><div class="adm-topbar-actions"><?= $adminActions ?></div><?php endif; ?>
    </header>
    <div class="adm-content">
      <?= flash_render() ?>
