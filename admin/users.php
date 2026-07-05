<?php
require_once __DIR__ . '/includes/boot.php';
require_admin();

$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = $_POST['do'] ?? '';

    if ($do === 'create') {
        $username = trim((string)($_POST['username'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $pass     = (string)($_POST['password'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $username)) {
            flash('Vartotojo vardas: 3–60 simbolių (raidės, skaičiai, taškas, brūkšnys).', 'error');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Neteisingas el. paštas.', 'error');
        } elseif (strlen($pass) < 8) {
            flash('Slaptažodis turi būti bent 8 simbolių.', 'error');
        } elseif (q_row('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email])) {
            flash('Toks vartotojas jau egzistuoja.', 'error');
        } else {
            q('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)',
              [$username, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            flash("Administratorius „{$username}“ sukurtas.");
        }
    } elseif ($do === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$me['id']) {
            flash('Negalite ištrinti savo paskyros.', 'error');
        } elseif ((int)q_val('SELECT COUNT(*) FROM users') <= 1) {
            flash('Turi likti bent vienas administratorius.', 'error');
        } else {
            q('DELETE FROM users WHERE id = ?', [$id]);
            flash('Paskyra ištrinta.');
        }
    } elseif ($do === 'password') {
        $current = (string)($_POST['current'] ?? '');
        $new     = (string)($_POST['new'] ?? '');
        $row     = q_row('SELECT password_hash FROM users WHERE id = ?', [$me['id']]);
        if (!$row || !password_verify($current, $row['password_hash'])) {
            flash('Neteisingas dabartinis slaptažodis.', 'error');
        } elseif (strlen($new) < 8) {
            flash('Naujas slaptažodis turi būti bent 8 simbolių.', 'error');
        } else {
            q('UPDATE users SET password_hash = ? WHERE id = ?',
              [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            flash('Slaptažodis pakeistas.');
        }
    }
    header('Location: users.php');
    exit;
}

$users = q_all('SELECT id, username, email, created_at FROM users ORDER BY id');

$adminTitle   = 'Vartotojai';
$adminSection = 'users';
require __DIR__ . '/includes/header.php';
?>

<div class="adm-card">
  <h2>Administratoriai</h2>
  <table class="adm-table">
    <tr><th>Vardas</th><th>El. paštas</th><th>Sukurta</th><th class="actions"></th></tr>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><strong><?= e($u['username']) ?></strong><?= (int)$u['id'] === (int)$me['id'] ? ' <span class="pill pill-muted">jūs</span>' : '' ?></td>
      <td><?= e($u['email']) ?></td>
      <td><small style="color:var(--muted)"><?= e($u['created_at']) ?></small></td>
      <td class="actions">
        <?php if ((int)$u['id'] !== (int)$me['id']): ?>
        <form method="post" onsubmit="return confirm('Ištrinti paskyrą „<?= e($u['username']) ?>“?')">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="delete">
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit">Šalinti</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="adm-grid cols-2">
  <div class="adm-card">
    <h2>Naujas administratorius</h2>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="do" value="create">
      <label class="adm-field">Vartotojo vardas
        <input type="text" name="username" required minlength="3" maxlength="60">
      </label>
      <label class="adm-field">El. paštas
        <input type="email" name="email" required>
      </label>
      <label class="adm-field">Slaptažodis (bent 8 simboliai)
        <input type="password" name="password" required minlength="8">
      </label>
      <button class="btn btn-primary" type="submit">Sukurti</button>
    </form>
  </div>

  <div class="adm-card">
    <h2>Keisti savo slaptažodį</h2>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="do" value="password">
      <label class="adm-field">Dabartinis slaptažodis
        <input type="password" name="current" required>
      </label>
      <label class="adm-field">Naujas slaptažodis (bent 8 simboliai)
        <input type="password" name="new" required minlength="8">
      </label>
      <button class="btn btn-primary" type="submit">Pakeisti</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
