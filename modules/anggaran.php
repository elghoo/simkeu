<?php
/** Anggaran (RAPB) per kategori per tahun + realisasi */
$pdo = db();
$tahun = (int)($_GET['tahun'] ?? setting('tahun_anggaran', date('Y')));
if ($tahun < 2000 || $tahun > 2100) $tahun = (int)date('Y');
$editMode = can('master') && !empty($_GET['edit']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_perm('master');
    $tahun = (int)($_POST['tahun'] ?? $tahun);
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $st = $pdo->prepare('INSERT INTO anggaran (tahun, kategori_id, jumlah, keterangan) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE jumlah = VALUES(jumlah), keterangan = VALUES(keterangan)');
        foreach ((array)($_POST['jumlah'] ?? []) as $kid => $v) {
            $st->execute([$tahun, (int)$kid, round(parse_angka($v), 2), trim($_POST['ket'][$kid] ?? '') ?: null]);
        }
        log_aktivitas('simpan_anggaran', 'Anggaran tahun ' . $tahun);
        flash('success', 'Anggaran tahun ' . $tahun . ' berhasil disimpan.');
    }
    if ($action === 'salin') {
        $dari = $tahun - 1;
        $pdo->prepare('INSERT INTO anggaran (tahun, kategori_id, jumlah, keterangan) SELECT ?, kategori_id, jumlah, keterangan FROM anggaran WHERE tahun = ?
            ON DUPLICATE KEY UPDATE jumlah = VALUES(jumlah)')->execute([$tahun, $dari]);
        log_aktivitas('salin_anggaran', "Salin anggaran $dari ke $tahun");
        flash('success', "Anggaran tahun $dari disalin ke tahun $tahun. Silakan sesuaikan nilainya.");
    }
    redirect(url('anggaran', ['tahun' => $tahun]));
}

$st = $pdo->prepare("SELECT k.*, COALESCE(a.jumlah,0) anggaran, a.keterangan ket,
        COALESCE((SELECT SUM(jumlah) FROM transaksi t WHERE t.kategori_id = k.id AND YEAR(t.tanggal) = ?),0) realisasi
    FROM kategori k LEFT JOIN anggaran a ON a.kategori_id = k.id AND a.tahun = ?
    WHERE k.aktif = 1 OR a.jumlah > 0 ORDER BY k.tipe DESC, k.kode");
$st->execute([$tahun, $tahun]);
$rows = $st->fetchAll();
$grup = ['masuk' => [], 'keluar' => []];
foreach ($rows as $r) $grup[$r['tipe']][] = $r;
$sum = fn($t, $f) => array_sum(array_column($grup[$t], $f));
$adaPrev = (bool)$pdo->query('SELECT COUNT(*) FROM anggaran WHERE tahun = ' . ($tahun - 1))->fetchColumn();

$title = 'Anggaran (RAPB)';
$subtitle = 'Rencana Anggaran Pendapatan & Belanja tahun ' . $tahun;
require ROOT_PATH . '/includes/header.php';
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <form method="get" class="d-flex gap-2"><input type="hidden" name="page" value="anggaran">
        <select name="tahun" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Tahun anggaran">
            <?php for ($y = (int)date('Y') + 1; $y >= (int)date('Y') - 4; $y--): ?><option value="<?= $y ?>" <?= selected($y, $tahun) ?>>Tahun <?= $y ?></option><?php endfor; ?>
        </select></form>
    <div class="ms-auto d-flex gap-2">
        <a href="<?= url('laporan', ['tab' => 'anggaran', 'tahun' => $tahun]) ?>" class="btn btn-sm btn-light"><i class="bi bi-printer me-1"></i>Laporan realisasi</a>
        <?php if (can('master') && !$editMode): ?>
            <?php if ($adaPrev): ?><form method="post" data-confirm="Salin anggaran tahun <?= $tahun - 1 ?> ke <?= $tahun ?>? Nilai yang sudah ada akan ditimpa."><?= csrf_field() ?><input type="hidden" name="action" value="salin"><input type="hidden" name="tahun" value="<?= $tahun ?>"><button class="btn btn-sm btn-light"><i class="bi bi-copy me-1"></i>Salin dari <?= $tahun - 1 ?></button></form><?php endif; ?>
            <a href="<?= url('anggaran', ['tahun' => $tahun, 'edit' => 1]) ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Susun / Ubah Anggaran</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card"><div class="stat flex-column gap-1"><div class="stat-label">Target pendapatan</div><div class="stat-value"><?= rupiah($sum('masuk', 'anggaran')) ?></div><div class="stat-sub">Tercapai <?= rupiah($sum('masuk', 'realisasi')) ?> (<?= angka($sum('masuk', 'anggaran') ? $sum('masuk', 'realisasi') / $sum('masuk', 'anggaran') * 100 : 0, 1) ?>%)</div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="stat flex-column gap-1"><div class="stat-label">Pagu belanja</div><div class="stat-value"><?= rupiah($sum('keluar', 'anggaran')) ?></div><div class="stat-sub">Terserap <?= rupiah($sum('keluar', 'realisasi')) ?> (<?= angka($sum('keluar', 'anggaran') ? $sum('keluar', 'realisasi') / $sum('keluar', 'anggaran') * 100 : 0, 1) ?>%)</div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="stat flex-column gap-1"><div class="stat-label">Rencana surplus / defisit</div><?php $sd = $sum('masuk', 'anggaran') - $sum('keluar', 'anggaran'); ?><div class="stat-value <?= $sd >= 0 ? 'text-in' : 'text-out' ?>"><?= rupiah($sd) ?></div><div class="stat-sub">Selisih target pendapatan dan pagu belanja</div></div></div></div>
</div>

<?php if ($editMode): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="tahun" value="<?= $tahun ?>"><?php endif; ?>
<?php foreach (['masuk' => 'Anggaran Pendapatan', 'keluar' => 'Anggaran Belanja'] as $t => $judul): ?>
<div class="card mb-3">
    <div class="card-header"><h2><?= $judul ?></h2></div>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Kode</th><th>Kategori / pos</th><th class="text-end" style="min-width:170px">Anggaran</th><th class="text-end">Realisasi</th><th class="text-end"><?= $t === 'masuk' ? 'Kekurangan' : 'Sisa' ?></th><th style="width:170px">Capaian</th></tr></thead>
        <tbody><?php foreach ($grup[$t] as $r): $pct = $r['anggaran'] > 0 ? $r['realisasi'] / $r['anggaran'] * 100 : ($r['realisasi'] > 0 ? 100 : 0); ?>
            <tr>
                <td><span class="badge-soft bs-gold"><?= e($r['kode']) ?></span></td>
                <td class="fw-semibold"><?= e($r['nama']) ?><?php if ($editMode): ?><input name="ket[<?= $r['id'] ?>]" class="form-control form-control-sm mt-1 fw-normal" placeholder="Keterangan (opsional)" value="<?= e($r['ket']) ?>" maxlength="255"><?php elseif ($r['ket']): ?><div class="small text-muted fw-normal"><?= e($r['ket']) ?></div><?php endif; ?></td>
                <td class="text-end num">
                    <?php if ($editMode): ?><input name="jumlah[<?= $r['id'] ?>]" class="form-control form-control-sm text-end input-rupiah" inputmode="numeric" value="<?= (int)$r['anggaran'] ?>" aria-label="Anggaran <?= e($r['nama']) ?>">
                    <?php else: ?><?= rupiah($r['anggaran']) ?><?php endif; ?></td>
                <td class="text-end num"><?= rupiah($r['realisasi']) ?></td>
                <td class="text-end num <?= $r['anggaran'] - $r['realisasi'] < 0 && $t === 'keluar' ? 'text-out fw-semibold' : '' ?>"><?= rupiah($r['anggaran'] - $r['realisasi']) ?></td>
                <td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1"><div class="progress-bar <?= $t === 'keluar' ? ($pct > 100 ? 'over' : ($pct > 85 ? 'warn' : '')) : '' ?>" style="width:<?= min(100, $pct) ?>%; <?= $t === 'masuk' ? 'background:var(--zk-success)' : '' ?>"></div></div><span class="small num" style="width:48px"><?= angka($pct, 1) ?>%</span></div></td>
            </tr>
        <?php endforeach; ?></tbody>
        <tfoot><tr><td colspan="2">Jumlah</td><td class="text-end num"><?= rupiah($sum($t, 'anggaran')) ?></td><td class="text-end num"><?= rupiah($sum($t, 'realisasi')) ?></td><td class="text-end num"><?= rupiah($sum($t, 'anggaran') - $sum($t, 'realisasi')) ?></td><td></td></tr></tfoot>
    </table></div>
</div>
<?php endforeach; ?>
<?php if ($editMode): ?>
    <div class="d-flex gap-2 position-sticky bottom-0 py-2" style="background:var(--zk-bg)"><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan Anggaran <?= $tahun ?></button><a href="<?= url('anggaran', ['tahun' => $tahun]) ?>" class="btn btn-light">Batal</a></div>
</form>
<?php endif; ?>
<?php require ROOT_PATH . '/includes/footer.php';
