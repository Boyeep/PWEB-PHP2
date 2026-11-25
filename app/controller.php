<?php
declare(strict_types=1);

const ROUTES = [
    '/' => ['GET'],
    '/login' => ['POST'],
    '/logout' => ['POST'],
    '/blog' => ['GET'],
    '/dashboard/admin' => ['GET'],
    '/dashboard/panitia' => ['GET'],
    '/dashboard/verifikator' => ['GET'],
    '/dashboard/siswa' => ['GET'],
    '/pengguna' => ['GET'],
    '/pengguna/tambah' => ['GET', 'POST'],
    '/pengguna/edit' => ['GET', 'POST'],
    '/pengguna/hapus' => ['GET', 'POST'],
    '/pendaftaran' => ['GET'],
    '/pendaftaran/isi' => ['GET', 'POST'],
    '/pendaftaran/detail' => ['GET', 'POST'],
    '/dokumen' => ['GET'],
];

function dispatch(PDO $db, string $path, string $method, ?array $user): string
{
    $post = $method === 'POST';
    if ($path === '/' || $path === '/login') {
        if ($user) {
            throw new Redirect(dashboardUrl($user['role']));
        }
        $email = '';
        $error = null;
        if ($post) {
            verifyCsrf();
            $email = strtolower(trim(input($_POST, 'email')));
            $password = input($_POST, 'password');
            $role = input($_POST, 'demo_role');
            if (demoMode() && isset(ROLES[$role])) {
                $email = $role . '@ruangakses.test';
                $password = DEMO_PASSWORD;
            }
            $error = login($db, substr($email, 0, 150), strlen($password) <= 72 ? $password : '');
        }
        return page('Masuk', 'login', compact('email', 'error'));
    }

    $actor = requireRole($user);
    if ($path === '/logout') {
        verifyCsrf();
        logout();
    }
    if (str_starts_with($path, '/dashboard/')) {
        requireRole($actor, substr($path, strlen('/dashboard/')));
        $role = $actor['role'];
        $counts = $role === 'admin' ? userStats($db) : ($role === 'siswa' ? [] : registrationStats($db));
        $activity = $role === 'admin' ? recentActivity($db) : [];
        $record = $role === 'siswa' ? ownRegistration($db, (int) $actor['id']) : null;
        return page('Dasbor ' . ROLES[$role], 'dashboard', compact('role', 'counts', 'activity', 'record'), $actor, 'dashboard');
    }
    if (str_starts_with($path, '/pengguna')) {
        requireRole($actor, 'admin');
        return userPage($db, $path, $post, $actor);
    }
    return admissionPage($db, $path, $post, $actor);
}

function userPage(PDO $db, string $path, bool $post, array $actor): string
{
    if ($path === '/pengguna') {
        $keyword = trim(mb_substr(input($_GET, 'q'), 0, 150));
        $role = input($_GET, 'role');
        $role = isset(ROLES[$role]) ? $role : '';
        [$rows, $total, $current, $pages] = listUsers($db, $keyword, $role, max(1, positiveNumber(input($_GET, 'page'))));
        return page('Manajemen pengguna', 'users', compact('rows', 'total', 'current', 'pages', 'keyword', 'role'), $actor, 'users');
    }

    $create = $path === '/pengguna/tambah';
    $id = $create ? null : queryId();
    $account = $create ? null : findUser($db, $id);
    if (!$create && !$account) {
        throw new HttpError(404, 'Akun tidak ditemukan.');
    }
    if ($post) {
        verifyCsrf();
    }
    if ($path === '/pengguna/hapus') {
        if ($post) {
            deleteUser($db, $actor, $id);
            flash('Akun pengguna berhasil dihapus.');
            throw new Redirect('/pengguna');
        }
        if ((int) $actor['id'] === $id || hasRegistration($db, $id)) {
            throw new HttpError(409, (int) $actor['id'] === $id
                ? 'Akun yang sedang Anda gunakan tidak dapat dihapus.'
                : 'Akun memiliki riwayat pendaftaran dan harus dipertahankan.');
        }
        return page('Hapus pengguna', 'delete-user', compact('account', 'id'), $actor, 'users');
    }

    $values = userValues($post ? $_POST : ($account ?? []));
    $errors = [];
    if ($post) {
        $password = input($_POST, 'password');
        $errors = userErrors($values, $password, $create);
        if (!$errors) {
            $errors = saveUser($db, $actor, $id, $values, $password);
        }
        if (!$errors) {
            flash($create ? 'Akun pengguna berhasil ditambahkan.' : 'Perubahan akun berhasil disimpan.');
            throw new Redirect('/pengguna');
        }
        http_response_code(422);
    }
    return page($create ? 'Tambah pengguna' : 'Edit pengguna', 'user-form', compact('create', 'values', 'errors', 'id'), $actor, 'users');
}

function admissionPage(PDO $db, string $path, bool $post, array $actor): string
{
    if ($path === '/pendaftaran') {
        requireRole($actor, 'admin', 'panitia', 'verifikator');
        $status = input($_GET, 'status', $actor['role'] === 'verifikator' ? 'menunggu' : '');
        $status = isset(STATUSES[$status]) ? $status : '';
        [$rows, $total, $current, $pages] = registrations($db, $status, max(1, positiveNumber(input($_GET, 'page'))));
        return page('Daftar pendaftaran', 'registrations', compact('rows', 'total', 'current', 'pages', 'status'), $actor, 'registrations');
    }
    if ($path === '/pendaftaran/isi') {
        requireRole($actor, 'siswa');
        $record = ownRegistration($db, (int) $actor['id']);
        if ($record && $record['status'] !== 'perlu_revisi') {
            throw new HttpError(409, 'Pendaftaran hanya dapat diubah ketika perlu revisi.');
        }
        $source = $post ? $_POST : ($record ?? []);
        $values = [];
        foreach (['nisn', 'no_hp', 'jurusan'] as $field) {
            $values[$field] = trim(input($source, $field));
        }
        $errors = [];
        if ($post) {
            verifyCsrf();
            $errors = saveRegistration($db, $actor, $values, $_FILES['dokumen'] ?? []);
            if (!$errors) {
                flash('Pendaftaran berhasil dikirim untuk diperiksa.');
                throw new Redirect(dashboardUrl('siswa'));
            }
            http_response_code(422);
        }
        return page($record ? 'Perbaiki pendaftaran' : 'Isi pendaftaran', 'registration-form', compact('record', 'values', 'errors'), $actor, 'dashboard');
    }

    $id = queryId();
    if ($path === '/dokumen') {
        requireRole($actor, 'admin', 'verifikator', 'siswa');
    } elseif ($post) {
        requireRole($actor, 'verifikator');
        verifyCsrf();
    }
    $record = registrationFor($db, $actor, $id);
    if ($path === '/dokumen') {
        return downloadDocument($db, $record);
    }

    $status = $post ? input($_POST, 'status') : '';
    $note = $post ? trim(input($_POST, 'catatan')) : '';
    $errors = [];
    if ($post) {
        $errors = reviewRegistration($db, $actor, $id, $status, $note);
        if (!$errors) {
            flash('Keputusan verifikasi berhasil disimpan.');
            throw new Redirect('/pendaftaran/detail?id=' . $id);
        }
        http_response_code(422);
    }
    return page('Detail pendaftaran', 'registration-detail', compact('record', 'status', 'note', 'errors'), $actor, $actor['role'] === 'siswa' ? 'dashboard' : 'registrations');
}

function downloadDocument(PDO $db, array $record): string
{
    $extension = DOCUMENT_TYPES[$record['dokumen_mime']] ?? null;
    if ($extension === null) {
        throw new HttpError(404, 'Dokumen belum tersedia.');
    }
    $query = $db->prepare('SELECT dokumen FROM pendaftaran WHERE id = ? AND dokumen_mime = ?');
    $query->execute([(int) $record['id'], $record['dokumen_mime']]);
    $data = $query->fetchColumn();
    if (!is_string($data) || $data === '') {
        throw new HttpError(404, 'Dokumen belum tersedia.');
    }
    header('Content-Type: ' . $record['dokumen_mime']);
    header('Content-Disposition: attachment; filename="dokumen-' . (int) $record['id'] . '.' . $extension . '"');
    return $data;
}
