<div class="page-heading">
    <div>
        <h1>Manajemen pengguna</h1>
        <p class="muted mb-0">Cari akun dan atur peran pengguna PPDB.</p>
    </div>
    <a class="btn btn-primary" href="/pengguna/tambah"><?= icon('plus', 19) ?> Tambah pengguna</a>
</div>
<section class="panel table-panel" aria-labelledby="users-title">
    <div class="table-tools">
        <div class="section-heading">
            <h2 id="users-title">Daftar pengguna</h2><span class="muted"><?= $total ?> akun</span>
        </div>
        <form class="filters" method="get" action="/pengguna">
            <div class="search-field">
                <label class="form-label" for="q">Cari pengguna</label>
                <div class="input-icon"><?= icon('search', 18) ?><input class="form-control" type="search" id="q" name="q" maxlength="150" value="<?= h($keyword) ?>" placeholder="Nama, email, atau ID"></div>
            </div>
            <div>
                <label class="form-label" for="filter-role">Peran</label>
                <select class="form-select" id="filter-role" name="role">
                    <option value="">Semua peran</option>
                    <?php foreach (ROLES as $key => $label): ?><option value="<?= h($key) ?>"<?= $role === $key ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Terapkan</button>
            <?php if ($keyword !== '' || $role !== ''): ?><a class="btn btn-outline" href="/pengguna">Reset</a><?php endif; ?>
        </form>
    </div>
    <?php if (!$rows): ?>
        <div class="empty-state"><span class="stat-icon"><?= icon('search') ?></span><h3>Pengguna tidak ditemukan</h3><p class="muted mb-0">Ubah kata pencarian atau pilih peran lain.</p></div>
    <?php else: ?>
        <table class="table access-table">
            <caption class="visually-hidden">Daftar akun pengguna</caption>
            <thead><tr><th scope="col">Nama dan email</th><th scope="col">Peran</th><th scope="col">Dibuat</th><th scope="col">Tindakan</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <th scope="row">
                        <div class="person"><span class="avatar pill-<?= h($row['role']) ?>" aria-hidden="true"><?= h(initials($row['nama_lengkap'])) ?></span>
                        <div><strong><?= h($row['nama_lengkap']) ?></strong><small><?= h($row['email']) ?> · #<?= h($row['id']) ?></small></div></div>
                    </th>
                    <td data-label="Peran"><?= roleBadge($row['role']) ?></td>
                    <td data-label="Dibuat" class="table-date"><?= h(dateLabel($row['created_at'])) ?></td>
                    <td class="row-actions">
                        <a href="/pengguna/edit?id=<?= h($row['id']) ?>" aria-label="Edit <?= h($row['nama_lengkap']) ?>">Edit</a>
                        <?php if ((int) $row['id'] !== (int) $actor['id']): ?><a class="danger-link" href="/pengguna/hapus?id=<?= h($row['id']) ?>" aria-label="Hapus <?= h($row['nama_lengkap']) ?>">Hapus</a><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php pager('/pengguna', ['q' => $keyword, 'role' => $role], $current, $pages); ?>
    <?php endif; ?>
</section>
