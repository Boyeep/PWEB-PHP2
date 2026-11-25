<section class="panel error-panel">
    <p class="eyebrow">HTTP <?= $status ?></p>
    <h1><?= h($title) ?></h1>
    <p class="muted"><?= h($description) ?></p>
    <a class="btn btn-primary" href="<?= $actor ? h(dashboardUrl($actor['role'])) : '/' ?>"><?= icon('back', 18) ?> <?= $actor ? 'Kembali ke dasbor' : 'Kembali ke halaman masuk' ?></a>
</section>
