<?php
declare(strict_types=1);

function findUser(PDO $db, int $id): ?array
{
    $query = $db->prepare('SELECT id, nama_lengkap, email, role, auth_version, created_at FROM users WHERE id = ?');
    $query->execute([$id]);
    return $query->fetch() ?: null;
}

function userStats(PDO $db): array
{
    return $db->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role')->fetchAll(PDO::FETCH_KEY_PAIR) + array_fill_keys(array_keys(ROLES), 0);
}

function listUsers(PDO $db, string $keyword, string $role, int $page): array
{
    $conditions = [];
    $params = [];
    if ($keyword !== '') {
        $pattern = '%' . strtr($keyword, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $condition = "(LOWER(nama_lengkap) LIKE LOWER(?) ESCAPE '!' OR LOWER(email) LIKE LOWER(?) ESCAPE '!'";
        $params = [$pattern, $pattern];
        if (ctype_digit($keyword) && strlen($keyword) <= 10) {
            $condition .= ' OR id = ?';
            $params[] = (int) $keyword;
        }
        $conditions[] = $condition . ')';
    }
    if (isset(ROLES[$role])) {
        $conditions[] = 'role = ?';
        $params[] = $role;
    }
    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    $count = $db->prepare('SELECT COUNT(*) FROM users' . $where);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / 12));
    $page = min($page, $pages);
    $offset = ($page - 1) * 12;
    $query = $db->prepare('SELECT id, nama_lengkap, email, role, created_at FROM users' . $where . ' ORDER BY id DESC LIMIT 12 OFFSET ' . $offset);
    $query->execute($params);
    return [$query->fetchAll(), $total, $page, $pages];
}

function userValues(array $source): array
{
    return [
        'nama_lengkap' => trim(input($source, 'nama_lengkap')),
        'email' => strtolower(trim(input($source, 'email'))),
        'role' => input($source, 'role', 'siswa'),
    ];
}

function userErrors(array $values, string $password, bool $create): array
{
    $errors = [];
    $length = mb_strlen($values['nama_lengkap']);
    if ($length < 2 || $length > 150 || preg_match('/[\x00-\x1f\x7f]/', $values['nama_lengkap'])) {
        $errors['nama_lengkap'] = 'Masukkan nama dengan 2 sampai 150 karakter.';
    }
    if (strlen($values['email']) > 150 || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Masukkan alamat email yang valid.';
    }
    if (!isset(ROLES[$values['role']])) {
        $errors['role'] = 'Pilih salah satu peran yang tersedia.';
    }
    if (($create || $password !== '') && !validPassword($password)) {
        $errors['password'] = 'Gunakan minimal 10 karakter dan maksimal 72 byte.';
    }
    return $errors;
}

function validPassword(string $password): bool
{
    return mb_strlen($password) >= 10 && strlen($password) <= 72 && !str_contains($password, "\0");
}

function hasRegistration(PDO $db, int $id): bool
{
    $query = $db->prepare('SELECT 1 FROM pendaftaran WHERE user_id = ?');
    $query->execute([$id]);
    return $query->fetchColumn() !== false;
}

function checkActor(PDO $db, array $actor): void
{
    $fresh = findUser($db, (int) $actor['id']);
    if (!$fresh || $fresh['role'] !== $actor['role'] || (int) $fresh['auth_version'] !== (int) $actor['auth_version']) {
        throw new HttpError(403, 'Hak akses akun telah berubah. Silakan masuk kembali.');
    }
}

function saveUser(PDO $db, array $actor, ?int $id, array $values, string $password): array
{
    return transaction($db, function () use ($db, $actor, $id, $values, $password): array {
        checkActor($db, $actor);
        $account = $id === null ? null : findUser($db, $id);
        if ($id !== null && !$account) {
            throw new HttpError(404, 'Akun tidak ditemukan.');
        }
        if ($account && $values['role'] !== $account['role']) {
            if ((int) $actor['id'] === $id || ($account['role'] === 'admin' && userStats($db)['admin'] <= 1)) {
                return ['role' => 'Peran akun sendiri atau admin terakhir tidak dapat diubah.'];
            }
            if (hasRegistration($db, $id)) {
                return ['role' => 'Peran siswa dengan riwayat pendaftaran tidak dapat diubah.'];
            }
        }
        if (!$account && demoMode() && (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() >= 30) {
            return ['form' => 'Satu ruang demo dapat menampung maksimal 30 pengguna.'];
        }
        $emailCheck = $db->prepare('SELECT id FROM users WHERE email = ?');
        $emailCheck->execute([$values['email']]);
        $owner = $emailCheck->fetchColumn();
        if ($owner !== false && (int) $owner !== $id) {
            return ['email' => 'Email ini sudah digunakan oleh akun lain.'];
        }
        $params = [$values['nama_lengkap'], $values['email'], $values['role']];
        if ($id === null) {
            $query = $db->prepare('INSERT INTO users (nama_lengkap, email, role, password_hash) VALUES (?, ?, ?, ?)');
            $query->execute([...$params, password_hash($password, PASSWORD_DEFAULT)]);
            logActivity($db, (int) $actor['id'], 'Menambahkan akun #' . $db->lastInsertId());
            return [];
        }

        $sql = 'UPDATE users SET nama_lengkap = ?, email = ?, role = ?, updated_at = CURRENT_TIMESTAMP';
        if ($password !== '') {
            $sql .= ', password_hash = ?';
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        if ($password !== '' || $values['role'] !== $account['role'] || $values['email'] !== $account['email']) {
            $sql .= ', auth_version = auth_version + 1';
        }
        $query = $db->prepare($sql . ' WHERE id = ?');
        $query->execute([...$params, $id]);
        logActivity($db, (int) $actor['id'], 'Memperbarui akun #' . $id);
        return [];
    });
}

function deleteUser(PDO $db, array $actor, int $id): void
{
    transaction($db, function () use ($db, $actor, $id): void {
        checkActor($db, $actor);
        $account = findUser($db, $id);
        if (!$account) {
            throw new HttpError(404, 'Akun tidak ditemukan.');
        }
        if ((int) $actor['id'] === $id) {
            throw new HttpError(409, 'Akun yang sedang Anda gunakan tidak dapat dihapus.');
        }
        if ($account['role'] === 'admin' && userStats($db)['admin'] <= 1) {
            throw new HttpError(409, 'Admin terakhir tidak dapat dihapus.');
        }
        if (hasRegistration($db, $id)) {
            throw new HttpError(409, 'Akun memiliki riwayat pendaftaran dan harus dipertahankan.');
        }
        $query = $db->prepare('DELETE FROM users WHERE id = ?');
        $query->execute([$id]);
        logActivity($db, (int) $actor['id'], 'Menghapus akun #' . $id);
    });
}

function logActivity(PDO $db, int $actor, string $activity): void
{
    $query = $db->prepare('INSERT INTO activity_logs (user_id, aktivitas) VALUES (?, ?)');
    $query->execute([$actor, $activity]);
    if (demoMode()) {
        $db->exec('DELETE FROM activity_logs WHERE id < (SELECT MAX(id) - 99 FROM activity_logs)');
    }
}

function recentActivity(PDO $db): array
{
    return $db->query('SELECT logs.aktivitas, logs.created_at, users.nama_lengkap FROM activity_logs logs LEFT JOIN users ON users.id = logs.user_id ORDER BY logs.id DESC LIMIT 5')->fetchAll();
}
