<?php
/**
 * SIMKEU - Sistem Informasi Manajemen Keuangan - Dev by Elghodhonfar
 * Front controller / router sederhana
 */
require_once __DIR__ . '/config/app.php';
require_login();

// halaman => [file modul, hak akses]
$routes = [
    'dashboard'   => ['dashboard', 'dashboard'],
    'pemasukan'   => ['transaksi', 'transaksi.view'],
    'pengeluaran' => ['transaksi', 'transaksi.view'],
    'transfer'    => ['transfer', 'transaksi.view'],
    'bukti'       => ['bukti', 'transaksi.view'],
    'pengajuan'   => ['pengajuan', 'pengajuan.view'],
    'anggaran'    => ['anggaran', 'laporan'],
    'laporan'     => ['laporan', 'laporan'],
    'akun_kas'    => ['akun_kas', 'master'],
    'kategori'    => ['kategori', 'master'],
    'unit'        => ['unit', 'master'],
    'pengguna'    => ['pengguna', 'system'],
    'pengaturan'  => ['pengaturan', 'system'],
    'log'         => ['log', 'system'],
    'profil'      => ['profil', null],
];

$page = $_GET['page'] ?? home_page();
if (!isset($routes[$page])) {
    http_response_code(404);
    $title = 'Halaman tidak ditemukan';
    require __DIR__ . '/includes/header.php';
    echo '<div class="card card-body text-center py-5"><h4>404</h4><p class="text-muted">Halaman yang Anda cari tidak tersedia.</p><a href="' . url() . '" class="btn btn-primary mx-auto">Kembali ke Beranda</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

[$module, $perm] = $routes[$page];
if ($perm) require_perm($perm);
require __DIR__ . '/modules/' . $module . '.php';
