# RuangAkses

Portal simulasi PPDB SMK Nusantara untuk login multi-peran, manajemen akun, dan pemeriksaan pendaftaran. Dibangun dengan PHP native, PDO, MySQL atau SQLite, dan Bootstrap 5.

[Buka demo](https://pweb-php2.vercel.app/) · [Baca artikel](https://pweb-php2.vercel.app/blog) · [Hasil pengujian](docs/PENGUJIAN.md)

## Hak akses

| Peran | Fitur |
| --- | --- |
| Admin | Dasbor statistik, daftar dan pencarian akun, filter peran, tambah, edit, hapus, aktivitas terbaru, dan akses baca pendaftaran |
| Panitia | Dasbor pendaftaran, pemantauan calon siswa, serta filter status |
| Verifikator | Antrean, unduh dokumen, dan keputusan verifikasi atau revisi |
| Siswa | Isi pendaftaran, unggah dokumen, lihat status sendiri, dan kirim perbaikan bila diminta |

Penghapusan akun memakai konfirmasi dan POST. Sistem melindungi akun Admin yang sedang digunakan, Admin terakhir, serta akun dengan riwayat pendaftaran. Pemeriksaan peran, kepemilikan, dan token CSRF dijalankan di server.

## Menjalankan proyek

Gunakan PHP 8.2+ dengan PDO, mbstring, dan Fileinfo. Jalankan perintah dari direktori proyek.

```sh
php -S localhost:8000 -t public
```

Buka `http://localhost:8000/`. Mode SQLite membutuhkan `pdo_sqlite` dan mengisi akun contoh per ruang browser. Gunakan tombol **Coba akun demo** atau email `admin@ruangakses.test`, `panitia@ruangakses.test`, `verifikator@ruangakses.test`, atau `siswa@ruangakses.test` dengan kata sandi `RuangDemo26!`.

### MySQL atau MariaDB

1. Aktifkan `pdo_mysql`, lalu impor [database/mysql.sql](database/mysql.sql) melalui phpMyAdmin atau klien MySQL.
2. Salin [.env.example](.env.example) ke `.env` dan isi koneksi database.
3. Atur `INITIAL_ADMIN_PASSWORD` di lingkungan shell. Jalankan `php bin/setup.php admin admin@example.com`. Akun awal dibuat saat tabel `users` masih kosong.
4. Jalankan server PHP dengan document root `public`. Jika menggunakan Apache, arahkan rute aplikasi ke `public/index.php` dan layani direktori aset sebagai berkas statis.

Untuk pengujian lokal dengan database MySQL kosong, `php bin/setup.php demo` membuat akun dan pendaftaran fiktif. Tombol masuk cepat tersedia pada mode SQLite.

## Deployment

Vercel menjalankan PHP melalui `vercel-php@0.9.0`. Demo publik menyimpan snapshot SQLite di Vercel Blob privat, dengan cookie ruang dan autentikasi yang terpisah. Konfigurasi hosting memakai `BLOB_READ_WRITE_TOKEN` dan `BLOB_STORE_ID`. Gunakan data contoh saat mencoba demo publik.

Blog dapat dibaca langsung di `/blog`. Untuk ditempel di Blogger, gunakan [blogspot.html](docs/blogspot.html).
