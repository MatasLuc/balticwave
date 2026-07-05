<?php
require_once __DIR__ . '/functions.php';

/** Start the session with sane cookie settings (idempotent). */
function bw_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('bwcms');
    session_start();
}

/** Currently logged-in admin row, or null. */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            try {
                $user = q_row('SELECT id, username, email, created_at FROM users WHERE id = ?',
                              [$_SESSION['user_id']]);
            } catch (Throwable $e) {
                $user = null;
            }
        }
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/** Gate an admin page: redirect to login when not authenticated. */
function require_admin(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/** Attempt login; returns error message or null on success. */
function attempt_login(string $username, string $password): ?string
{
    // Small brute-force brake: after 5 failures wait 30 s.
    $fails = $_SESSION['login_fails'] ?? 0;
    $last  = $_SESSION['login_last'] ?? 0;
    if ($fails >= 5 && time() - $last < 30) {
        return 'Per daug bandymų. Palaukite 30 sekundžių.';
    }

    $user = q_row('SELECT * FROM users WHERE username = ? OR email = ?', [$username, $username]);
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        unset($_SESSION['login_fails'], $_SESSION['login_last']);
        return null;
    }
    $_SESSION['login_fails'] = $fails + 1;
    $_SESSION['login_last']  = time();
    return 'Neteisingas vartotojo vardas arba slaptažodis.';
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'],
                  $p['secure'], $p['httponly']);
    }
    session_destroy();
}
