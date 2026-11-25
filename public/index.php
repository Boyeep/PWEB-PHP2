<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/controller.php';

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://cdn.jsdelivr.net; img-src 'self' data: https://raw.githubusercontent.com; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('Content-Type: text/html; charset=utf-8');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = $path === '/index.php' ? '/' : $path;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$workspace = $user = $redirect = null;

try {
    try {
        if (!is_string($path) || !isset(ROUTES[$path])) {
            throw new HttpError(404, 'Halaman yang Anda cari tidak ditemukan.');
        }
        if (!in_array($method, ROUTES[$path], true)) {
            header('Allow: ' . implode(', ', ROUTES[$path]));
            throw new HttpError(405, 'Metode permintaan tidak didukung.');
        }
        if ($path === '/blog') {
            $body = page('Catatan proyek', 'blog');
        } else {
            $workspace = demoMode() ? new Workspace() : null;
            $db = connectDatabase($workspace?->path() ?? '');
            startSession($db);
            $user = currentUser($db);
            $body = dispatch($db, $path, $method, $user);
        }
    } catch (Redirect $response) {
        $redirect = $response->location;
        $body = '';
    } catch (HttpError $error) {
        $body = errorPage($error->status, $error->getMessage(), $user);
    }

    if (session_status() === PHP_SESSION_ACTIVE && !session_write_close()) {
        throw new RuntimeException('Sesi gagal disimpan.');
    }
    $workspace?->save();
    if ($redirect !== null) {
        header('Location: ' . $redirect, true, 303);
    }
} catch (Throwable $error) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_abort();
    }
    header_remove('Set-Cookie');
    header_remove('Content-Disposition');
    header('Content-Type: text/html; charset=utf-8');
    if ($error instanceof HttpError) {
        $body = errorPage($error->status, $error->getMessage());
    } else {
        error_log($error::class . ': ' . $error->getMessage());
        $body = errorPage(503, 'Layanan sementara tidak tersedia. Muat ulang halaman untuk mencoba lagi.');
    }
} finally {
    unset($db);
    $workspace?->close();
}

echo $body;
