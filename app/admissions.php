<?php
declare(strict_types=1);

const PROGRAMS = ['Rekayasa Perangkat Lunak', 'Teknik Komputer dan Jaringan', 'Desain Komunikasi Visual'];
const STATUSES = ['menunggu' => 'Menunggu', 'terverifikasi' => 'Terverifikasi', 'perlu_revisi' => 'Perlu revisi'];
const DOCUMENT_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
const MAX_DOCUMENT_BYTES = 1048576;

function registrationStats(PDO $db): array
{
    return $db->query('SELECT status, COUNT(*) FROM pendaftaran GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR) + array_fill_keys(array_keys(STATUSES), 0);
}

function registrations(PDO $db, string $status, int $page): array
{
    $where = isset(STATUSES[$status]) ? ' WHERE p.status = ?' : '';
    $params = $where === '' ? [] : [$status];
    $count = $db->prepare('SELECT COUNT(*) FROM pendaftaran p' . $where);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / 12));
    $page = min($page, $pages);
    $offset = ($page - 1) * 12;
    $query = $db->prepare('SELECT p.id, p.jurusan, p.status, p.updated_at, u.nama_lengkap FROM pendaftaran p JOIN users u ON u.id = p.user_id' . $where . ' ORDER BY p.updated_at DESC, p.id DESC LIMIT 12 OFFSET ' . $offset);
    $query->execute($params);
    return [$query->fetchAll(), $total, $page, $pages];
}

function findRegistration(PDO $db, int $id): ?array
{
    $query = $db->prepare('SELECT p.id, p.user_id, p.jurusan, p.status, p.catatan, p.dokumen_mime, p.updated_at, u.nama_lengkap, s.nisn, s.no_hp
        FROM pendaftaran p JOIN users u ON u.id = p.user_id JOIN siswa s ON s.user_id = p.user_id WHERE p.id = ?');
    $query->execute([$id]);
    return $query->fetch() ?: null;
}

function ownRegistration(PDO $db, int $userId): ?array
{
    $query = $db->prepare('SELECT id FROM pendaftaran WHERE user_id = ?');
    $query->execute([$userId]);
    $id = $query->fetchColumn();
    return $id === false ? null : findRegistration($db, (int) $id);
}

function registrationFor(PDO $db, array $actor, int $id): array
{
    $record = findRegistration($db, $id);
    if (!$record || ($actor['role'] === 'siswa' && (int) $record['user_id'] !== (int) $actor['id'])) {
        throw new HttpError(404, 'Pendaftaran tidak ditemukan.');
    }
    return $record;
}

function documentUpload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null, null];
    }
    $path = $file['tmp_name'] ?? '';
    if ($file['error'] !== UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path)) {
        return [null, null, 'Berkas gagal diunggah. Pilih PDF, JPG, atau PNG hingga 1 MB.'];
    }
    $size = filesize($path);
    if ($size === false || $size === 0 || $size > MAX_DOCUMENT_BYTES) {
        return [null, null, 'Ukuran dokumen harus di antara 1 byte dan 1 MB.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $data = file_get_contents($path);
    $imageTypes = ['image/jpeg' => IMAGETYPE_JPEG, 'image/png' => IMAGETYPE_PNG];
    if (!isset(DOCUMENT_TYPES[$mime]) || $data === false
        || ($mime === 'application/pdf' && !str_starts_with($data, '%PDF-'))
        || (isset($imageTypes[$mime]) && (@getimagesize($path)[2] ?? null) !== $imageTypes[$mime])) {
        return [null, null, 'Isi dokumen harus berupa PDF, JPG, atau PNG yang valid.'];
    }
    return [$data, $mime, null];
}

function saveRegistration(PDO $db, array $actor, array $form, array $file): array
{
    $errors = [];
    if (!preg_match('/\A[0-9]{10}\z/', $form['nisn'])) {
        $errors['nisn'] = 'NISN harus berisi 10 digit.';
    }
    if (!preg_match('/\A\+?[0-9]{8,15}\z/', $form['no_hp'])) {
        $errors['no_hp'] = 'Masukkan 8 sampai 15 digit. Awalan + diperbolehkan.';
    }
    if (!in_array($form['jurusan'], PROGRAMS, true)) {
        $errors['jurusan'] = 'Pilih jurusan yang tersedia.';
    }
    [$data, $mime, $fileError] = documentUpload($file);
    if ($fileError !== null) {
        $errors['dokumen'] = $fileError;
    }
    if ($errors) {
        return $errors;
    }

    return transaction($db, function () use ($db, $actor, $form, $data, $mime): array {
        checkActor($db, $actor);
        $id = (int) $actor['id'];
        $existing = ownRegistration($db, $id);
        if ($existing && $existing['status'] !== 'perlu_revisi') {
            throw new HttpError(409, 'Pendaftaran hanya dapat diubah ketika perlu revisi.');
        }
        if ($data === null) {
            return ['dokumen' => 'Pilih dokumen untuk mengirim pendaftaran.'];
        }
        if (demoMode()) {
            $sizeQuery = $db->prepare('SELECT COALESCE(SUM(LENGTH(dokumen)), 0) FROM pendaftaran WHERE user_id <> ?');
            $sizeQuery->execute([$id]);
            if ((int) $sizeQuery->fetchColumn() + strlen($data) > 4 * MAX_DOCUMENT_BYTES) {
                return ['dokumen' => 'Penyimpanan dokumen pada ruang demo sudah penuh. Pilih berkas yang lebih kecil.'];
            }
        }
        $duplicate = $db->prepare('SELECT 1 FROM siswa WHERE nisn = ? AND user_id <> ?');
        $duplicate->execute([$form['nisn'], $id]);
        if ($duplicate->fetchColumn() !== false) {
            return ['nisn' => 'NISN ini sudah digunakan pada pendaftaran lain.'];
        }
        if ($existing) {
            $profile = $db->prepare('UPDATE siswa SET nisn = ?, no_hp = ? WHERE user_id = ?');
            $record = $db->prepare("UPDATE pendaftaran SET jurusan = ?, dokumen = ?, dokumen_mime = ?, status = 'menunggu', catatan = '', updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
        } else {
            $profile = $db->prepare('INSERT INTO siswa (nisn, no_hp, user_id) VALUES (?, ?, ?)');
            $record = $db->prepare('INSERT INTO pendaftaran (jurusan, dokumen, dokumen_mime, user_id) VALUES (?, ?, ?, ?)');
        }
        $profile->execute([$form['nisn'], $form['no_hp'], $id]);
        $record->bindValue(1, $form['jurusan']);
        $record->bindValue(2, $data, PDO::PARAM_LOB);
        $record->bindValue(3, $mime);
        $record->bindValue(4, $id, PDO::PARAM_INT);
        $record->execute();
        return [];
    });
}

function reviewRegistration(PDO $db, array $actor, int $id, string $status, string $note): array
{
    $errors = [];
    if (!in_array($status, ['terverifikasi', 'perlu_revisi'], true)) {
        $errors['status'] = 'Pilih keputusan verifikasi.';
    }
    if (($status === 'perlu_revisi' && $note === '') || mb_strlen($note) > 250) {
        $errors['catatan'] = 'Tuliskan catatan revisi dengan maksimal 250 karakter.';
    }
    if ($errors) {
        return $errors;
    }

    transaction($db, function () use ($db, $actor, $id, $status, $note): void {
        checkActor($db, $actor);
        $record = registrationFor($db, $actor, $id);
        if ($record['status'] !== 'menunggu') {
            throw new HttpError(409, 'Pendaftaran ini sudah diperiksa.');
        }
        $query = $db->prepare('UPDATE pendaftaran SET status = ?, catatan = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $query->execute([$status, $note, $id]);
        logActivity($db, (int) $actor['id'], 'Memeriksa pendaftaran #' . $id);
    });
    return [];
}
