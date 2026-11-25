<?php
$descriptions = [
    'admin' => 'Kelola hak akses dan pantau aktivitas akun PPDB.',
    'panitia' => 'Pantau progres pendaftaran dan status setiap calon siswa.',
    'verifikator' => 'Periksa dokumen calon siswa dan simpan keputusan verifikasi.',
    'siswa' => 'Lengkapi berkas dan pantau hasil verifikasi pendaftaran Anda.',
];
$cta = match ($role) {
    'admin' => ['/pengguna', 'Kelola pengguna', 'users'],
    'panitia' => ['/pendaftaran', 'Lihat pendaftaran', 'file'],
    'verifikator' => ['/pendaftaran?status=menunggu', 'Buka antrean', 'file'],
    'siswa' => !$record ? ['/pendaftaran/isi', 'Isi pendaftaran', 'plus'] : ['/pendaftaran/detail?id=' . $record['id'], 'Lihat pendaftaran saya', 'file'],
};
?>
<section class="dashboard-hero" aria-labelledby="dash-title">
    <div class="hero-content">
        <p class="eyebrow">Dasbor <?= h(ROLES[$role]) ?></p>
        <h1 id="dash-title">Halo, <?= h($actor['nama_lengkap']) ?>.</h1>
        <p><?= h($descriptions[$role]) ?></p>
        <a class="btn btn-hero" href="<?= h($cta[0]) ?>"><?= icon($cta[2], 19) ?> <?= h($cta[1]) ?> <?= icon('arrow', 16) ?></a>
    </div>
    <div class="hero-mark" aria-hidden="true"><?= icon(roleIcon($role), 92) ?></div>
</section>
<?php if ($role === 'admin'): ?>
    <section class="section-space" aria-labelledby="stat-heading">
        <div class="section-heading">
            <h2 id="stat-heading">Ringkasan pengguna</h2>
            <span class="muted"><?= array_sum($counts) ?> akun terdaftar</span>
        </div>
        <div class="row g-3">
            <?php foreach (ROLES as $key => $label): ?>
                <div class="col-6 col-xl-3">
                    <a class="stat-card" href="/pengguna?role=<?= h($key) ?>">
                        <span class="stat-icon pill-<?= h($key) ?>"><?= icon(roleIcon($key)) ?></span>
                        <span><strong><?= h($counts[$key]) ?></strong><span class="muted"><?= h($label) ?></span></span>
                        <span class="stat-arrow"><?= icon('arrow', 17) ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="section-space row g-3" aria-label="Aktivitas dan pendaftaran">
        <div class="col-lg-7">
            <div class="panel h-100">
                <h2>Aktivitas terbaru</h2>
                <?php if (!$activity): ?>
                    <div class="empty-inline"><span class="stat-icon"><?= icon('clock') ?></span><p class="muted mb-0">Aktivitas penambahan, perubahan, dan penghapusan akun akan muncul di sini.</p></div>
                <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach ($activity as $event): ?>
                            <li><div><strong><?= h($event['aktivitas']) ?></strong><small><?= h($event['nama_lengkap'] ?? 'Akun terhapus') ?></small></div><span><?= h(dateLabel($event['created_at'])) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel h-100">
                <h2>Pendaftaran PPDB</h2>
                <p class="muted">Lihat daftar calon siswa dan status pemeriksaan setiap pendaftaran.</p>
                <a class="btn btn-outline" href="/pendaftaran">Lihat daftar <?= icon('arrow', 16) ?></a>
            </div>
        </div>
    </section>
<?php elseif ($role === 'siswa'): ?>
    <section class="panel section-space" aria-labelledby="registration-status">
        <h2 id="registration-status">Pendaftaran saya</h2>
        <?php if ($record): ?>
            <div class="record-summary"><strong><?= h($record['jurusan']) ?></strong><?= statusBadge($record['status']) ?></div>
            <p class="muted">Pendaftaran #<?= h($record['id']) ?> · Diperbarui <?= h(dateLabel($record['updated_at'])) ?></p>
            <?php if ($record['catatan'] !== ''): ?><p class="notice"><?= nl2br(h($record['catatan'])) ?></p><?php endif; ?>
            <?php if ($record['status'] === 'perlu_revisi'): ?><a class="btn btn-primary" href="/pendaftaran/isi"><?= icon('edit', 17) ?> Perbaiki pendaftaran</a><?php endif; ?>
        <?php else: ?>
            <p class="muted mb-0">Anda belum mengirim pendaftaran. Siapkan NISN, nomor telepon, dan dokumen pendukung.</p>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="section-space" aria-labelledby="stat-heading">
        <div class="section-heading">
            <h2 id="stat-heading">Ringkasan pendaftaran</h2>
            <span class="muted"><?= array_sum($counts) ?> pendaftaran</span>
        </div>
        <div class="row g-3">
            <?php $statusIcons = ['menunggu' => 'clock', 'terverifikasi' => 'check', 'perlu_revisi' => 'edit']; ?>
            <?php foreach (STATUSES as $key => $label): ?>
                <div class="col-sm-4">
                    <a class="stat-card" href="/pendaftaran?status=<?= h($key) ?>">
                        <span class="stat-icon pill-<?= h($key) ?>"><?= icon($statusIcons[$key]) ?></span>
                        <span><strong><?= h($counts[$key]) ?></strong><span class="muted"><?= h($label) ?></span></span>
                        <span class="stat-arrow"><?= icon('arrow', 17) ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
