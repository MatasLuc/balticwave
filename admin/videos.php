<?php
require_once __DIR__ . '/includes/boot.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = $_POST['do'] ?? '';
    if ($do === 'create' || $do === 'update') {
        $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 190);
        $url   = mb_substr(trim((string)($_POST['youtube_url'] ?? '')), 0, 300);
        $desc  = mb_substr(trim((string)($_POST['description'] ?? '')), 0, 500);
        if ($title === '' || youtube_id($url) === '') {
            flash('Reikalingas pavadinimas ir teisinga YouTube nuoroda.', 'error');
        } elseif ($do === 'create') {
            $order = (int)q_val('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM videos');
            q('INSERT INTO videos (title, youtube_url, description, sort_order) VALUES (?, ?, ?, ?)',
              [$title, $url, $desc, $order]);
            flash("Vaizdo įrašas „{$title}“ pridėtas.");
        } else {
            q('UPDATE videos SET title = ?, youtube_url = ?, description = ? WHERE id = ?',
              [$title, $url, $desc, (int)($_POST['id'] ?? 0)]);
            flash('Vaizdo įrašas atnaujintas.');
        }
    } elseif ($do === 'delete') {
        q('DELETE FROM videos WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        flash('Vaizdo įrašas ištrintas.');
    }
    header('Location: videos.php');
    exit;
}

$videos  = q_all('SELECT * FROM videos ORDER BY sort_order, id');
$editing = null;
if (isset($_GET['edit'])) {
    $editing = q_row('SELECT * FROM videos WHERE id = ?', [(int)$_GET['edit']]);
}

$adminTitle   = 'Video';
$adminSection = 'videos';
require __DIR__ . '/includes/header.php';
?>

<div class="adm-card">
  <h2><?= $editing ? 'Redaguoti vaizdo įrašą' : 'Naujas vaizdo įrašas' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
    <div class="adm-grid cols-2">
      <label class="adm-field">Pavadinimas
        <input type="text" name="title" required maxlength="190" value="<?= e($editing['title'] ?? '') ?>">
      </label>
      <label class="adm-field">YouTube nuoroda
        <input type="text" name="youtube_url" required maxlength="300" placeholder="https://youtu.be/…" value="<?= e($editing['youtube_url'] ?? '') ?>">
      </label>
    </div>
    <label class="adm-field">Aprašymas (nebūtina)
      <input type="text" name="description" maxlength="500" value="<?= e($editing['description'] ?? '') ?>">
    </label>
    <button class="btn btn-primary" type="submit"><?= $editing ? 'Išsaugoti' : '+ Pridėti' ?></button>
    <?php if ($editing): ?><a class="btn btn-ghost" href="videos.php">Atšaukti</a><?php endif; ?>
  </form>
</div>

<div class="adm-card">
  <h2>Vaizdo įrašai (<?= count($videos) ?>)</h2>
  <p style="color:var(--muted);font-size:.84rem;margin:0 0 12px">Tempkite eilutes, kad pakeistumėte eiliškumą — išsaugoma automatiškai.</p>
  <div id="video-list">
    <?php foreach ($videos as $v): $yid = youtube_id($v['youtube_url']); ?>
    <div class="menu-row" data-id="<?= (int)$v['id'] ?>">
      <span class="drag-handle" title="Tempti">⠿</span>
      <img src="https://i.ytimg.com/vi/<?= e($yid) ?>/default.jpg" alt="" style="width:64px;border-radius:6px;pointer-events:none">
      <div style="flex:1;min-width:0">
        <strong><?= e($v['title']) ?></strong><br>
        <small style="color:var(--muted)"><?= e($v['youtube_url']) ?></small>
      </div>
      <a class="btn btn-ghost btn-sm" href="videos.php?edit=<?= (int)$v['id'] ?>">Redaguoti</a>
      <form method="post" onsubmit="return confirm('Ištrinti vaizdo įrašą?')">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="delete">
        <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
        <button class="btn btn-danger btn-sm" type="submit">✕</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
(function () {
  var list = document.getElementById('video-list');
  bwSortable(list, '.menu-row', function () {
    var ids = Array.prototype.map.call(list.querySelectorAll('.menu-row'), function (r) { return r.dataset.id; });
    bwApi('videos_reorder', { ids: JSON.stringify(ids) })
      .then(function () { bwToast('Eiliškumas išsaugotas.'); })
      .catch(function (e) { bwToast(e.message, true); });
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
