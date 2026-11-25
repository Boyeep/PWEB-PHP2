<div class="page-heading">
    <div><h1>Daftar pendaftaran</h1><p class="muted mb-0"><?= $actor['role'] === 'verifikator' ? 'Pilih calon siswa untuk memeriksa dokumennya.' : 'Pantau status pemeriksaan calon siswa.' ?></p></div>
</div>
<section class="panel table-panel" aria-labelledby="registration-list">
    <div class="table-tools">
        <div class="section-heading">
            <h2 id="registration-list">Calon siswa</h2><span class="muted"><?= $total ?> pendaftaran</span>
        </div>
        <form class="filters" method="get" action="/pendaftaran">
            <div>
                <label class="form-label" for="filter-status">Status</label>
                <select class="form-select" id="filter-status" name="status">
                    <option value="">Semua status</option>
                    <?php foreach (STATUSES as $key => $label): ?><option value="<?= h($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Terapkan</button>
        </form>
    </div>
    <?php if (!$rows): ?>
        <div class="empty-state"><span class="stat-icon"><?= icon('file') ?></span><h3>Belum ada pendaftaran</h3><p class="muted mb-0">Pilih status lain untuk melihat hasil.</p></div>
    <?php else: ?>
        <table class="table access-table">
            <caption class="visually-hidden">Daftar pendaftaran calon siswa</caption>
            <thead><tr><th scope="col">Calon siswa</th><th scope="col">Status</th><th scope="col">Diperbarui</th><th scope="col">Tindakan</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <th scope="row"><div class="person"><span class="avatar pill-siswa" aria-hidden="true"><?= h(initials($row['nama_lengkap'])) ?></span><div><strong><?= h($row['nama_lengkap']) ?></strong><small><?= h($row['jurusan']) ?> · #<?= h($row['id']) ?></small></div></div></th>
                    <td data-label="Status"><?= statusBadge($row['status']) ?></td>
                    <td data-label="Diperbarui" class="table-date"><?= h(dateLabel($row['updated_at'])) ?></td>
                    <td class="row-actions"><a href="/pendaftaran/detail?id=<?= h($row['id']) ?>" aria-label="Lihat pendaftaran <?= h($row['nama_lengkap']) ?>">Lihat detail <?= icon('arrow', 15) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php pager('/pendaftaran', ['status' => $status], $current, $pages); ?>
    <?php endif; ?>
</section>
