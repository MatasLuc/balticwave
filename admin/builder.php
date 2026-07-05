<?php
/**
 * Visual drag & drop page builder — full-screen editor.
 * Blocks are freely positioned on the canvas (x/w in %, y in px) and saved
 * as JSON via api.php?action=save_layout.
 */
require_once __DIR__ . '/includes/boot.php';
require_admin();

$page = q_row('SELECT * FROM pages WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$page) {
    flash('Puslapis nerastas.', 'error');
    header('Location: pages.php');
    exit;
}

$layout = json_decode($page['content'] ?: '{}', true) ?: [];
$layout += ['height' => 600, 'blocks' => []];

$albums = q_all('SELECT id, title FROM gallery_albums ORDER BY sort_order, id');
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Redaktorius: <?= e($page['title']) ?> — Baltic Wave CMS</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= e(BW_VERSION) ?>">
<link rel="stylesheet" href="assets/admin.css?v=<?= e(BW_VERSION) ?>">
<script>
window.BW_CSRF   = <?= json_encode(csrf_token()) ?>;
window.BW_PAGE   = { id: <?= (int)$page['id'] ?>, title: <?= json_encode($page['title'], JSON_UNESCAPED_UNICODE) ?> };
window.BW_LAYOUT = <?= json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.BW_ALBUMS = <?= json_encode(array_map(fn($a) => ['id' => (int)$a['id'], 'title' => $a['title']], $albums), JSON_UNESCAPED_UNICODE) ?>;
window.BW_UPLOADS_URL = <?= json_encode('../' . UPLOADS_URL) ?>;
</script>
</head>
<body class="bw-admin bld-body">
<div class="bld-app">

  <div class="bld-top">
    <a class="btn btn-ghost btn-sm" href="pages.php">← Puslapiai</a>
    <h1><?= e($page['title']) ?> <small>· vizualus redaktorius</small></h1>
    <span class="bld-status" id="bld-status">Išsaugota</span>
    <label class="adm-check" style="margin:0;font-size:.82rem" title="Pritraukti prie tinklelio">
      <input type="checkbox" id="bld-snap" checked> tinklelis
    </label>
    <label style="font-size:.8rem;color:var(--muted)">Aukštis
      <input type="number" id="bld-height" class="adm-input" style="width:90px;display:inline-block;margin:0 0 0 6px;padding:6px 8px" min="200" max="30000" step="10" value="<?= (int)$layout['height'] ?>">
    </label>
    <a class="btn btn-ghost btn-sm" href="<?= e(page_url($page['slug'])) ?>" target="_blank">Peržiūrėti ↗</a>
    <button class="btn btn-primary btn-sm" id="bld-save">💾 Išsaugoti</button>
  </div>

  <div class="bld-wrap">
    <aside class="bld-palette">
      <h3>Blokai</h3>
      <div id="bld-palette"></div>
      <h3 style="margin-top:18px">Patarimai</h3>
      <p style="font-size:.76rem;color:var(--muted);line-height:1.6;margin:0 6px">
        · Tempkite bloką bet kur ant drobės<br>
        · Rodyklės — pastumti (Shift = 10×)<br>
        · Teal rankenėlė — plotis<br>
        · Delete — pašalinti bloką<br>
        · Mobiliuose blokai išsirikiuoja pagal Y
      </p>
    </aside>

    <div class="bld-canvas-outer" id="bld-canvas-outer">
      <div class="bw-canvas bld-canvas grid-on" id="bld-canvas"></div>
    </div>

    <aside class="bld-props" id="bld-props">
      <h3>Savybės</h3>
      <div class="empty">Pasirinkite bloką drobėje arba pridėkite naują iš kairės.</div>
    </aside>
  </div>
</div>

<div class="bld-modal" id="bld-picker">
  <div class="bld-modal-card">
    <h3>Pasirinkite paveikslėlį</h3>
    <div style="display:flex;gap:10px;align-items:center">
      <button class="btn btn-primary btn-sm" id="bld-upload-btn">⤒ Įkelti naują</button>
      <input type="file" id="bld-upload-input" accept="image/*" hidden>
      <button class="btn btn-ghost btn-sm" id="bld-picker-close">Uždaryti</button>
    </div>
    <div class="bld-pick-grid" id="bld-pick-grid"></div>
  </div>
</div>

<script src="assets/admin.js?v=<?= e(BW_VERSION) ?>"></script>
<script src="assets/builder.js?v=<?= e(BW_VERSION) ?>"></script>
</body>
</html>
