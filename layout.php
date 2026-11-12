<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

function page_head(string $title, string $desc, string $path = '', string $extraHead = '', bool $index = true): void {
    $canon = base_url() . '/' . ltrim($path, '/');
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#0a2530">
<?php if (!$index): ?><meta name="robots" content="noindex, nofollow"><?php else: ?>
<link rel="canonical" href="<?= e($canon) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="ru_RU">
<meta property="og:site_name" content="Магнесия — МРТ в Якутске">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($canon) ?>">
<?php endif; ?>
<link rel="icon" href="<?= url('assets/img/favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=1">
<?= $extraHead ?>
</head>
<body>
<a class="skip" href="#main">К содержимому</a>
<header class="site-header" id="top">
  <div class="wrap bar">
    <a class="brand" href="<?= url('') ?>" aria-label="Магнесия — на главную">
      <svg viewBox="0 0 64 64" width="38" height="38" aria-hidden="true"><circle cx="32" cy="32" r="22" fill="none" stroke="#22c3d6" stroke-width="5"/><path d="M32 16v32M16 32h32" stroke="#b08d57" stroke-width="4" stroke-linecap="round"/></svg>
      <span><b>Магнесия</b><small>центр МРТ-диагностики · Якутск</small></span>
    </a>
    <nav class="nav" id="nav" aria-label="Основное меню">
      <a href="<?= url('') ?>#services">Исследования</a>
      <a href="<?= url('') ?>#prices">Цены</a>
      <a href="<?= url('') ?>#doctors">Врачи</a>
      <a href="<?= url('') ?>#equipment">Оборудование</a>
      <a href="<?= url('') ?>#faq">Вопросы</a>
      <a href="<?= url('') ?>#contacts">Контакты</a>
    </nav>
    <a class="phone" href="tel:<?= SITE_PHONE_TEL ?>"><?= e(SITE_PHONE) ?></a>
    <a class="btn btn-brass btn-sm" href="<?= url('') ?>#booking">Записаться</a>
    <button class="burger" id="burger" aria-label="Открыть меню" aria-expanded="false" aria-controls="nav"><span></span><span></span><span></span></button>
  </div>
</header>
<?php
}

function page_foot(): void { ?>
<footer class="site-footer">
  <div class="wrap foot-grid">
    <div>
      <p class="foot-brand">Магнесия</p>
      <p>Центр МРТ-диагностики в Якутске. Официальный филиал российской сети МРТ-клиник.</p>
    </div>
    <div>
      <p class="foot-h">Связаться</p>
      <p><a href="tel:<?= SITE_PHONE_TEL ?>"><?= e(SITE_PHONE) ?></a><br><a href="mailto:<?= SITE_EMAIL ?>"><?= SITE_EMAIL ?></a><br><?= e(SITE_ADDRESS) ?></p>
    </div>
    <div>
      <p class="foot-h">Документы</p>
      <p><a href="<?= url('privacy.php') ?>">Политика обработки персональных данных</a><br>
      <a href="https://yakutsk.magnesia.su/oms/" rel="noopener" target="_blank">ОМС</a><br>
      <a href="<?= url('admin/login.php') ?>">Вход для сотрудников</a></p>
    </div>
  </div>
  <div class="wrap legal">
    <p>ООО «Центр томографии Магнесия – Якутск», ИНН 1435309854, КПП 143501001. Лицензия № Л041-01179-14/00311219 от 25.01.2017. Имеются противопоказания, необходима консультация специалиста.</p>
    <p>© <?= date('Y') ?> Магнесия. Режим работы: <?= e(SITE_HOURS) ?>.</p>
  </div>
</footer>
<div class="cookie" id="cookie" hidden>
  <p>Мы используем файлы cookie, чтобы сайт работал стабильно. Продолжая, вы соглашаетесь с их использованием.</p>
  <button class="btn btn-brass btn-sm" id="cookieOk" type="button">Понятно</button>
</div>
<a class="call-fab" href="tel:<?= SITE_PHONE_TEL ?>" aria-label="Позвонить в клинику">
  <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M6.6 10.8a15 15 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z"/></svg>
</a>
<script src="<?= url('assets/js/main.js') ?>?v=1" defer></script>
</body>
</html>
<?php }
