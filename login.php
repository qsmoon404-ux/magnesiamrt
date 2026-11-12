<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
start_session();
if (current_user()) { header('Location: ' . url('admin/index.php')); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Сессия устарела. Обновите страницу и повторите вход.';
    } elseif (rate_limited('login', 6, 900)) {
        $error = 'Слишком много попыток. Подождите 15 минут.';
    } elseif (login_user((string)($_POST['login'] ?? ''), (string)($_POST['password'] ?? ''))) {
        header('Location: ' . url('admin/index.php')); exit;
    } else {
        rate_hit('login');
        $error = 'Неверный логин или пароль.';
    }
}
?><!DOCTYPE html>
<html lang="ru"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Вход для сотрудников — Магнесия</title>
<link rel="icon" href="<?= url('assets/img/favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= url('assets/css/admin.css') ?>?v=1">
</head><body class="login-body">
<main class="login-card">
  <div class="login-brand">
    <svg viewBox="0 0 64 64" width="44" height="44" aria-hidden="true"><circle cx="32" cy="32" r="22" fill="none" stroke="#22c3d6" stroke-width="5"/><path d="M32 16v32M16 32h32" stroke="#b08d57" stroke-width="4" stroke-linecap="round"/></svg>
    <div><b>Магнесия</b><span>кабинет сотрудников</span></div>
  </div>
  <h1>Вход</h1>
  <?php if ($error): ?><p class="alert alert-bad" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" autocomplete="on">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label for="login">Логин</label>
    <input id="login" name="login" type="text" autocomplete="username" autofocus required value="<?= e($_POST['login'] ?? '') ?>">
    <label for="password">Пароль</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    <button class="btn btn-primary btn-block" type="submit">Войти</button>
  </form>
  <p class="login-back"><a href="<?= url('') ?>">← На сайт клиники</a></p>
</main>
</body></html>
