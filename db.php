<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!extension_loaded('pdo_sqlite')) {
        http_response_code(500);
        exit('Не включено расширение pdo_sqlite. OpenServer → Настройки → Модули → PHP-8.3 → включите pdo_sqlite и sqlite3, затем перезапустите сервер.');
    }
    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON;');
    migrate($pdo);
    if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) seed($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        login TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        full_name TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('admin','operator','doctor')),
        speciality TEXT DEFAULT '',
        must_change INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    );
    CREATE TABLE IF NOT EXISTS appointments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        phone TEXT NOT NULL,
        service TEXT NOT NULL,
        preferred_date TEXT NOT NULL,
        preferred_time TEXT NOT NULL,
        comment TEXT DEFAULT '',
        status TEXT NOT NULL DEFAULT 'new',
        doctor_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
        staff_note TEXT DEFAULT '',
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        ip TEXT DEFAULT ''
    );
    CREATE TABLE IF NOT EXISTS attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kind TEXT NOT NULL, ip TEXT NOT NULL, ts INTEGER NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_att ON attempts(kind, ip, ts);
    CREATE INDEX IF NOT EXISTS idx_app_doc ON appointments(doctor_id);
    ");
}

/** Начальные пользователи. Пароли временные: при первом входе система потребует их сменить. */
function seed(PDO $pdo): void {
    $users = [
        ['admin',     'Admin#2026',    'Администратор центра',             'admin',    ''],
        ['operator',  'Operator#2026', 'Оператор',                         'operator', ''],
        ['dyagileva', 'Doctor#2026',   'Дягилева Наталья Евгеньевна',      'doctor',   'Врач-рентгенолог'],
        ['atlasov',   'Doctor#2026',   'Атласов Андрей Андреевич',         'doctor',   'Врач-рентгенолог'],
        ['glebov',    'Doctor#2026',   'Глебов Антон Сергеевич',           'doctor',   'Врач-рентгенолог'],
        ['timofeev',  'Doctor#2026',   'Тимофеев Григорий Константинович', 'doctor',   'Врач-рентгенолог'],
        ['pevzner',   'Doctor#2026',   'Певзнер Светлана Евгеньевна',      'doctor',   'Врач-рентгенолог'],
    ];
    $st = $pdo->prepare('INSERT INTO users(login,password_hash,full_name,role,speciality,must_change) VALUES(?,?,?,?,?,1)');
    foreach ($users as $u) $st->execute([$u[0], password_hash($u[1], PASSWORD_DEFAULT), $u[2], $u[3], $u[4]]);
}

/** Ограничение частоты: не более $max событий за $window секунд с одного IP. */
function rate_limited(string $kind, int $max, int $window): bool {
    $pdo = db();
    $pdo->prepare('DELETE FROM attempts WHERE ts < ?')->execute([time() - 86400]);
    $st = $pdo->prepare('SELECT COUNT(*) FROM attempts WHERE kind=? AND ip=? AND ts>?');
    $st->execute([$kind, client_ip(), time() - $window]);
    return (int)$st->fetchColumn() >= $max;
}
function rate_hit(string $kind): void {
    db()->prepare('INSERT INTO attempts(kind,ip,ts) VALUES(?,?,?)')->execute([$kind, client_ip(), time()]);
}
