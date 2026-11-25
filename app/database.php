<?php
declare(strict_types=1);

const ROLES = ['admin' => 'Admin', 'panitia' => 'Panitia', 'verifikator' => 'Verifikator', 'siswa' => 'Siswa'];
const DEMO_PASSWORD = 'RuangDemo26!';
const DEMO_LIFETIME = 7 * 86400;

function setting(string $key, string $default = ''): string
{
    static $file = null;
    $file ??= is_file(ROOT . '/.env') ? parse_ini_file(ROOT . '/.env', false, INI_SCANNER_RAW) : [];
    $value = getenv($key);
    return $value === false ? ($file[$key] ?? $default) : $value;
}

function demoMode(): bool
{
    return setting('DB_DRIVER', 'sqlite') === 'sqlite';
}

function connectDatabase(string $sqlitePath = ''): PDO
{
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    if (!demoMode()) {
        if (setting('DB_DRIVER') !== 'mysql') {
            throw new RuntimeException('Driver database tidak dikenal.');
        }
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', setting('DB_HOST', '127.0.0.1'), setting('DB_PORT', '3306'), setting('DB_DATABASE', 'ruangakses'));
        $db = new PDO($dsn, setting('DB_USERNAME', 'root'), setting('DB_PASSWORD'), $options + [PDO::ATTR_EMULATE_PREPARES => false]);
        $db->exec("SET time_zone = '+00:00'");
        return $db;
    }

    $db = new PDO('sqlite:' . $sqlitePath, null, null, $options);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA busy_timeout = 3000');
    if ((int) $db->query('PRAGMA user_version')->fetchColumn() === 0) {
        transaction($db, function () use ($db): void {
            if ((int) $db->query('PRAGMA user_version')->fetchColumn() === 0) {
                $db->exec(file_get_contents(ROOT . '/database/sqlite.sql'));
                seedDemo($db);
                $db->exec('PRAGMA user_version = 1');
            }
        });
    }
    return $db;
}

function transaction(PDO $db, callable $action): mixed
{
    // Serialize writes that must check account and registration state together.
    $sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    if ($sqlite) {
        $db->exec('BEGIN IMMEDIATE');
    } else {
        $db->beginTransaction();
    }
    try {
        if (!$sqlite) {
            $db->query('SELECT id FROM app_lock WHERE id = 1 FOR UPDATE')->fetchColumn();
        }
        $result = $action();
        $sqlite ? $db->exec('COMMIT') : $db->commit();
        return $result;
    } catch (Throwable $error) {
        if ($sqlite) {
            $db->exec('ROLLBACK');
        } elseif ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function seedDemo(PDO $db): void
{
    $accounts = [
        ['Nadia Prameswari', 'admin', 'admin'],
        ['Raka Pratama', 'panitia', 'panitia'],
        ['Dewi Anggraini', 'verifikator', 'verifikator'],
        ['Alya Ramadhani', 'siswa', 'siswa'],
        ['Bagas Mahendra', 'bagas', 'siswa'],
        ['Citra Lestari', 'citra', 'siswa'],
    ];
    $insert = $db->prepare('INSERT INTO users (nama_lengkap, email, role, password_hash) VALUES (?, ?, ?, ?)');
    $profile = $db->prepare('INSERT INTO siswa (user_id, nisn, no_hp) VALUES (?, ?, ?)');
    $registration = $db->prepare('INSERT INTO pendaftaran (user_id, jurusan, status, catatan, dokumen, dokumen_mime) VALUES (?, ?, ?, ?, ?, ?)');
    $statuses = ['menunggu', 'terverifikasi', 'perlu_revisi'];
    $programs = ['Rekayasa Perangkat Lunak', 'Teknik Komputer dan Jaringan', 'Desain Komunikasi Visual'];
    foreach ($accounts as $index => [$name, $alias, $role]) {
        $insert->execute([$name, $alias . '@ruangakses.test', $role, password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT)]);
        if ($role !== 'siswa') {
            continue;
        }
        $id = (int) $db->lastInsertId();
        $number = $index - 3;
        $profile->execute([$id, '000123456' . $number, '08123456780' . $number]);
        $note = $number === 2 ? 'Unggah ulang berkas dengan teks yang lebih jelas.' : '';
        $document = file_get_contents(ROOT . '/database/contoh-dokumen.png');
        $registration->bindValue(1, $id, PDO::PARAM_INT);
        $registration->bindValue(2, $programs[$number]);
        $registration->bindValue(3, $statuses[$number]);
        $registration->bindValue(4, $note);
        $registration->bindValue(5, $document, PDO::PARAM_LOB);
        $registration->bindValue(6, 'image/png');
        $registration->execute();
    }
}
