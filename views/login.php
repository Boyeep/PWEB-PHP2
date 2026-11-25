<div class="login-layout">
    <section class="login-intro" aria-labelledby="portal-title">
        <a class="brand" href="/">
            <span class="brand-icon"><?= icon('shield', 23) ?></span>
            <span>RuangAkses<small>PPDB SMK Nusantara</small></span>
        </a>
        <div class="intro-copy">
            <h1 id="portal-title">Satu portal untuk penerimaan siswa baru.</h1>
            <p>Kelola akun, pantau pendaftaran, dan periksa dokumen PPDB dari dasbor sesuai peran Anda.</p>
        </div>
        <div class="access-preview" aria-hidden="true">
            <div class="preview-mark"><?= icon('shield', 49) ?></div>
            <div class="preview-grid">
                <?php foreach (ROLES as $key => $label): ?>
                    <div class="preview-role">
                        <span class="preview-role-icon"><?= icon(roleIcon($key), 18) ?></span>
                        <span><?= h($label) ?></span>
                        <?= icon('check', 15) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <a class="intro-link" href="/blog"><?= icon('book', 17) ?> Baca catatan proyek <?= icon('arrow', 16) ?></a>
    </section>
    <section class="login-form-wrap" aria-labelledby="login-title">
        <div class="login-box">
            <h2 id="login-title">Masuk ke akun</h2>
            <p class="muted login-copy">Gunakan email dan kata sandi akun Anda.</p>
            <?php if ($error): ?><p class="notice danger" role="alert"><?= h($error) ?></p><?php endif; ?>
            <form method="post" action="/login" class="form-stack">
                <?php csrfField(); ?>
                <div>
                    <label class="form-label" for="login-email">Email</label>
                    <input class="form-control" name="email" id="login-email" type="email" maxlength="150" autocomplete="username" value="<?= h($email) ?>" placeholder="nama@contoh.id" required>
                </div>
                <div>
                    <label class="form-label" for="login-password">Kata sandi</label>
                    <div class="password-field">
                        <input class="form-control" name="password" id="login-password" type="password" autocomplete="current-password" placeholder="Masukkan kata sandi" required>
                        <button class="password-toggle" type="button" data-password="login-password" aria-pressed="false" hidden>Lihat</button>
                    </div>
                </div>
                <button class="btn btn-primary login-submit" type="submit">Masuk <?= icon('arrow', 18) ?></button>
            </form>
            <?php if (demoMode()): ?>
                <div class="demo-login">
                    <h3>Coba akun demo</h3>
                    <p>Gunakan data contoh. Perubahan tersimpan di ruang browser ini.</p>
                    <form method="post" action="/login" class="demo-buttons">
                        <?php csrfField(); ?>
                        <?php foreach (ROLES as $key => $label): ?>
                            <button class="btn btn-outline" type="submit" name="demo_role" value="<?= h($key) ?>"><?= icon(roleIcon($key), 17) ?> <?= h($label) ?></button>
                        <?php endforeach; ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
