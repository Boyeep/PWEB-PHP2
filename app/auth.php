<?php
declare(strict_types=1);

const SESSION_LIFETIME = 8 * 3600;

final class DatabaseSession implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private array $loaded = [];

    public function __construct(private PDO $db) {}

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function validateId(string $id): bool
    {
        $query = $this->db->prepare('SELECT 1 FROM app_sessions WHERE id_hash = ? AND expires_at > ?');
        $query->execute([hash('sha256', $id), time()]);
        return $query->fetchColumn() !== false;
    }

    public function read(string $id): string
    {
        $query = $this->db->prepare('SELECT payload FROM app_sessions WHERE id_hash = ? AND expires_at > ?');
        $query->execute([hash('sha256', $id), time()]);
        $data = $query->fetchColumn();
        if ($data !== false) {
            $this->loaded[$id] = true;
        }
        return $data === false ? '' : (string) $data;
    }

    public function write(string $id, string $data): bool
    {
        if ($data === '') {
            return $this->destroy($id);
        }
        $sql = isset($this->loaded[$id])
            ? 'UPDATE app_sessions SET payload = ?, expires_at = ? WHERE id_hash = ?'
            : 'INSERT INTO app_sessions (payload, expires_at, id_hash) VALUES (?, ?, ?)';
        return $this->db->prepare($sql)->execute([$data, time() + SESSION_LIFETIME, hash('sha256', $id)]);
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        // A read-only request leaves the expiry and database snapshot unchanged.
        return true;
    }

    public function destroy(string $id): bool
    {
        $query = $this->db->prepare('DELETE FROM app_sessions WHERE id_hash = ?');
        return $query->execute([hash('sha256', $id)]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $query = $this->db->prepare('DELETE FROM app_sessions WHERE expires_at <= ?');
        $query->execute([time()]);
        return $query->rowCount();
    }
}

function startSession(PDO $db): void
{
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_probability', '0');
    session_name('ruangakses_session');
    session_set_cookie_params(cookieOptions() + ['lifetime' => 0]);
    session_cache_limiter('');
    session_set_save_handler(new DatabaseSession($db), false);
    if (!session_start()) {
        throw new RuntimeException('Sesi gagal dimulai.');
    }
}

function currentUser(PDO $db): ?array
{
    if (!isset($_SESSION['user_id'], $_SESSION['auth_version'], $_SESSION['login_time'])) {
        return null;
    }
    $user = findUser($db, (int) $_SESSION['user_id']);
    if (!$user || (int) $user['auth_version'] !== $_SESSION['auth_version'] || time() - $_SESSION['login_time'] >= SESSION_LIFETIME) {
        unset($_SESSION['user_id'], $_SESSION['auth_version'], $_SESSION['login_time']);
        return null;
    }
    return $user;
}

function requireRole(?array $user, string ...$roles): array
{
    if ($user === null) {
        throw new Redirect('/');
    }
    if ($roles && !in_array($user['role'], $roles, true)) {
        throw new HttpError(403, 'Halaman ini berada di luar hak akses akun Anda.');
    }
    return $user;
}

function login(PDO $db, string $email, string $password): ?string
{
    $now = time();
    $attempts = array_filter($_SESSION['attempts'] ?? [], static fn (int $time): bool => $now - $time < 300);
    if (count($attempts) >= 8) {
        http_response_code(429);
        return 'Terlalu banyak percobaan. Tunggu lima menit lalu coba lagi.';
    }
    $query = $db->prepare('SELECT id, role, password_hash, auth_version FROM users WHERE email = ?');
    $query->execute([$email]);
    $account = $query->fetch();
    $fallback = '$2y$10$Ce1olES9dHMRKJfnqBg3XOsgu.DckbUIa9cMifRNHjmVg8FfEoFDK';
    $verified = password_verify($password, $account['password_hash'] ?? $fallback);
    if (!$account || !$verified) {
        $attempts[] = $now;
        $_SESSION['attempts'] = $attempts;
        http_response_code(422);
        return 'Email atau kata sandi salah. Periksa isian Anda.';
    }

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('ID sesi gagal diperbarui.');
    }
    $_SESSION = [
        'user_id' => (int) $account['id'],
        'auth_version' => (int) $account['auth_version'],
        'login_time' => $now,
    ];
    if (random_int(1, 20) === 1) {
        (new DatabaseSession($db))->gc(SESSION_LIFETIME);
    }
    throw new Redirect(dashboardUrl($account['role']));
}

function logout(): never
{
    $_SESSION = [];
    if (!session_destroy()) {
        throw new RuntimeException('Sesi gagal diakhiri.');
    }
    setcookie(session_name(), '', cookieOptions() + ['expires' => 1]);
    throw new Redirect('/');
}

function csrfToken(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function verifyCsrf(): void
{
    $token = $_SESSION['csrf'] ?? '';
    if ($token === '' || !hash_equals($token, input($_POST, 'csrf'))) {
        throw new HttpError(403, 'Formulir sudah kedaluwarsa. Buka kembali halaman lalu coba lagi.');
    }
}
