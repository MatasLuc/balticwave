<?php
require_once __DIR__ . '/includes/boot.php';
require_admin();

$page = q_row('SELECT * FROM pages WHERE id = ?', [(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
if (!$page) {
    flash('Puslapis nerastas.', 'error');
    header('Location: pages.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 190);
    $meta  = mb_substr(trim((string)($_POST['meta_description'] ?? '')), 0, 300);
    $slug  = $page['slug'] === 'home' ? 'home' : (slugify((string)($_POST['slug'] ?? '')) ?: $page['slug']);
    $published = isset($_POST['published']) ? 1 : 0;

    if ($title === '') {
        flash('Pavadinimas negali būti tuščias.', 'error');
    } elseif ($slug !== $page['slug'] && in_array($slug, BW_RESERVED_SLUGS, true)) {
        flash("Adresas „{$slug}“ yra rezervuotas.", 'error');
    } elseif ($slug !== $page['slug'] && q_row('SELECT id FROM pages WHERE slug = ? AND id != ?', [$slug, $page['id']])) {
        flash("Adresas „{$slug}“ jau užimtas.", 'error');
    } else {
        q('UPDATE pages SET title = ?, slug = ?, meta_description = ?, published = ? WHERE id = ?',
          [$title, $slug, $meta, $published, $page['id']]);
        if ($slug !== $page['slug']) {
            delete_page_stub($page['slug']);
            ensure_page_stub($slug);
        }
        flash('Puslapio nustatymai išsaugoti.');
        header('Location: page-edit.php?id=' . (int)$page['id']);
        exit;
    }
    $page = array_merge($page, ['title' => $title, 'meta_description' => $meta, 'published' => $published]);
}

$adminTitle   = 'Puslapio nustatymai: ' . $page['title'];
$adminSection = 'pages';
$adminActions = '<a class="btn btn-primary" href="builder.php?id=' . (int)$page['id'] . '">✎ Redaguoti turinį</a>'
              . '<a class="btn btn-ghost" href="' . e(page_url($page['slug'])) . '" target="_blank">Peržiūrėti ↗</a>';
require __DIR__ . '/includes/header.php';
?>

<div class="adm-card" style="max-width:720px">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$page['id'] ?>">
    <label class="adm-field">Pavadinimas
      <input type="text" name="title" required maxlength="190" value="<?= e($page['title']) ?>">
    </label>
    <label class="adm-field">Adresas (.php failo vardas)
      <input type="text" name="slug" maxlength="80" pattern="[a-z0-9-]*" value="<?= e($page['slug']) ?>" <?= $page['slug'] === 'home' ? 'disabled' : '' ?>>
      <?php if ($page['slug'] === 'home'): ?>
        <span class="adm-help">Pradinis puslapis visada pasiekiamas per index.php</span>
      <?php else: ?>
        <span class="adm-help">Pakeitus adresą, senasis .php failas bus pašalintas ir sukurtas naujas.</span>
      <?php endif; ?>
    </label>
    <label class="adm-field">SEO aprašymas (meta description)
      <textarea name="meta_description" rows="3" maxlength="300"><?= e($page['meta_description']) ?></textarea>
    </label>
    <label class="adm-check"><input type="checkbox" name="published" <?= $page['published'] ? 'checked' : '' ?>> Paskelbtas (matomas lankytojams)</label>
    <button class="btn btn-primary" type="submit">Išsaugoti</button>
    <a class="btn btn-ghost" href="pages.php">← Grįžti į sąrašą</a>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
