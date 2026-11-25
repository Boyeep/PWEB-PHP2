# Pengujian RuangAkses

## Menjalankan pengujian

Instal dependensi Python dan buka server PHP. Untuk menguji MySQL atau MariaDB, impor schema ke database pengujian kosong dan jalankan `php bin/setup.php demo`.

```sh
python -m pip install -r tests/requirements.txt
python tests/check_http.py http://localhost:8000 --demo
python tests/check_http.py http://localhost:8001
python tests/check_browser.py http://localhost:8000 --channel chrome
```

Gunakan `--demo` pada SQLite untuk memeriksa isolasi browser. Skrip membuat akun dan pendaftaran fiktif. Jalankan pada database uji atau ruang demo.

## Hasil lokal

| Lingkungan | Hasil |
| --- | --- |
| PHP 8.2 dan SQLite | 62 pemeriksaan HTTP lulus |
| PHP 8.2 dan MariaDB 10.4 | 58 pemeriksaan HTTP lulus |
| Chrome | Login cepat, tombol tampilkan kata sandi, Bootstrap, dan halaman HTTP 403 lulus. Dasbor, daftar pengguna, formulir, dan blog diperiksa pada lebar 375, 768, dan 1280 piksel. |

## Hasil deployment

Pada [demo publik](https://pweb-php2.vercel.app/), 63 pemeriksaan HTTP lulus, termasuk atribut cookie HTTPS, alur pendaftaran, perubahan hak akses, dan isolasi browser. Pengujian Chrome pada lebar 375, 768, dan 1280 piksel juga lulus. Ketiga gambar pada [artikel](https://pweb-php2.vercel.app/blog) dapat diunduh.

Saat dua permintaan tambah akun dikirim bersamaan dalam satu ruang demo, satu mendapat HTTP 303 dan satu mendapat HTTP 409. Permintaan kedua berhasil saat dicoba ulang. Kedua akun dapat ditemukan dalam daftar.

## Cakupan

| Skenario | Hasil yang diperiksa |
| --- | --- |
| Login, logout, dan sesi | Empat peran diarahkan ke dasbor masing-masing. ID sesi berganti ketika login. Logout menutup akses. |
| CRUD pengguna | Admin membuat, membaca, mencari, memfilter, memperbarui, dan menghapus akun. |
| Validasi pengguna | Email duplikat, kata sandi pendek, peran tidak dikenal, serta token CSRF yang salah ditolak. |
| Integritas akun | Akun sendiri dan akun dengan riwayat pendaftaran dipertahankan. Perubahan kata sandi membatalkan sesi lama. |
| Izin akses | Panitia, Verifikator, dan Siswa mendapat HTTP 403 pada manajemen pengguna. Siswa hanya dapat mengakses pendaftarannya. |
| Pendaftaran | Berkas divalidasi berdasarkan isi, dokumen dapat diunduh sesuai izin, dan alur revisi kembali ke antrean. |
| Isolasi | Perubahan bertahan setelah logout dalam ruang yang sama. Browser baru membuka ruang terpisah. |

## Tangkapan layar

| Halaman | Berkas |
| --- | --- |
| Masuk | [Desktop](screenshots/halaman-masuk.webp) |
| Dasbor Admin | [Desktop](screenshots/dashboard-admin.webp) |
| Manajemen pengguna | [Desktop](screenshots/manajemen-pengguna.webp) · [Ponsel](screenshots/manajemen-ponsel.webp) |
| Formulir tambah | [Desktop](screenshots/tambah-pengguna.webp) |
| Formulir edit | [Desktop](screenshots/edit-pengguna.webp) |
| Pengujian RBAC | [HTTP 403 dari akun Panitia](screenshots/rbac-403.webp) |
