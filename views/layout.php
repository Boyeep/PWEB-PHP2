<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="RuangAkses, portal akun dan pendaftaran PPDB SMK Nusantara.">
    <title><?= h($title) ?> | RuangAkses</title>
    <link rel="icon" href="/assets/icon.svg" type="image/svg+xml">
    <link rel="preload" href="/assets/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/style.css">
    <script src="/assets/app.js" defer></script>
</head>
<body class="<?= $actor ? 'app-shell' : 'public-shell' ?>">
<a class="skip" href="#utama">Lewati ke konten</a>
<?php if ($actor): ?>
    <aside class="sidebar">
        <a class="brand" href="<?= h(dashboardUrl($actor['role'])) ?>">
            <span class="brand-icon"><?= icon('shield', 22) ?></span>
            <span>RuangAkses<small>PPDB SMK Nusantara</small></span>
        </a>
        <nav class="side-nav" aria-label="Navigasi utama">
            <a href="<?= h(dashboardUrl($actor['role'])) ?>"<?= $active === 'dashboard' ? ' aria-current="page"' : '' ?>><?= icon('grid') ?> Dasbor</a>
            <?php if ($actor['role'] === 'admin'): ?>
                <a href="/pengguna"<?= $active === 'users' ? ' aria-current="page"' : '' ?>><?= icon('users') ?> Pengguna</a>
            <?php endif; ?>
            <?php if ($actor['role'] !== 'siswa'): ?>
                <a href="/pendaftaran"<?= $active === 'registrations' ? ' aria-current="page"' : '' ?>><?= icon('file') ?> Pendaftaran</a>
            <?php endif; ?>
            <a href="/blog"><?= icon('book') ?> Blog</a>
        </nav>
        <form class="side-footer" method="post" action="/logout">
            <?php csrfField(); ?>
            <button type="submit"><?= icon('logout') ?> Keluar</button>
        </form>
    </aside>
    <div class="app-wrap">
        <header class="topbar">
            <span class="topbar-title"><?= h($title) ?></span>
            <div class="identity">
                <div class="avatar"><?= h(initials($actor['nama_lengkap'])) ?></div>
                <div><strong><?= h($actor['nama_lengkap']) ?></strong><small><?= h(ROLES[$actor['role']]) ?></small></div>
            </div>
        </header>
        <main id="utama" class="main-content" tabindex="-1">
            <?php if ($message): ?><p role="status" class="notice success"><?= icon('check') ?> <?= h($message) ?></p><?php endif; ?>
            <?php require ROOT . '/views/' . $template . '.php'; ?>
        </main>
    </div>
<?php else: ?>
    <?php if ($template !== 'login'): ?>
        <header class="public-nav">
            <a class="brand" href="/"><span class="brand-icon"><?= icon('shield', 22) ?></span> RuangAkses</a>
            <a class="btn btn-outline" href="/">Ke halaman masuk <?= icon('arrow', 16) ?></a>
        </header>
    <?php endif; ?>
    <main id="utama" tabindex="-1"><?php require ROOT . '/views/' . $template . '.php'; ?></main>
<?php endif; ?>
</body>
</html>
