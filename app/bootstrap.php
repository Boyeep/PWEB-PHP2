<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
ini_set('display_errors', '0');
date_default_timezone_set('UTC');

final class HttpError extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}

final class Redirect extends RuntimeException
{
    public function __construct(public readonly string $location)
    {
        parent::__construct();
    }
}

require ROOT . '/app/database.php';
require ROOT . '/app/workspace.php';
require ROOT . '/app/auth.php';
require ROOT . '/app/users.php';
require ROOT . '/app/admissions.php';
require ROOT . '/app/view.php';

function cookieOptions(): array
{
    return [
        'path' => '/',
        'secure' => setting('VERCEL') === '1' || in_array(strtolower($_SERVER['HTTPS'] ?? ''), ['on', '1'], true),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function input(array $values, string $key, string $default = ''): string
{
    $value = $values[$key] ?? null;
    return is_string($value) && mb_check_encoding($value, 'UTF-8') ? $value : $default;
}

function queryId(): int
{
    $id = positiveNumber(input($_GET, 'id'));
    if ($id === 0) {
        throw new HttpError(404, 'Data tidak ditemukan.');
    }
    return $id;
}

function positiveNumber(string $value): int
{
    return preg_match('/\A[1-9][0-9]{0,9}\z/', $value) ? (int) $value : 0;
}

function dashboardUrl(string $role): string
{
    return '/dashboard/' . $role;
}

function flash(string $message): void
{
    $_SESSION['flash'] = $message;
}
