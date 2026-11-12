<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
start_session();
$me = require_login();
$pdo = db();
$isAdmin = $me['role'] === 'admin';
$isOp    = $me['role'] === 'operator';
$isDoc   = $me['role'] === 'doctor';
$mustChange = (int)$me['must_change'] === 1;

function flash(string $type, string $msg): void { $_SESSION['flash'] = [$type, $msg]; }
function back(string $tab = 'appointments', string $qs = ''): never {
    header('Location: ' . url('admin/index.php') . '?tab=' . $tab . $qs); exit;
}
function valid_password(string $p): ?string {
    if (mb_strlen($p) < 8) return 'Пароль должен быть не короче 8 символов.';
    if (!preg_match('/\p{L}/u', $p) || !preg_match('/\d/', $p)) return 'В пароле должны быть буквы и цифры.';
    return null;
}
function can_touch_appt(array $me, array $a): bool {
    return $me['role'] !== 'doctor' || (int)$a['doctor_id'] === (int)$me['id'];
}

/* ---------- Обработка действий (только POST + CSRF) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) { flash('bad', 'Сессия устарела. Повторите действие.'); back(); }
    $act = (string)($_POST['action'] ?? '');

    // Смена своего пароля — доступна всем
    if ($act === 'change_password') {
        $cur = (string)($_POST['current'] ?? ''); $new = (string)($_POST['new'] ?? ''); $rep = (string)($_POST['repeat'] ?? '');
        $hash = $pdo->prepare('SELECT password_hash FROM users WHERE id=?'); $hash->execute([$me['id']]);
        if (!password_verify($cur, (string)$hash->fetchColumn())) flash('bad', 'Текущий пароль указан неверно.');
        elseif ($new !== $rep) flash('bad', 'Новые пароли не совпадают.');
        elseif ($err = valid_password($new)) flash('bad', $err);
        elseif ($new === $cur) flash('bad', 'Новый пароль должен отличаться от текущего.');
        else {
            $pdo->prepare('UPDATE users SET password_hash=?, must_change=0 WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            flash('ok', 'Пароль изменён.');
            back('appointments');
        }
        back('profile');
    }
    if ($mustChange) { flash('bad', 'Сначала смените временный пароль.'); back('profile'); }

    $aid = (int)($_POST['id'] ?? 0);
    $appt = null;
    if (in_array($act, ['appt_status', 'appt_assign', 'appt_note', 'appt_delete'], true)) {
        $st = $pdo->prepare('SELECT * FROM appointments WHERE id=?'); $st->execute([$aid]); $appt = $st->fetch();
        if (!$appt || !can_touch_appt($me, $appt)) { flash('bad', 'Заявка не найдена или недоступна.'); back(); }
    }
    $keep = '&' . http_build_query(array_filter(['f' => $_POST['f'] ?? '', 'q' => $_POST['q'] ?? '', 'd' => $_POST['d'] ?? '']));

    switch ($act) {
        case 'appt_status':
            $s = (string)($_POST['status'] ?? '');
            $allowed = $isAdmin ? array_keys(STATUSES) : ($isOp ? ['new', 'confirmed', 'canceled'] : ['confirmed', 'done']);
            if (!in_array($s, $allowed, true)) { flash('bad', 'Этот статус вам недоступен.'); back('appointments', $keep); }
            $pdo->prepare('UPDATE appointments SET status=? WHERE id=?')->execute([$s, $aid]);
            flash('ok', "Заявка №$aid: статус обновлён."); back('appointments', $keep);

        case 'appt_note':
            $pdo->prepare('UPDATE appointments SET staff_note=? WHERE id=?')->execute([mb_substr(trim((string)($_POST['note'] ?? '')), 0, 500), $aid]);
            flash('ok', "Заявка №$aid: заметка сохранена."); back('appointments', $keep);

        case 'appt_assign':
            if (!$isAdmin) { flash('bad', 'Назначать врача может только администратор.'); back('appointments', $keep); }
            $did = (int)($_POST['doctor_id'] ?? 0);
            if ($did) {
                $c = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id=? AND role='doctor'"); $c->execute([$did]);
                if (!(int)$c->fetchColumn()) { flash('bad', 'Врач не найден.'); back('appointments', $keep); }
                $newStatus = in_array($appt['status'], ['new', 'confirmed'], true) ? 'assigned' : $appt['status'];
                $pdo->prepare('UPDATE appointments SET doctor_id=?, status=? WHERE id=?')->execute([$did, $newStatus, $aid]);
                flash('ok', "Заявка №$aid направлена врачу.");
            } else {
                $pdo->prepare("UPDATE appointments SET doctor_id=NULL, status=CASE WHEN status='assigned' THEN 'confirmed' ELSE status END WHERE id=?")->execute([$aid]);
                flash('ok', "Заявка №$aid снята с врача.");
            }
            back('appointments', $keep);

        case 'appt_delete':
            if (!$isAdmin) { flash('bad', 'Удалять заявки может только администратор.'); back('appointments', $keep); }
            $pdo->prepare('DELETE FROM appointments WHERE id=?')->execute([$aid]);
            flash('ok', "Заявка №$aid удалена."); back('appointments', $keep);

        case 'user_add':
            if (!$isAdmin) { flash('bad', 'Доступ запрещён.'); back(); }
            $login = mb_strtolower(trim((string)($_POST['login'] ?? '')));
            $name = trim((string)($_POST['full_name'] ?? ''));
            $role = (string)($_POST['role'] ?? '');
            $spec = trim((string)($_POST['speciality'] ?? ''));
            $pass = (string)($_POST['password'] ?? '');
            if (!preg_match('/^[a-z0-9_.\-]{3,32}$/', $login)) flash('bad', 'Логин: 3–32 символа, латиница, цифры, точка, дефис, подчёркивание.');
            elseif (mb_strlen($name) < 3 || mb_strlen($name) > 100) flash('bad', 'Укажите ФИО (3–100 символов).');
            elseif (!isset(ROLES[$role])) flash('bad', 'Выберите роль.');
            elseif ($err = valid_password($pass)) flash('bad', $err);
            else {
                try {
                    $pdo->prepare('INSERT INTO users(login,password_hash,full_name,role,speciality,must_change) VALUES(?,?,?,?,?,1)')
                        ->execute([$login, password_hash($pass, PASSWORD_DEFAULT), $name, $role, mb_substr($spec, 0, 100)]);
                    flash('ok', "Пользователь «{$login}» добавлен. При первом входе он должен сменить пароль.");
                } catch (PDOException $e) { flash('bad', 'Такой логин уже существует.'); }
            }
            back('users');

        case 'user_delete':
            if (!$isAdmin) { flash('bad', 'Доступ запрещён.'); back(); }
            $uid = (int)($_POST['uid'] ?? 0);
            $t = $pdo->prepare('SELECT * FROM users WHERE id=?'); $t->execute([$uid]); $target = $t->fetch();
            if (!$target) flash('bad', 'Пользователь не найден.');
            elseif ($uid === (int)$me['id']) flash('bad', 'Нельзя удалить самого себя.');
            elseif ($target['role'] === 'admin' && (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() <= 1) flash('bad', 'Нельзя удалить последнего администратора.');
            else { $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]); flash('ok', "Пользователь «{$target['login']}» удалён."); }
            back('users');

        case 'user_reset':
            if (!$isAdmin) { flash('bad', 'Доступ запрещён.'); back(); }
            $uid = (int)($_POST['uid'] ?? 0); $pass = (string)($_POST['password'] ?? '');
            if ($err = valid_password($pass)) flash('bad', $err);
            else {
                $pdo->prepare('UPDATE users SET password_hash=?, must_change=1 WHERE id=?')->execute([password_hash($pass, PASSWORD_DEFAULT), $uid]);
                flash('ok', 'Временный пароль установлен. Пользователь сменит его при входе.');
            }
            back('users');
    }
    back();
}

/* ---------- Данные для страницы ---------- */
$tab = (string)($_GET['tab'] ?? 'appointments');
if ($mustChange) $tab = 'profile';
if ($tab === 'users' && !$isAdmin) $tab = 'appointments';
if (!in_array($tab, ['appointments', 'users', 'profile'], true)) $tab = 'appointments';

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$doctors = $pdo->query("SELECT id, full_name FROM users WHERE role='doctor' ORDER BY full_name")->fetchAll();

$f = (string)($_GET['f'] ?? ''); $q = trim((string)($_GET['q'] ?? '')); $dfil = (int)($_GET['d'] ?? 0);
$where = []; $args = [];
if ($isDoc) { $where[] = 'a.doctor_id = ?'; $args[] = $me['id']; }
elseif ($dfil) { $where[] = 'a.doctor_id = ?'; $args[] = $dfil; }
if ($f !== '' && isset(STATUSES[$f])) { $where[] = 'a.status = ?'; $args[] = $f; }
if ($q !== '') { $where[] = '(a.name LIKE ? OR a.phone LIKE ? OR a.service LIKE ?)'; $like = '%' . $q . '%'; array_push($args, $like, $like, $like); }
$sql = 'SELECT a.*, u.full_name AS doctor_name FROM appointments a LEFT JOIN users u ON u.id=a.doctor_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY a.id DESC LIMIT 300';
$st = $pdo->prepare($sql); $st->execute($args); $appts = $st->fetchAll();

$cnt = ['new' => 0, 'today' => 0, 'total' => 0];
$base = $isDoc ? ' WHERE doctor_id=' . (int)$me['id'] : '';
$cnt['total'] = (int)$pdo->query('SELECT COUNT(*) FROM appointments' . $base)->fetchColumn();
$cnt['new']   = (int)$pdo->query("SELECT COUNT(*) FROM appointments" . ($base ?: ' WHERE 1=1') . ($isDoc ? " AND status='assigned'" : " AND status='new'"))->fetchColumn();
$cnt['today'] = (int)$pdo->query("SELECT COUNT(*) FROM appointments" . ($base ?: ' WHERE 1=1') . " AND preferred_date='" . date('Y-m-d') . "'")->fetchColumn();
$users = $isAdmin ? $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id=u.id) AS appts FROM users u ORDER BY CASE role WHEN 'admin' THEN 0 WHEN 'operator' THEN 1 ELSE 2 END, full_name")->fetchAll() : [];
$csrf = csrf_token();
$keepFields = '<input type="hidden" name="f" value="' . e($f) . '"><input type="hidden" name="q" value="' . e($q) . '"><input type="hidden" name="d" value="' . $dfil . '">';

function fmt_date(string $d): string { $x = DateTime::createFromFormat('Y-m-d', $d); return $x ? $x->format('d.m.Y') : $d; }
?><!DOCTYPE html>
<html lang="ru"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Кабинет — Магнесия</title>
<link rel="icon" href="<?= url('assets/img/favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= url('assets/css/admin.css') ?>?v=1">
</head><body>
<header class="a-head">
  <div class="a-wrap a-bar">
    <a class="a-brand" href="<?= url('admin/index.php') ?>">
      <svg viewBox="0 0 64 64" width="32" height="32" aria-hidden="true"><circle cx="32" cy="32" r="22" fill="none" stroke="#22c3d6" stroke-width="5"/><path d="M32 16v32M16 32h32" stroke="#b08d57" stroke-width="4" stroke-linecap="round"/></svg>
      <b>Магнесия</b><span>кабинет</span>
    </a>
    <nav class="a-nav" aria-label="Разделы кабинета">
      <a href="?tab=appointments" class="<?= $tab === 'appointments' ? 'on' : '' ?>"><?= $isDoc ? 'Мои пациенты' : 'Заявки' ?></a>
      <?php if ($isAdmin): ?><a href="?tab=users" class="<?= $tab === 'users' ? 'on' : '' ?>">Сотрудники</a><?php endif; ?>
      <a href="?tab=profile" class="<?= $tab === 'profile' ? 'on' : '' ?>">Профиль</a>
    </nav>
    <div class="a-user">
      <span><b><?= e($me['full_name']) ?></b><small><?= e(ROLES[$me['role']]) ?></small></span>
      <form method="post" action="<?= url('admin/logout.php') ?>"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button class="btn btn-line btn-sm" type="submit">Выйти</button></form>
    </div>
  </div>
</header>

<main class="a-wrap a-main">
<?php if ($flash): ?><p class="alert alert-<?= $flash[0] === 'ok' ? 'ok' : 'bad' ?>" role="status"><?= e($flash[1]) ?></p><?php endif; ?>

<?php if ($tab === 'appointments'): ?>
  <h1><?= $isDoc ? 'Назначенные мне заявки' : 'Заявки на МРТ' ?></h1>
  <div class="stats">
    <div class="stat"><b><?= $cnt['new'] ?></b><span><?= $isDoc ? 'Ждут приёма' : 'Новые' ?></span></div>
    <div class="stat"><b><?= $cnt['today'] ?></b><span>На сегодня</span></div>
    <div class="stat"><b><?= $cnt['total'] ?></b><span>Всего</span></div>
  </div>

  <form class="filters" method="get">
    <input type="hidden" name="tab" value="appointments">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Имя, телефон или исследование" aria-label="Поиск">
    <select name="f" aria-label="Статус">
      <option value="">Все статусы</option>
      <?php foreach (STATUSES as $k => $v): ?><option value="<?= $k ?>" <?= $f === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select>
    <?php if (!$isDoc): ?>
    <select name="d" aria-label="Врач">
      <option value="0">Все врачи</option>
      <?php foreach ($doctors as $d): ?><option value="<?= $d['id'] ?>" <?= $dfil === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['full_name']) ?></option><?php endforeach; ?>
    </select>
    <?php endif; ?>
    <button class="btn btn-primary btn-sm" type="submit">Найти</button>
    <?php if ($f !== '' || $q !== '' || $dfil): ?><a class="btn btn-line btn-sm" href="?tab=appointments">Сбросить</a><?php endif; ?>
  </form>

  <?php if (!$appts): ?>
    <p class="empty-state"><?= $isDoc ? 'Пока нет заявок, назначенных вам. Администратор направит их сюда.' : 'Заявок не найдено. Новые заявки с сайта появятся здесь.' ?></p>
  <?php endif; ?>

  <div class="cards">
  <?php foreach ($appts as $a): ?>
    <article class="appt st-<?= e($a['status']) ?>">
      <header>
        <span class="id">№ <?= (int)$a['id'] ?></span>
        <span class="badge b-<?= e($a['status']) ?>"><?= e(STATUSES[$a['status']] ?? $a['status']) ?></span>
        <time class="created"><?= e(date('d.m.Y H:i', strtotime($a['created_at']))) ?></time>
      </header>
      <h3><?= e($a['name']) ?></h3>
      <p class="ph"><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $a['phone'])) ?>"><?= e($a['phone']) ?></a></p>
      <dl>
        <dt>Исследование</dt><dd><?= e($a['service']) ?></dd>
        <dt>Желаемое время</dt><dd><?= e(fmt_date($a['preferred_date'])) ?>, <?= e($a['preferred_time']) ?></dd>
        <dt>Врач</dt><dd><?= $a['doctor_name'] ? e($a['doctor_name']) : '<span class="muted">не назначен</span>' ?></dd>
        <?php if ($a['comment'] !== ''): ?><dt>Комментарий пациента</dt><dd><?= nl2br(e($a['comment'])) ?></dd><?php endif; ?>
        <?php if ($a['staff_note'] !== ''): ?><dt>Заметка сотрудника</dt><dd><?= nl2br(e($a['staff_note'])) ?></dd><?php endif; ?>
      </dl>

      <details class="manage"><summary>Управление</summary>
        <?php if ($isAdmin): ?>
        <form method="post" class="row">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="appt_assign"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><?= $keepFields ?>
          <label>Направить врачу
            <select name="doctor_id">
              <option value="0">— не назначен —</option>
              <?php foreach ($doctors as $d): ?><option value="<?= $d['id'] ?>" <?= (int)$a['doctor_id'] === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['full_name']) ?></option><?php endforeach; ?>
            </select>
          </label>
          <button class="btn btn-primary btn-sm" type="submit">Направить</button>
        </form>
        <?php endif; ?>

        <form method="post" class="row">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="appt_status"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><?= $keepFields ?>
          <label>Статус
            <select name="status">
              <?php
              $opts = $isAdmin ? STATUSES : ($isOp ? array_intersect_key(STATUSES, array_flip(['new', 'confirmed', 'canceled'])) : array_intersect_key(STATUSES, array_flip(['confirmed', 'done'])));
              foreach ($opts as $k => $v): ?><option value="<?= $k ?>" <?= $a['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
            </select>
          </label>
          <button class="btn btn-line btn-sm" type="submit">Сохранить</button>
        </form>

        <form method="post" class="row">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="appt_note"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><?= $keepFields ?>
          <label>Заметка <input type="text" name="note" maxlength="500" value="<?= e($a['staff_note']) ?>" placeholder="Например: перезвонить после 15:00"></label>
          <button class="btn btn-line btn-sm" type="submit">Сохранить</button>
        </form>

        <?php if ($isAdmin): ?>
        <form method="post" class="row danger-row" data-confirm="Удалить заявку № <?= (int)$a['id'] ?> безвозвратно?">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="appt_delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><?= $keepFields ?>
          <button class="btn btn-danger btn-sm" type="submit">Удалить заявку</button>
        </form>
        <?php endif; ?>
      </details>
    </article>
  <?php endforeach; ?>
  </div>

<?php elseif ($tab === 'users' && $isAdmin): ?>
  <h1>Сотрудники</h1>
  <div class="split">
    <section class="panel">
      <h2>Добавить сотрудника</h2>
      <form method="post" class="stack">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="user_add">
        <label>Логин <small>латиница и цифры</small><input name="login" required pattern="[A-Za-z0-9_.\-]{3,32}" autocomplete="off"></label>
        <label>ФИО<input name="full_name" required maxlength="100" autocomplete="off"></label>
        <label>Роль
          <select name="role" required>
            <?php foreach (ROLES as $k => $v): ?><option value="<?= $k ?>" <?= $k === 'doctor' ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Должность <small>необязательно</small><input name="speciality" maxlength="100" placeholder="Врач-рентгенолог"></label>
        <label>Временный пароль <small>от 8 символов, буквы и цифры</small><input name="password" type="text" required minlength="8" autocomplete="new-password"></label>
        <button class="btn btn-primary" type="submit">Добавить</button>
      </form>
    </section>

    <section class="panel">
      <h2>Все пользователи <small>(<?= count($users) ?>)</small></h2>
      <div class="ulist">
      <?php foreach ($users as $u): ?>
        <article class="user">
          <div>
            <b><?= e($u['full_name']) ?></b>
            <span class="badge r-<?= e($u['role']) ?>"><?= e(ROLES[$u['role']]) ?></span>
            <p class="muted">Логин: <code><?= e($u['login']) ?></code><?= $u['role'] === 'doctor' ? ' · заявок: ' . (int)$u['appts'] : '' ?><?= (int)$u['must_change'] ? ' · ждёт смены пароля' : '' ?></p>
          </div>
          <details class="manage"><summary>Действия</summary>
            <form method="post" class="row">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="user_reset"><input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
              <label>Новый временный пароль<input name="password" type="text" minlength="8" required autocomplete="off"></label>
              <button class="btn btn-line btn-sm" type="submit">Сбросить</button>
            </form>
            <?php if ((int)$u['id'] !== (int)$me['id']): ?>
            <form method="post" class="row danger-row" data-confirm="Удалить пользователя «<?= e($u['login']) ?>»? Его заявки останутся без врача.">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="user_delete"><input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit">Удалить пользователя</button>
            </form>
            <?php endif; ?>
          </details>
        </article>
      <?php endforeach; ?>
      </div>
    </section>
  </div>

<?php else: ?>
  <h1>Профиль</h1>
  <?php if ($mustChange): ?><p class="alert alert-warn">Вы вошли с временным паролем. Придумайте свой — без этого другие разделы недоступны.</p><?php endif; ?>
  <section class="panel narrow">
    <p><b><?= e($me['full_name']) ?></b><br><span class="muted">Логин: <code><?= e($me['login']) ?></code> · <?= e(ROLES[$me['role']]) ?></span></p>
    <h2>Смена пароля</h2>
    <form method="post" class="stack">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="change_password">
      <label>Текущий пароль<input name="current" type="password" required autocomplete="current-password"></label>
      <label>Новый пароль <small>от 8 символов, буквы и цифры</small><input name="new" type="password" required minlength="8" autocomplete="new-password"></label>
      <label>Повторите новый пароль<input name="repeat" type="password" required minlength="8" autocomplete="new-password"></label>
      <button class="btn btn-primary" type="submit">Сохранить пароль</button>
    </form>
  </section>
<?php endif; ?>
</main>
<script src="<?= url('assets/js/admin.js') ?>?v=1" defer></script>
</body></html>
