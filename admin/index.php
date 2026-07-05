<?php
require_once __DIR__ . '/includes/boot.php';
require_admin();

$stats = [
    'pages'    => (int)q_val('SELECT COUNT(*) FROM pages'),
    'images'   => (int)q_val('SELECT COUNT(*) FROM gallery_images'),
    'videos'   => (int)q_val('SELECT COUNT(*) FROM videos'),
    'unread'   => (int)q_val('SELECT COUNT(*) FROM messages WHERE is_read = 0'),
];
$recentPages    = q_all('SELECT id, title, slug, updated_at FROM pages ORDER BY updated_at DESC LIMIT 5');
$recentMessages = q_all('SELECT * FROM messages ORDER BY created_at DESC LIMIT 5');

$adminTitle   = 'Skydelis';
$adminSection = 'dashboard';
$adminActions = '<a class="btn btn-primary" href="pages.php#new">+ Naujas puslapis</a>';
require __DIR__ . '/includes/header.php';
?>

<div class="adm-grid cols-4">
  <div class="adm-stat"><b><?= $stats['pages'] ?></b><span>Puslapiai</span></div>
  <div class="adm-stat"><b><?= $stats['images'] ?></b><span>Nuotraukos galerijoje</span></div>
  <div class="adm-stat"><b><?= $stats['videos'] ?></b><span>Vaizdo įrašai</span></div>
  <div class="adm-stat"><b><?= $stats['unread'] ?></b><span>Neperskaitytos žinutės</span></div>
</div>

<div class="adm-grid cols-2" style="margin-top:22px">
  <div class="adm-card">
    <h2>Paskutiniai redaguoti puslapiai</h2>
    <table class="adm-table">
      <tr><th>Puslapis</th><th>Atnaujinta</th><th></th></tr>
      <?php foreach ($recentPages as $p): ?>
      <tr>
        <td><strong><?= e($p['title']) ?></strong><br><small style="color:var(--muted)">/<?= e($p['slug']) ?></small></td>
        <td><small><?= e($p['updated_at']) ?></small></td>
        <td class="actions"><a class="btn btn-ghost btn-sm" href="builder.php?id=<?= (int)$p['id'] ?>">Redaguoti</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="adm-card">
    <h2>Naujausios žinutės</h2>
    <?php if (!$recentMessages): ?>
      <p style="color:var(--muted)">Žinučių dar nėra.</p>
    <?php else: ?>
      <table class="adm-table">
        <tr><th>Nuo</th><th>Žinutė</th><th></th></tr>
        <?php foreach ($recentMessages as $m): ?>
        <tr>
          <td><strong><?= e($m['name']) ?></strong><?= $m['is_read'] ? '' : ' <span class="pill pill-on">nauja</span>' ?></td>
          <td><small style="color:var(--muted)"><?= e(mb_substr($m['message'], 0, 60)) ?>…</small></td>
          <td class="actions"><a class="btn btn-ghost btn-sm" href="messages.php">Žiūrėti</a></td>
        </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
</div>

<div class="adm-card">
  <h2>Sistema</h2>
  <table class="adm-table">
    <tr><td>CMS versija</td><td><?= e(BW_VERSION) ?></td></tr>
    <tr><td>PHP</td><td><?= e(PHP_VERSION) ?></td></tr>
    <tr><td>Šakninis katalogas įrašomas (naujų puslapių .php failams)</td>
        <td><?= is_writable(dirname(__DIR__)) ? '<span class="pill pill-on">taip</span>' : '<span class="pill pill-off">ne — naujų puslapių failai nebus kuriami</span>' ?></td></tr>
    <tr><td>/uploads įrašomas</td>
        <td><?= (is_dir(UPLOADS_DIR) && is_writable(UPLOADS_DIR)) ? '<span class="pill pill-on">taip</span>' : '<span class="pill pill-off">ne — įkėlimai neveiks</span>' ?></td></tr>
  </table>
  <p style="color:var(--muted);font-size:.84rem;margin:14px 0 0">
    Po kodo atnaujinimo paleiskite <a href="../setupdb.php" target="_blank">setupdb.php</a> — jis pritaikys naujas duomenų bazės migracijas.
  </p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
