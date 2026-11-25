<a class="back-link" href="/dashboard/siswa"><?= icon('back', 18) ?> Dasbor siswa</a>
<div class="page-heading">
    <div><h1><?= h($title) ?></h1><p class="muted mb-0">Lengkapi data calon siswa dan unggah dokumen pendukung.</p></div>
</div>
<?php if ($record && $record['catatan'] !== ''): ?><p class="notice form-panel"><?= nl2br(h($record['catatan'])) ?></p><?php endif; ?>
<section class="panel form-panel" aria-label="Formulir pendaftaran">
    <form method="post" action="/pendaftaran/isi" enctype="multipart/form-data" class="form-stack">
        <?php csrfField(); ?>
        <div>
            <label class="form-label" for="nisn">NISN</label>
            <input class="form-control" id="nisn" name="nisn" value="<?= h($values['nisn']) ?>" inputmode="numeric" maxlength="10" pattern="[0-9]{10}" required<?= invalid($errors, 'nisn') ?>>
            <?= fieldError($errors, 'nisn') ?>
        </div>
        <div>
            <label class="form-label" for="no_hp">Nomor telepon</label>
            <input class="form-control" id="no_hp" name="no_hp" type="tel" value="<?= h($values['no_hp']) ?>" maxlength="16" autocomplete="tel" required<?= invalid($errors, 'no_hp') ?>>
            <?= fieldError($errors, 'no_hp') ?>
        </div>
        <div>
            <label class="form-label" for="jurusan">Jurusan pilihan</label>
            <select class="form-select" id="jurusan" name="jurusan" required<?= invalid($errors, 'jurusan') ?>>
                <option value="">Pilih jurusan</option>
                <?php foreach (PROGRAMS as $value): ?><option value="<?= h($value) ?>"<?= $values['jurusan'] === $value ? ' selected' : '' ?>><?= h($value) ?></option><?php endforeach; ?>
            </select>
            <?= fieldError($errors, 'jurusan') ?>
        </div>
        <div>
            <label class="form-label" for="dokumen"><?= $record ? 'Dokumen pengganti' : 'Dokumen pendukung' ?></label>
            <input class="form-control" id="dokumen" name="dokumen" type="file" accept="application/pdf,image/jpeg,image/png" required<?= invalid($errors, 'dokumen') ?><?= isset($errors['dokumen']) ? '' : ' aria-describedby="document-help"' ?>>
            <?= fieldError($errors, 'dokumen') ?>
            <p class="field-help" id="document-help">PDF, JPG, atau PNG hingga 1 MB.<?= demoMode() ? ' Gunakan berkas contoh.' : '' ?></p>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Kirim pendaftaran</button><a class="btn btn-outline" href="/dashboard/siswa">Batal</a></div>
    </form>
</section>
