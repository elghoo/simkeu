<?php
/** @var string $title */
$title = $title ?? 'Dashboard';
$current = $_GET['page'] ?? 'dashboard';
$menu = [
    ['header' => 'Utama', 'perm' => 'dashboard'],
    ['page' => 'dashboard',  'icon' => 'grid-1x2',          'label' => 'Dashboard', 'perm' => 'dashboard'],
    ['header' => 'Transaksi', 'perm' => 'pengajuan.view'],
    ['page' => 'pemasukan',  'icon' => 'arrow-down-left-circle', 'label' => 'Pemasukan', 'perm' => 'transaksi.view'],
    ['page' => 'pengeluaran','icon' => 'arrow-up-right-circle',  'label' => 'Pengeluaran', 'perm' => 'transaksi.view'],
    ['page' => 'transfer',   'icon' => 'arrow-left-right',  'label' => 'Transfer Kas', 'perm' => 'transaksi.view'],
    ['page' => 'pengajuan',  'icon' => 'clipboard-check',   'label' => 'Pengajuan Dana', 'perm' => 'pengajuan.view', 'badge' => true],
    ['header' => 'Perencanaan & Laporan', 'perm' => 'laporan'],
    ['page' => 'anggaran',   'icon' => 'pie-chart',         'label' => 'Anggaran (RAPB)', 'perm' => 'laporan'],
    ['page' => 'laporan',    'icon' => 'file-earmark-bar-graph', 'label' => 'Laporan Keuangan', 'perm' => 'laporan'],
    ['header' => 'Data Master', 'perm' => 'master'],
    ['page' => 'akun_kas',   'icon' => 'bank',              'label' => 'Akun Kas & Bank', 'perm' => 'master'],
    ['page' => 'kategori',   'icon' => 'tags',              'label' => 'Kategori / Pos', 'perm' => 'master'],
    ['page' => 'unit',       'icon' => 'diagram-3',         'label' => 'Unit Kerja', 'perm' => 'master'],
    ['header' => 'Sistem', 'perm' => 'system'],
    ['page' => 'pengguna',   'icon' => 'person-gear',       'label' => 'Pengguna', 'perm' => 'system'],
    ['page' => 'pengaturan', 'icon' => 'gear',              'label' => 'Pengaturan', 'perm' => 'system'],
    ['page' => 'log',        'icon' => 'clock-history',     'label' => 'Log Aktivitas', 'perm' => 'system'],
];
// Jumlah pengajuan yang perlu ditindaklanjuti oleh peran ini
$__badge = 0;
if (can('pengajuan.approve')) {
    $__badge += (int)db()->query("SELECT COUNT(*) FROM pengajuan WHERE status = 'diajukan'")->fetchColumn();
}
if (can('pengajuan.cair')) {
    $__badge += (int)db()->query("SELECT COUNT(*) FROM pengajuan WHERE status = 'disetujui'")->fetchColumn();
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> &middot; <?= APP_NAME ?></title>
    <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><rect width="40" height="40" rx="11" fill="#173B57"/><rect x="9" y="22" width="5" height="9" rx="1.5" fill="#fff"/><rect x="17.5" y="16" width="5" height="15" rx="1.5" fill="#fff"/><rect x="26" y="10" width="5" height="21" rx="1.5" fill="#D4A63A"/></svg>') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a href="<?= url() ?>" class="brand">
            <span class="brand-logo"><?= logo_svg(36) ?></span>
            <span>
                <span class="brand-name"><?= APP_NAME ?></span>
                <span class="brand-sub"><?= e(setting('nama_lembaga', APP_DESC)) ?></span>
            </span>
        </a>
        <nav class="side-nav">
            <?php foreach ($menu as $m):
                if (!empty($m['perm']) && !can($m['perm'])) continue;
                if (isset($m['header'])): ?>
                    <div class="nav-header"><?= e($m['header']) ?></div>
                <?php else: ?>
                    <a href="<?= url($m['page']) ?>" class="nav-link-item <?= $current === $m['page'] ? 'active' : '' ?>">
                        <i class="bi bi-<?= $m['icon'] ?>"></i><span><?= e($m['label']) ?></span>
                        <?php if (!empty($m['badge']) && $__badge): ?><span class="nav-badge"><?= $__badge ?></span><?php endif; ?>
                    </a>
                <?php endif; endforeach; ?>
        </nav>
        <div class="side-foot">v<?= APP_VERSION ?> &middot; PHP Native</div>
    </aside>
    <div class="sidebar-backdrop" data-toggle-sidebar></div>

    <div class="main">
        <header class="topbar">
            <button class="btn btn-icon d-lg-none" data-toggle-sidebar aria-label="Buka menu"><i class="bi bi-list"></i></button>
            <div class="topbar-title">
                <h1><?= e($title) ?></h1>
                <?php if (!empty($subtitle)): ?><p><?= e($subtitle) ?></p><?php endif; ?>
            </div>
            <div class="dropdown ms-auto">
                <button class="btn user-chip dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar"><?= e(inisial(user('nama'))) ?></span>
                    <span class="d-none d-sm-inline text-start">
                        <span class="d-block fw-semibold lh-1"><?= e(user('nama')) ?></span>
                        <small class="text-muted"><?= e(role_list()[user('role')] ?? user('role')) ?></small>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><a class="dropdown-item" href="<?= url('profil') ?>"><i class="bi bi-person me-2"></i>Profil &amp; Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
                </ul>
            </div>
        </header>
        <main class="content">
            <?= show_flash() ?>
