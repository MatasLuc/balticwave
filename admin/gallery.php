<?php
require_once __DIR__ . '/includes/boot.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = $_POST['do'] ?? '';
    if ($do === 'create') {
        $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 190);
        if ($title === '') {
            flash('Įveskite albumo pavadinimą.', 'error');
        } else {
            $order = (int)q_val('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM gallery_albums');
            q('INSERT INTO gallery_albums (title, description, sort_order) VALUES (?, ?, ?)',
              [$title, mb_substr(trim((string)($_POST['description'] ?? '')), 0, 500), $order]);
            flash("Albumas „{$title}“ sukurtas.");
            header('Location: album.php?id=' . (int)db()->lastInsertId());
            exit;
        }
    } elseif ($do === 'delete') {
        $album = q_row('SELECT * FROM gallery_albums WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        if ($album) {
            foreach (q_all('SELECT filename FROM gallery_images WHERE album_id = ?', [$album['id']]) as $img) {
                $f = UPLOADS_DIR . '/' . basename($img['filename']);
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            q('DELETE FROM gallery_albums WHERE id = ?', [$album['id']]);
            flash('Albumas ir jo nuotraukos ištrinti.');
        }
    }
    header('Location: gallery.php');
    exit;
}

$albums = q_all('SELECT a.*,
                 (SELECT COUNT(*) FROM gallery_images gi WHERE gi.album_id = a.id) AS img_count,
                 (SELECT filename FROM gallery_images gi WHERE gi.album_id = a.id ORDER BY sort_order, id LIMIT 1) AS cover
                 FROM gallery_albums a ORDER BY a.sort_order, a.id');

$adminTitle   = 'Galerija';
$adminSection = 'gallery';
require __DIR__ . '/includes/header.php';
?>

<div class="adm-card">
  <h2>Naujas albumas</h2>
  <form method="post" class="adm-grid cols-2" style="align-items:end">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="create">
    <label class="adm-field" style="margin:0">Pavadinimas
      <input type="text" name="title" required maxlength="190" placeholder="pvz. Baltic Wave IV, Toronto">
    </label>
    <div style="display:flex;gap:10px;align-items:end">
      <label class="adm-field" style="margin:0;flex:1">Aprašymas (nebūtina)
        <input type="text" name="description" maxlength="500">
      </label>
      <button class="btn btn-primary" type="submit">Sukurti</button>
    </div>
  </form>
</div>

<div class="album-grid">
  <?php foreach ($albums as $a): ?>
  <div class="album-card">
    <a href="album.php?id=<?= (int)$a['id'] ?>" class="cover" style="text-decoration:none;<?= $a['cover'] ? 'background-image:url(' . e('../' . UPLOADS_URL . '/' . rawurlencode($a['cover'])) . ')' : '' ?>">
      <?= $a['cover'] ? '' : '▣' ?>
    </a>
    <div class="body">
      <h3><?= e($a['title']) ?></h3>
      <p><?= (int)$a['img_count'] ?> nuotr. <?= $a['description'] !== '' ? '· ' . e(mb_substr($a['description'], 0, 40)) : '' ?></p>
      <div style="display:flex;gap:8px">
        <a class="btn btn-primary btn-sm" href="album.php?id=<?= (int)$a['id'] ?>">Tvarkyti</a>
        <form method="post" onsubmit="return confirm('Ištrinti albumą su visomis nuotraukomis?')">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="delete">
          <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit">Šalinti</button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php if (!$albums): ?><p style="color:var(--muted)">Albumų dar nėra — sukurkite pirmąjį viršuje.</p><?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
