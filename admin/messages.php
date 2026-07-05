<?php
require_once __DIR__ . '/includes/boot.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = $_POST['do'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($do === 'read') {
        q('UPDATE messages SET is_read = 1 WHERE id = ?', [$id]);
    } elseif ($do === 'unread') {
        q('UPDATE messages SET is_read = 0 WHERE id = ?', [$id]);
    } elseif ($do === 'delete') {
        q('DELETE FROM messages WHERE id = ?', [$id]);
        flash('Žinutė ištrinta.');
    } elseif ($do === 'read_all') {
        q('UPDATE messages SET is_read = 1');
        flash('Visos žinutės pažymėtos skaitytomis.');
    }
    header('Location: messages.php');
    exit;
}

$messages = q_all('SELECT * FROM messages ORDER BY created_at DESC LIMIT 300');

$adminTitle   = 'Žinutės';
$adminSection = 'messages';
$adminActions = '<form method="post">' . csrf_field()
              . '<input type="hidden" name="do" value="read_all">'
              . '<button class="btn btn-ghost" type="submit">Pažymėti visas skaitytomis</button></form>';
require __DIR__ . '/includes/header.php';
?>

<?php if (!$messages): ?>
  <div class="adm-card"><p style="color:var(--muted);margin:0">Žinučių dar nėra. Jos atsiras, kai lankytojai užpildys kontaktų formą.</p></div>
<?php endif; ?>

<?php foreach ($messages as $m): ?>
<details class="msg <?= $m['is_read'] ? '' : 'unread' ?>">
  <summary>
    <span class="who"><?= e($m['name']) ?></span>
    <a href="mailto:<?= e($m['email']) ?>" style="font-size:.85rem"><?= e($m['email']) ?></a>
    <?= $m['is_read'] ? '' : '<span class="pill pill-on">nauja</span>' ?>
    <span class="when"><?= e($m['created_at']) ?></span>
  </summary>
  <div class="body"><?= e($m['message']) ?></div>
  <div class="msg-actions">
    <form method="post"><?= csrf_field() ?>
      <input type="hidden" name="do" value="<?= $m['is_read'] ? 'unread' : 'read' ?>">
      <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
      <button class="btn btn-ghost btn-sm" type="submit"><?= $m['is_read'] ? 'Žymėti neskaityta' : 'Žymėti skaityta' ?></button>
    </form>
    <form method="post" onsubmit="return confirm('Ištrinti žinutę?')"><?= csrf_field() ?>
      <input type="hidden" name="do" value="delete">
      <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
      <button class="btn btn-danger btn-sm" type="submit">Šalinti</button>
    </form>
  </div>
</details>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
