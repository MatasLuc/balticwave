<?php
require_once __DIR__ . '/includes/boot.php';
require_admin();

// ---- Create page -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'create') {
    csrf_check();
    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 190);
    $slug  = slugify((string)($_POST['slug'] ?? '')) ?: slugify($title);

    if ($title === '' || $slug === '') {
        flash('Įveskite puslapio pavadinimą.', 'error');
    } elseif (in_array($slug, BW_RESERVED_SLUGS, true)) {
        flash("Adresas „{$slug}“ yra rezervuotas sistemai — pasirinkite kitą.", 'error');
    } elseif (q_row('SELECT id FROM pages WHERE slug = ?', [$slug])) {
        flash("Puslapis adresu „{$slug}“ jau egzistuoja.", 'error');
    } else {
        $layout = json_encode([
            'height' => 500,
            'blocks' => [
                ['id' => 'b1', 'type' => 'heading', 'x' => 10, 'y' => 70, 'w' => 80, 'z' => 1,
                 'props' => ['text' => $title, 'level' => 1, 'align' => 'center']],
                ['id' => 'b2', 'type' => 'text', 'x' => 20, 'y' => 230, 'w' => 60, 'z' => 1,
                 'props' => ['html' => '<p>Naujas puslapis. Pridėkite blokų iš kairės pusės.</p>', 'align' => 'center']],
            ],
        ], JSON_UNESCAPED_UNICODE);
        q('INSERT INTO pages (slug, title, content, published) VALUES (?, ?, ?, ?)',
          [$slug, $title, $layout, isset($_POST['published']) ? 1 : 0]);
        $id = (int)db()->lastInsertId();
        if (!ensure_page_stub($slug)) {
            flash("Puslapis sukurtas, bet nepavyko sukurti failo {$slug}.php — patikrinkite katalogo rašymo teises.", 'warning');
        } else {
            flash("Puslapis „{$title}“ sukurtas ({$slug}.php).");
        }
        header('Location: builder.php?id=' . $id);
        exit;
    }
    header('Location: pages.php');
    exit;
}

// ---- Delete page -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'delete') {
    csrf_check();
    $page = q_row('SELECT * FROM pages WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
    if ($page && $page['slug'] !== 'home') {
        q('DELETE FROM pages WHERE id = ?', [$page['id']]);
        delete_page_stub($page['slug']);
        flash('Puslapis „' . $page['title'] . '“ ištrintas.');
    } else {
        flash('Pradinio puslapio ištrinti negalima.', 'error');
    }
    header('Location: pages.php');
    exit;
}

$pages = q_all('SELECT * FROM pages ORDER BY (slug = "home") DESC, title');

$adminTitle   = 'Puslapiai';
$adminSection = 'pages';
require __DIR__ . '/includes/header.php';
?>

<div class="adm-card">
  <h2>Visi puslapiai (<?= count($pages) ?>)</h2>
  <table class="adm-table">
    <tr><th>Pavadinimas</th><th>Adresas</th><th>Būsena</th><th>Atnaujinta</th><th class="actions">Veiksmai</th></tr>
    <?php foreach ($pages as $p): ?>
    <tr>
      <td><strong><?= e($p['title']) ?></strong></td>
      <td>
        <a href="<?= e(page_url($p['slug'])) ?>" target="_blank" style="text-decoration:none">
          <?= $p['slug'] === 'home' ? 'index.php' : e($p['slug']) . '.php' ?> ↗
        </a>
      </td>
      <td><?= $p['published'] ? '<span class="pill pill-on">paskelbtas</span>' : '<span class="pill pill-off">juodraštis</span>' ?></td>
      <td><small style="color:var(--muted)"><?= e($p['updated_at']) ?></small></td>
      <td class="actions">
        <a class="btn btn-primary btn-sm" href="builder.php?id=<?= (int)$p['id'] ?>">✎ Turinys</a>
        <a class="btn btn-ghost btn-sm" href="page-edit.php?id=<?= (int)$p['id'] ?>">Nustatymai</a>
        <?php if ($p['slug'] !== 'home'): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('Tikrai ištrinti puslapį „<?= e($p['title']) ?>“?')">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="delete">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit">Šalinti</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="adm-card" id="new">
  <h2>Naujas puslapis</h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="create">
    <div class="adm-grid cols-2">
      <label class="adm-field">Pavadinimas
        <input type="text" name="title" id="new-title" required maxlength="190" placeholder="pvz. Partneriai">
      </label>
      <label class="adm-field">Adresas (.php failo vardas)
        <input type="text" name="slug" id="new-slug" maxlength="80" placeholder="sugeneruojamas automatiškai" pattern="[a-z0-9-]*">
        <span class="adm-help">Bus sukurtas failas, pvz. <code>partneriai.php</code></span>
      </label>
    </div>
    <label class="adm-check"><input type="checkbox" name="published" checked> Paskelbti iš karto</label>
    <button class="btn btn-primary" type="submit">Sukurti ir atidaryti redaktorių →</button>
  </form>
</div>

<script>
document.getElementById('new-title').addEventListener('input', function () {
  var s = document.getElementById('new-slug');
  if (!s.dataset.touched) {
    s.value = this.value.toLowerCase()
      .replace(/[ąčęėįšųūž]/g, function (c) { return 'aceeisuuz'['ąčęėįšųūž'.indexOf(c)]; })
      .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  }
});
document.getElementById('new-slug').addEventListener('input', function () { this.dataset.touched = 1; });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
