<?php
/** Pengaturan profil lembaga & penandatangan laporan */
$pdo = db();
$fields = [
    'Profil Lembaga' => [
        'nama_lembaga' => ['Nama lembaga', 'text'],
        'singkatan'    => ['Singkatan', 'text'],
        'alamat'       => ['Alamat', 'text'],
        'kota'         => ['Kota (untuk tanda tangan)', 'text'],
        'telepon'      => ['Telepon', 'text'],
        'email'        => ['Email', 'email'],
    ],
    'Penandatangan & Periode' => [
        'nama_pimpinan'     => ['Nama pimpinan', 'text'],
        'jabatan_pimpinan'  => ['Jabatan pimpinan', 'text'],
        'nama_bendahara'    => ['Nama bendahara', 'text'],
        'jabatan_bendahara' => ['Jabatan bendahara', 'text'],
        'tahun_anggaran'    => ['Tahun anggaran aktif', 'year'],
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $st = $pdo->prepare('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)');
    foreach ($fields as $group) {
        foreach ($group as $key => [$label, $type]) {
            $v = trim($_POST[$key] ?? '');
            if ($type === 'year') { $v = (int)$v; $v = (string)($v >= 2000 && $v <= 2100 ? $v : date('Y')); }
            if ($type === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) { flash('danger', 'Format email tidak valid.'); redirect(url('pengaturan')); }
            $st->execute([$key, mb_substr($v, 0, 255)]);
        }
    }
    log_aktivitas('ubah_pengaturan', 'Profil lembaga');
    flash('success', 'Pengaturan berhasil disimpan.');
    redirect(url('pengaturan'));
}

$title = 'Pengaturan';
$subtitle = 'Profil lembaga dan penandatangan laporan';
require ROOT_PATH . '/includes/header.php';
?>
<form method="post">
    <?= csrf_field() ?>
    <div class="row g-3">
        <?php foreach ($fields as $groupName => $group): ?>
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header"><h2><?= $groupName ?></h2></div>
            <div class="card-body">
                <?php foreach ($group as $key => [$label, $type]): $val = setting($key, ''); ?>
                    <div class="mb-3"><label class="form-label" for="<?= $key ?>"><?= e($label) ?></label>
                        <?php if ($type === 'year'): ?><input type="number" min="2000" max="2100" class="form-control" id="<?= $key ?>" name="<?= $key ?>" value="<?= e($val ?: date('Y')) ?>">
                        <?php else: ?><input type="<?= $type ?>" class="form-control" id="<?= $key ?>" name="<?= $key ?>" value="<?= e($val) ?>" maxlength="255"><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($groupName !== 'Profil Lembaga'): ?><div class="small text-muted">Nama dan jabatan ini dicetak pada kolom tanda tangan bukti kas dan laporan. Tahun anggaran aktif menjadi tahun bawaan di halaman Anggaran.</div><?php endif; ?>
            </div>
        </div></div>
        <?php endforeach; ?>
    </div>
    <div class="mt-3"><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan Pengaturan</button></div>
</form>
<?php require ROOT_PATH . '/includes/footer.php';
