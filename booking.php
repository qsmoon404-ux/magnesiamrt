<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(int $code, array $data): never { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['ok' => false, 'error' => 'Метод не поддерживается.']);
if (!csrf_check($_POST['csrf'] ?? null)) out(419, ['ok' => false, 'error' => 'Страница устарела. Обновите её и отправьте форму ещё раз.']);

// Ловушка для ботов и защита от слишком быстрой отправки
if (!empty($_POST['website'])) out(200, ['ok' => true, 'id' => 0]);
$rendered = (int)($_POST['ts'] ?? 0);
if ($rendered && time() - $rendered < 3) out(429, ['ok' => false, 'error' => 'Слишком быстро. Проверьте данные и отправьте ещё раз.']);

if (rate_limited('booking', 5, 3600)) out(429, ['ok' => false, 'error' => 'Слишком много заявок. Позвоните нам: ' . SITE_PHONE]);

$errors = [];
$name = trim(preg_replace('/\s+/u', ' ', (string)($_POST['name'] ?? '')));
if (mb_strlen($name) < 2 || mb_strlen($name) > 100 || !preg_match('/^[\p{L}\s\.\-\']+$/u', $name)) $errors['name'] = 'Укажите имя буквами, без цифр.';

$phone = normalize_phone((string)($_POST['phone'] ?? ''));
if (!$phone) $errors['phone'] = 'Введите номер полностью, например +7 (900) 123-45-67.';

$service = (string)($_POST['service'] ?? '');
if (!in_array($service, services_flat(), true)) $errors['service'] = 'Выберите исследование из списка.';

$date = (string)($_POST['date'] ?? '');
$d = DateTime::createFromFormat('!Y-m-d', $date);
$today = new DateTime('today');
if (!$d || $d->format('Y-m-d') !== $date) $errors['date'] = 'Выберите дату.';
elseif ($d < $today || $d > (clone $today)->modify('+60 days')) $errors['date'] = 'Запись доступна на ближайшие 60 дней.';

$time = (string)($_POST['time'] ?? '');
if (!preg_match('/^(0[7-9]|1\d|2[0-2]):00$/', $time)) $errors['time'] = 'Выберите удобное время.';

$comment = trim((string)($_POST['comment'] ?? ''));
if (mb_strlen($comment) > 500) $errors['comment'] = 'Не более 500 символов.';

if (empty($_POST['consent'])) $errors['consent'] = 'Нужно согласие на обработку персональных данных.';

if ($errors) out(422, ['ok' => false, 'errors' => $errors, 'error' => 'Проверьте выделенные поля.']);

rate_hit('booking');
$st = db()->prepare('INSERT INTO appointments(name,phone,service,preferred_date,preferred_time,comment,ip) VALUES(?,?,?,?,?,?,?)');
$st->execute([$name, $phone, $service, $date, $time, $comment, client_ip()]);
out(200, ['ok' => true, 'id' => (int)db()->lastInsertId()]);
