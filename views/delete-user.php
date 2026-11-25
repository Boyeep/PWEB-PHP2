<a class="back-link" href="/pengguna"><?= icon('back', 18) ?> Daftar pengguna</a>
<div class="page-heading"><h1>Hapus pengguna</h1></div>
<section class="panel form-panel">
    <p>Akun <strong><?= h($account['nama_lengkap']) ?></strong> (<?= h($account['email']) ?>) akan dihapus permanen.</p>
    <form method="post" action="/pengguna/hapus?id=<?= h($id) ?>" class="form-actions">
        <?php csrfField(); ?>
        <button type="submit" class="btn btn-danger">Hapus akun</button>
        <a href="/pengguna" class="btn btn-outline">Batal</a>
    </form>
</section>
