<a class="back-link" href="/pengguna"><?= icon('back', 18) ?> Daftar pengguna</a>
<div class="page-heading">
    <div><h1><?= $create ? 'Tambah pengguna' : 'Edit pengguna' ?></h1><p class="muted mb-0"><?= $create ? 'Buat akun dan tentukan perannya.' : 'Perbarui identitas dan hak akses akun.' ?></p></div>
</div>
<section class="panel form-panel" aria-label="Formulir pengguna">
    <?php if (isset($errors['form'])): ?><p class="notice danger" role="alert"><?= h($errors['form']) ?></p><?php endif; ?>
    <form method="post" action="<?= $create ? '/pengguna/tambah' : '/pengguna/edit?id=' . h($id) ?>" class="form-stack">
        <?php csrfField(); ?>
        <div>
            <label class="form-label" for="nama_lengkap">Nama lengkap</label>
            <input class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?= h($values['nama_lengkap']) ?>" maxlength="150" autocomplete="off" required<?= invalid($errors, 'nama_lengkap') ?>>
            <?= fieldError($errors, 'nama_lengkap') ?>
        </div>
        <div>
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" value="<?= h($values['email']) ?>" maxlength="150" autocomplete="off" required<?= invalid($errors, 'email') ?>>
            <?= fieldError($errors, 'email') ?>
        </div>
        <div>
            <label class="form-label" for="role">Peran</label>
            <select class="form-select" id="role" name="role" required<?= invalid($errors, 'role') ?>>
                <?php foreach (ROLES as $key => $label): ?><option value="<?= h($key) ?>"<?= $values['role'] === $key ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
            </select>
            <?= fieldError($errors, 'role') ?>
        </div>
        <div>
            <label class="form-label" for="password">Kata sandi<?= $create ? '' : ' baru (opsional)' ?></label>
            <div class="password-field">
                <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" minlength="10" maxlength="72"<?= $create ? ' required' : '' ?><?= invalid($errors, 'password') ?><?= isset($errors['password']) ? '' : ' aria-describedby="password-help"' ?>>
                <button class="password-toggle" type="button" data-password="password" aria-pressed="false" hidden>Lihat</button>
            </div>
            <?= fieldError($errors, 'password') ?>
            <p class="field-help" id="password-help"><?= $create ? 'Minimal 10 karakter, maksimal 72 byte.' : 'Kosongkan untuk mempertahankan kata sandi saat ini.' ?></p>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= $create ? 'Simpan pengguna' : 'Simpan perubahan' ?></button>
            <a class="btn btn-outline" href="/pengguna">Batal</a>
        </div>
    </form>
</section>
