<?php
require_once __DIR__ . '/includes/boot.php';
require_admin();

$album = q_row('SELECT * FROM gallery_albums WHERE id = ?', [(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
if (!$album) {
    flash('Albumas nerastas.', 'error');
    header('Location: gallery.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = $_POST['do'] ?? '';
    if ($do === 'meta') {
        q('UPDATE gallery_albums SET title = ?, description = ? WHERE id = ?',
          [mb_substr(trim((string)($_POST['title'] ?? '')), 0, 190) ?: $album['title'],
           mb_substr(trim((string)($_POST['description'] ?? '')), 0, 500), $album['id']]);
        flash('Albumo informacija atnaujinta.');
    } elseif ($do === 'delete_image') {
        $img = q_row('SELECT * FROM gallery_images WHERE id = ? AND album_id = ?',
                     [(int)($_POST['image_id'] ?? 0), $album['id']]);
        if ($img) {
            $f = UPLOADS_DIR . '/' . basename($img['filename']);
            if (is_file($f)) {
                @unlink($f);
            }
            q('DELETE FROM gallery_images WHERE id = ?', [$img['id']]);
            flash('Nuotrauka ištrinta.');
        }
    }
    header('Location: album.php?id=' . (int)$album['id']);
    exit;
}

$images = q_all('SELECT * FROM gallery_images WHERE album_id = ? ORDER BY sort_order, id', [$album['id']]);

$adminTitle   = 'Albumas: ' . $album['title'];
$adminSection = 'gallery';
$adminActions = '<a class="btn btn-ghost" href="gallery.php">← Visi albumai</a>';
require __DIR__ . '/includes/header.php';
?>

<div class="adm-card">
  <h2>Albumo informacija</h2>
  <form method="post" class="adm-grid cols-2" style="align-items:end">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="meta">
    <input type="hidden" name="id" value="<?= (int)$album['id'] ?>">
    <label class="adm-field" style="margin:0">Pavadinimas
      <input type="text" name="title" required maxlength="190" value="<?= e($album['title']) ?>">
    </label>
    <div style="display:flex;gap:10px;align-items:end">
      <label class="adm-field" style="margin:0;flex:1">Aprašymas
        <input type="text" name="description" maxlength="500" value="<?= e($album['description']) ?>">
      </label>
      <button class="btn btn-primary" type="submit">Išsaugoti</button>
    </div>
  </form>
</div>

<div class="dropzone" id="dropzone">
  <strong>Vilkite nuotraukas čia</strong> arba spauskite, kad pasirinktumėte failus<br>
  <small>JPG, PNG, WEBP, GIF, AVIF · iki 12 MB</small>
  <input type="file" id="file-input" accept="image/*" multiple hidden>
</div>

<div class="img-grid" id="img-grid">
  <?php foreach ($images as $img): ?>
  <div class="img-card" data-id="<?= (int)$img['id'] ?>">
    <img src="../<?= e(UPLOADS_URL) ?>/<?= e(rawurlencode($img['filename'])) ?>" alt="">
    <div class="body">
      <input type="text" class="caption" placeholder="Aprašymas…" maxlength="300" value="<?= e($img['caption']) ?>">
      <form method="post" onsubmit="return confirm('Ištrinti nuotrauką?')">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="delete_image">
        <input type="hidden" name="id" value="<?= (int)$album['id'] ?>">
        <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
        <button class="btn btn-danger btn-sm" type="submit" style="width:100%">Šalinti</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php if (!$images): ?><p style="color:var(--muted)" id="empty-note">Nuotraukų dar nėra — įkelkite viršuje.</p><?php endif; ?>
<p style="color:var(--muted);font-size:.82rem;margin-top:16px">Tempkite nuotraukas, kad pakeistumėte jų eiliškumą — išsaugoma automatiškai.</p>

<script>
(function () {
  var ALBUM_ID = <?= (int)$album['id'] ?>;
  var dz = document.getElementById('dropzone');
  var input = document.getElementById('file-input');
  var grid = document.getElementById('img-grid');

  function uploadFiles(files) {
    var queue = Array.prototype.slice.call(files);
    if (!queue.length) return;
    bwToast('Įkeliama: ' + queue.length + ' fail.');
    (function next() {
      var f = queue.shift();
      if (!f) { location.reload(); return; }
      bwApi('upload', { file: f, album_id: ALBUM_ID })
        .then(next)
        .catch(function (e) { bwToast(e.message, true); next(); });
    })();
  }

  dz.addEventListener('click', function () { input.click(); });
  input.addEventListener('change', function () { uploadFiles(input.files); });
  ['dragover', 'dragenter'].forEach(function (ev) {
    dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('hover'); });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove('hover'); });
  });
  dz.addEventListener('drop', function (e) { uploadFiles(e.dataTransfer.files); });

  // Reorder by dragging cards.
  bwSortable(grid, '.img-card', function () {
    var ids = Array.prototype.map.call(grid.querySelectorAll('.img-card'), function (c) { return c.dataset.id; });
    bwApi('images_reorder', { ids: JSON.stringify(ids) })
      .then(function () { bwToast('Eiliškumas išsaugotas.'); })
      .catch(function (e) { bwToast(e.message, true); });
  });

  // Caption autosave on blur.
  grid.addEventListener('blur', function (e) {
    if (!e.target.classList.contains('caption')) return;
    var card = e.target.closest('.img-card');
    bwApi('image_caption', { id: card.dataset.id, caption: e.target.value })
      .then(function () { bwToast('Aprašymas išsaugotas.'); })
      .catch(function (err) { bwToast(err.message, true); });
  }, true);
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
