<?php
declare(strict_types=1);

function h(string|int $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function page(string $title, string $template, array $data = [], ?array $actor = null, string $active = ''): string
{
    extract($data, EXTR_SKIP);
    $message = $_SESSION['flash'] ?? null;
    if (session_status() === PHP_SESSION_ACTIVE && $message !== null) {
        unset($_SESSION['flash']);
    }
    ob_start();
    try {
        require ROOT . '/views/layout.php';
        return ob_get_clean();
    } catch (Throwable $error) {
        ob_end_clean();
        throw $error;
    }
}

function errorPage(int $status, string $description, ?array $actor = null): string
{
    http_response_code($status);
    $titles = [403 => 'Akses ditolak', 404 => 'Halaman tidak ditemukan', 405 => 'Metode tidak tersedia', 409 => 'Perubahan tidak dapat dilakukan', 503 => 'Layanan belum tersedia'];
    $title = $titles[$status] ?? 'Permintaan gagal';
    return page($title, 'error', compact('status', 'description'), $actor);
}

function icon(string $key, int $size = 20): string
{
    $paths = [
        'shield' => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/><circle cx="9" cy="7" r="4"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Zm0 0v6h6M8 13h8m-8 4h6"/>',
        'book' => '<path d="M4 19V5a2 2 0 0 1 2-2h13v18H6a2 2 0 0 1 0-4h13M8 7h7m-7 4h5"/>',
        'logout' => '<path d="M10 17l5-5-5-5m5 5H3m9-9h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/>',
        'arrow' => '<path d="M4 12h15m-6-6 6 6-6 6"/>',
        'back' => '<path d="M20 12H5m6-6-6 6 6 6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'search' => '<circle cx="11" cy="11" r="6"/><path d="m16 16 4 4"/>',
        'user' => '<circle cx="12" cy="7" r="4"/><path d="M4 21v-2a5 5 0 0 1 5-5h6a5 5 0 0 1 5 5v2"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'edit' => '<path d="m16 4 4 4-10 10-5 1 1-5Zm-2 2 4 4"/>',
        'download' => '<path d="M12 3v12m-5-5 5 5 5-5M4 17v4h16v-4"/>',
    ];
    return '<svg aria-hidden="true" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' . $paths[$key] . '</svg>';
}

function roleIcon(string $role): string
{
    return ['admin' => 'shield', 'panitia' => 'users', 'verifikator' => 'check', 'siswa' => 'user'][$role];
}

function initials(string $name): string
{
    $words = preg_split('/\s+/u', trim($name), 3);
    return mb_strtoupper(implode('', array_map(static fn (string $word): string => mb_substr($word, 0, 1), array_slice($words, 0, 2))));
}

function dateLabel(string $value): string
{
    $time = (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'));
    $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return $time->format('j') . ' ' . $months[(int) $time->format('n')] . ' ' . $time->format('Y');
}

function csrfField(): void
{
    echo '<input type="hidden" name="csrf" value="' . h(csrfToken()) . '">';
}

function fieldError(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<p class="field-error" id="error-' . h($key) . '">' . h($errors[$key]) . '</p>' : '';
}

function invalid(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="error-' . h($key) . '"' : '';
}

function roleBadge(string $role): string
{
    return '<span class="pill pill-' . h($role) . '">' . h(ROLES[$role]) . '</span>';
}

function statusBadge(string $status): string
{
    return '<span class="pill pill-' . h($status) . '">' . h(STATUSES[$status]) . '</span>';
}

function pager(string $path, array $params, int $current, int $pages): void
{
    if ($pages <= 1) {
        return;
    }
    $link = static fn (int $page): string => h($path . '?' . http_build_query($params + ['page' => $page]));
    ?>
    <nav class="pager" aria-label="Halaman hasil">
        <?php if ($current > 1): ?><a class="btn btn-outline" href="<?= $link($current - 1) ?>">Sebelumnya</a><?php endif; ?>
        <span>Halaman <?= $current ?> dari <?= $pages ?></span>
        <?php if ($current < $pages): ?><a class="btn btn-outline" href="<?= $link($current + 1) ?>">Berikutnya</a><?php endif; ?>
    </nav>
    <?php
}
