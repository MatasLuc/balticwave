<?php
/**
 * Admin registration. Open to anyone ONLY while no admin account exists
 * (first-run). Afterwards new admins are created from the Users screen.
 */
require_once __DIR__ . '/includes/boot.php';

try {
    $userCount = (int)q_val('SELECT COUNT(*) FROM users');
} catch (Throwable $e) {
    exit('Duomenų bazė nepasiekiama. Pirmiausia paleiskite <a href="../setupdb.php">setupdb.php</a>.');
}
if ($userCount > 0 && !is_logged_in()) {
    header('Location: login.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim((string)($_POST['username'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $pass     = (string)($_POST['password'] ?? '');
    $pass2    = (string)($_POST['password2'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $username)) {
        $error = 'Vartotojo vardas: 3–60 simbolių (raidės, skaičiai, taškas, brūkšnys).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Neteisingas el. pašto adresas.';
    } elseif (strlen($pass) < 8) {
        $error = 'Slaptažodis turi būti bent 8 simbolių.';
    } elseif ($pass !== $pass2) {
        $error = 'Slaptažodžiai nesutampa.';
    } elseif (q_row('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email])) {
        $error = 'Toks vartotojas arba el. paštas jau egzistuoja.';
    } else {
        q('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)',
          [$username, $email, password_hash($pass, PASSWORD_DEFAULT)]);
        if (!is_logged_in()) {
            $_SESSION['user_id'] = (int)db()->lastInsertId();
            session_regenerate_id(true);
        }
        flash('Administratoriaus paskyra sukurta. Sveiki atvykę!');
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Registracija — Baltic Wave CMS</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/admin.css?v=<?= e(BW_VERSION) ?>">
</head>
<body class="bw-admin adm-auth">
<div class="auth-card">
  <h1><span>Baltic Wave</span> CMS</h1>
  <p class="sub"><?= $userCount === 0 ? 'Sukurkite pirmąją administratoriaus paskyrą' : 'Nauja administratoriaus paskyra' ?></p>
  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label class="adm-field">Vartotojo vardas
      <input type="text" name="username" required minlength="3" maxlength="60" value="<?= e($_POST['username'] ?? '') ?>">
    </label>
    <label class="adm-field">El. paštas
      <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
    </label>
    <label class="adm-field">Slaptažodis <span class="adm-help">bent 8 simboliai</span>
      <input type="password" name="password" required minlength="8">
    </label>
    <label class="adm-field">Pakartokite slaptažodį
      <input type="password" name="password2" required minlength="8">
    </label>
    <button class="btn btn-primary" type="submit">Sukurti paskyrą →</button>
  </form>
  <?php if ($userCount > 0): ?>
    <div class="auth-foot"><a href="login.php">← Prisijungimas</a></div>
  <?php endif; ?>
</div>
</body>
</html>
