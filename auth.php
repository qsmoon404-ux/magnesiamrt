<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function current_user(): ?array {
    start_session();
    if (empty($_SESSION['uid'])) return null;
    $st = db()->prepare('SELECT id,login,full_name,role,speciality,must_change FROM users WHERE id=?');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    if (!$u) { unset($_SESSION['uid']); return null; }
    return $u;
}
function require_login(): array {
    $u = current_user();
    if (!$u) { header('Location: ' . url('admin/login.php')); exit; }
    return $u;
}
function login_user(string $login, string $pass): bool {
    $st = db()->prepare('SELECT id,password_hash FROM users WHERE login=?');
    $st->execute([mb_strtolower(trim($login))]);
    $row = $st->fetch();
    $hash = $row['password_hash'] ?? password_hash('dummy', PASSWORD_DEFAULT);
    $ok = password_verify($pass, $hash) && $row;
    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$row['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return true;
    }
    return false;
}
function logout_user(): void {
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
