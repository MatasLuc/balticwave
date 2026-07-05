<?php
require_once __DIR__ . '/includes/boot.php';
require_admin();

$fields = [
    'site_name'        => ['Svetainės pavadinimas', 'text'],
    'tagline'          => ['Šūkis (tagline)', 'text'],
    'meta_description' => ['Numatytasis SEO aprašymas', 'textarea'],
    'footer_text'      => ['Poraštės tekstas', 'textarea'],
    'youtube_url'      => ['YouTube kanalo nuoroda', 'text'],
    'facebook_url'     => ['Facebook nuoroda', 'text'],
    'contact_email'    => ['Kontaktinis el. paštas (rodomas poraštėje)', 'text'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($fields as $key => $def) {
        set_setting($key, mb_substr(trim((string)($_POST[$key] ?? '')), 0, 1000));
    }
    flash('Nustatymai išsaugoti.');
    header('Location: settings.php');
    exit;
}

$adminTitle   = 'Nustatymai';
$adminSection = 'settings';
require __DIR__ . '/includes/header.php';
?>

<div class="adm-card" style="max-width:720px">
  <form method="post">
    <?= csrf_field() ?>
    <?php foreach ($fields as $key => [$label, $type]): ?>
      <label class="adm-field"><?= e($label) ?>
        <?php if ($type === 'textarea'): ?>
          <textarea name="<?= e($key) ?>" rows="3"><?= e(setting($key)) ?></textarea>
        <?php else: ?>
          <input type="text" name="<?= e($key) ?>" value="<?= e(setting($key)) ?>">
        <?php endif; ?>
      </label>
    <?php endforeach; ?>
    <button class="btn btn-primary" type="submit">Išsaugoti</button>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
