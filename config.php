<?php
declare(strict_types=1);

// ---------- Настройки сайта ----------
const SITE_NAME   = 'Магнесия — центр МРТ-диагностики';
const SITE_PHONE  = '8 (4112) 31-81-21';
const SITE_PHONE_TEL = '+74112318121';
const SITE_EMAIL  = 'info@magnesia.su';
const SITE_ADDRESS = 'г. Якутск, ул. Петровского, 19/5';
const SITE_HOURS  = 'Ежедневно с 7:00 до 23:00';
const WHATSAPP_URL = 'https://api.whatsapp.com/send?phone=79581117818';
const TELEGRAM_URL = 'https://t.me/MYMRTBOT';
const MAX_URL      = 'https://max.ru/u/f9LHodD0cOJ6DD9RxzguU-RKts3JUtbYs1ivFLq2T1ZOTkHKmNkZuqX2Kj4';
// Координаты точки на карте (долгота, широта). При необходимости уточните в Яндекс.Картах.
const MAP_LON = 129.720284;
const MAP_LAT = 62.023841;

define('DB_PATH', __DIR__ . '/../data/clinic.sqlite');

date_default_timezone_set('Asia/Yakutsk');

/** Путь сайта относительно корня веб-сервера (например "/magnesia" или ""). */
function site_root(): string {
    $doc  = rtrim(str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $self = str_replace('\\', '/', (string)realpath(dirname(__DIR__)));
    if ($doc === '' || strncasecmp($self, $doc, strlen($doc)) !== 0) return '';
    return rtrim(substr($self, strlen($doc)), '/');
}
function url(string $path = ''): string { return site_root() . '/' . ltrim($path, '/'); }
function base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . site_root();
}
