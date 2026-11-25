<?php
declare(strict_types=1);

final class Workspace
{
    private string $path;
    private string $token;
    private string $object;
    private ?string $etag = null;
    private ?string $originalHash = null;

    public function __construct()
    {
        $cookie = input($_COOKIE, 'ruangakses_workspace');
        $new = !preg_match('/\A[a-f0-9]{64}\z/', $cookie);
        $id = $new ? bin2hex(random_bytes(32)) : $cookie;
        $this->object = 'ruangakses/workspaces/' . hash('sha256', $id) . '.sqlite';
        $this->token = setting('BLOB_READ_WRITE_TOKEN');
        if ($this->token === '' && setting('VERCEL') === '1') {
            throw new RuntimeException('Penyimpanan demo belum terhubung.');
        }

        if ($this->token === '') {
            $folder = ROOT . '/storage';
            if (!is_dir($folder) && !@mkdir($folder, 0700) && !is_dir($folder)) {
                throw new RuntimeException('Folder penyimpanan tidak tersedia.');
            }
            $this->path = $folder . '/' . basename($this->object);
        } else {
            $store = strtolower(setting('BLOB_STORE_ID'));
            if (!preg_match('/\A[a-z0-9]+\z/', $store)) {
                throw new RuntimeException('Konfigurasi Blob tidak tersedia.');
            }
            $path = tempnam(sys_get_temp_dir(), 'ruangakses-');
            if ($path === false) {
                throw new RuntimeException('Penyimpanan sementara tidak tersedia.');
            }
            $this->path = $path;
            try {
                if (!$new) {
                    $this->restore($store);
                }
            } catch (Throwable $error) {
                $this->close();
                throw $error;
            }
        }
        if ($new) {
            setcookie('ruangakses_workspace', $id, cookieOptions() + ['expires' => time() + DEMO_LIFETIME]);
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    private function request(string $method, string $url, array $headers = [], ?string $data = null): array
    {
        $curl = curl_init($url);
        $incoming = [];
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->token, ...$headers],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$incoming): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $incoming[strtolower(trim($name))] = trim($value);
                }
                return strlen($line);
            },
        ]);
        if ($data !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        $result = curl_exec($curl);
        if ($result === false) {
            throw new RuntimeException('Koneksi penyimpanan demo gagal.');
        }
        return [curl_getinfo($curl, CURLINFO_HTTP_CODE), $result, $incoming];
    }

    private function restore(string $store): void
    {
        [$status, $body, $headers] = $this->request('GET', "https://$store.private.blob.vercel-storage.com/{$this->object}?cache=0");
        if ($status === 404) {
            return;
        }
        if ($status !== 200 || empty($headers['etag']) || file_put_contents($this->path, $body) === false) {
            throw new RuntimeException('Ruang demo gagal dibuka.');
        }
        $this->originalHash = hash('sha256', $body);
        $this->etag = $headers['etag'];
    }

    public function save(): void
    {
        if ($this->token === '') {
            return;
        }
        $data = file_get_contents($this->path);
        if ($data === false) {
            throw new RuntimeException('Ruang demo gagal dibaca.');
        }
        if (hash('sha256', $data) === $this->originalHash) {
            return;
        }
        $headers = [
            'x-api-version: 13', 'x-vercel-blob-access: private', 'x-add-random-suffix: 0',
            'x-content-type: application/octet-stream', 'Content-Type: application/octet-stream',
            'x-cache-control-max-age: 0', 'x-ttl-days: 7', 'x-allow-overwrite: ' . ($this->etag === null ? '0' : '1'),
        ];
        if ($this->etag !== null) {
            $headers[] = 'x-if-match: ' . $this->etag;
        }
        [$status] = $this->request('PUT', 'https://blob.vercel-storage.com/?pathname=' . rawurlencode($this->object), $headers, $data);
        if (in_array($status, [409, 412], true)) {
            throw new HttpError(409, 'Ruang demo baru saja diperbarui. Muat ulang halaman lalu coba lagi.');
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Ruang demo gagal disimpan.');
        }
    }

    public function close(): void
    {
        if ($this->token !== '') {
            unlink($this->path);
        }
    }
}
