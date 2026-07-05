<?php
require_once __DIR__ . '/includes/boot.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = null;
$noUsers = false;
try {
    $noUsers = (int)q_val('SELECT COUNT(*) FROM users') === 0;
} catch (Throwable $e) {
    $error = 'Duomenų bazė nepasiekiama. Ar paleidote setupdb.php?';
}
if ($noUsers) {
    header('Location: register.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === null) {
    csrf_check();
    $error = attempt_login(trim((string)($_POST['username'] ?? '')), (string)($_POST['password'] ?? ''));
    if ($error === null) {
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
<title>Prisijungimas — Baltic Wave CMS</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/admin.css?v=<?= e(BW_VERSION) ?>">
</head>
<body class="bw-admin adm-auth">
<div class="auth-card">
  <h1><span>Baltic Wave</span> CMS</h1>
  <p class="sub">Prisijunkite prie administravimo pulto</p>
  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label class="adm-field">Vartotojo vardas arba el. paštas
      <input type="text" name="username" required autofocus>
    </label>
    <label class="adm-field">Slaptažodis
      <input type="password" name="password" required>
    </label>
    <button class="btn btn-primary" type="submit">Prisijungti →</button>
  </form>
  <div class="auth-foot"><a href="<?= e(base_url()) ?>/">← Grįžti į svetainę</a></div>
</div>
</body>
</html>
