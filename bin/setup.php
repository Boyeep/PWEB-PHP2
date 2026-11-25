<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (demoMode() || !in_array($argv[1] ?? '', ['admin', 'demo'], true)) {
    fwrite(STDERR, "Atur DB_DRIVER=mysql dan jalankan php bin/setup.php admin atau demo.\n");
    exit(1);
}

try {
    $db = connectDatabase();
    transaction($db, function () use ($db, $argv): void {
        if ((int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) {
            throw new RuntimeException('Tabel users sudah terisi.');
        }
        if ($argv[1] === 'demo') {
            seedDemo($db);
            return;
        }
        $email = strtolower(trim($argv[2] ?? ''));
        $password = getenv('INITIAL_ADMIN_PASSWORD') ?: '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150 || !validPassword($password)) {
            throw new RuntimeException('Sertakan email dan set INITIAL_ADMIN_PASSWORD dengan minimal 10 karakter dan maksimal 72 byte.');
        }
        $query = $db->prepare("INSERT INTO users (nama_lengkap, email, password_hash, role) VALUES ('Administrator', ?, ?, 'admin')");
        $query->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
    });
    echo "Akun awal berhasil dibuat.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
