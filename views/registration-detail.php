<?php
$student = $actor['role'] === 'siswa';
$back = $student ? '/dashboard/siswa' : '/pendaftaran';
$canReview = $actor['role'] === 'verifikator' && $record['status'] === 'menunggu';
?>
<a class="back-link" href="<?= h($back) ?>"><?= icon('back', 18) ?> <?= $student ? 'Dasbor siswa' : 'Daftar pendaftaran' ?></a>
<div class="page-heading">
    <div><h1>Detail pendaftaran</h1><p class="muted mb-0">Pendaftaran #<?= h($record['id']) ?></p></div>
    <?= statusBadge($record['status']) ?>
</div>
<div class="row g-3">
    <div class="col-lg-<?= $canReview ? '6' : '8' ?>">
        <section class="panel">
            <h2>Data calon siswa</h2>
            <dl class="detail-list">
                <div><dt>Nama lengkap</dt><dd><?= h($record['nama_lengkap']) ?></dd></div>
                <?php if ($actor['role'] !== 'panitia'): ?>
                    <div><dt>NISN</dt><dd><?= h($record['nisn']) ?></dd></div>
                    <div><dt>Nomor telepon</dt><dd><?= h($record['no_hp']) ?></dd></div>
                <?php endif; ?>
                <div><dt>Jurusan</dt><dd><?= h($record['jurusan']) ?></dd></div>
                <div><dt>Diperbarui</dt><dd><?= h(dateLabel($record['updated_at'])) ?></dd></div>
                <?php if ($record['catatan'] !== ''): ?><div><dt>Catatan verifikasi</dt><dd><?= nl2br(h($record['catatan'])) ?></dd></div><?php endif; ?>
            </dl>
            <div class="form-actions">
                <?php if ($actor['role'] !== 'panitia' && $record['dokumen_mime']): ?>
                    <a class="btn btn-outline" href="/dokumen?id=<?= h($record['id']) ?>"><?= icon('download', 18) ?> Unduh dokumen</a>
                <?php endif; ?>
                <?php if ($student && $record['status'] === 'perlu_revisi'): ?>
                    <a class="btn btn-primary" href="/pendaftaran/isi"><?= icon('edit', 18) ?> Perbaiki pendaftaran</a>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <?php if ($canReview): ?>
        <div class="col-lg-6">
            <section class="panel">
                <h2>Verifikasi dokumen</h2>
                <p class="muted">Unduh dan periksa dokumen sebelum menyimpan keputusan.</p>
                <form method="post" action="/pendaftaran/detail?id=<?= h($record['id']) ?>" class="form-stack">
                    <?php csrfField(); ?>
                    <div>
                        <label class="form-label" for="status">Keputusan</label>
                        <select class="form-select" id="status" name="status" required<?= invalid($errors, 'status') ?>>
                            <option value="">Pilih keputusan</option>
                            <?php foreach (['terverifikasi', 'perlu_revisi'] as $key): ?><option value="<?= h($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= h(STATUSES[$key]) ?></option><?php endforeach; ?>
                        </select>
                        <?= fieldError($errors, 'status') ?>
                    </div>
                    <div>
                        <label class="form-label" for="catatan">Catatan verifikasi</label>
                        <textarea class="form-control" id="catatan" name="catatan" maxlength="250" rows="3"<?= invalid($errors, 'catatan') ?><?= isset($errors['catatan']) ? '' : ' aria-describedby="note-help"' ?>><?= h($note) ?></textarea>
                        <?= fieldError($errors, 'catatan') ?>
                        <p class="field-help" id="note-help">Wajib diisi jika dokumen perlu direvisi.</p>
                    </div>
                    <button class="btn btn-primary" type="submit">Simpan keputusan</button>
                </form>
            </section>
        </div>
    <?php endif; ?>
</div>
