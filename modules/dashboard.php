<?php
$title = 'Dashboard';
$subtitle = tgl_indo(date('Y-m-d'), true);
$pdo = db();

$tahunList = $pdo->query('SELECT DISTINCT YEAR(tanggal) y FROM transaksi UNION SELECT YEAR(CURDATE()) ORDER BY y DESC')->fetchAll(PDO::FETCH_COLUMN);
$tahun = (int)($_GET['tahun'] ?? date('Y'));
if (!in_array($tahun, array_map('intval', $tahunList), true)) $tahun = (int)date('Y');

// Saldo seluruh akun saat ini
$saldo = saldo_akun();
$totalSaldo = array_sum(array_column($saldo, 'saldo'));

// Bulan berjalan
$bulanIni = date('Y-m');
$st = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN tipe='masuk' THEN jumlah END),0) masuk,
    COALESCE(SUM(CASE WHEN tipe='keluar' THEN jumlah END),0) keluar
    FROM transaksi WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?");
$st->execute([$bulanIni]);
$bln = $st->fetch();

// Tahun terpilih
$st = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN tipe='masuk' THEN jumlah END),0) masuk,
    COALESCE(SUM(CASE WHEN tipe='keluar' THEN jumlah END),0) keluar
    FROM transaksi WHERE YEAR(tanggal) = ?");
$st->execute([$tahun]);
$thn = $st->fetch();

// Anggaran pengeluaran tahun terpilih
$st = $pdo->prepare("SELECT COALESCE(SUM(a.jumlah),0) FROM anggaran a JOIN kategori k ON k.id = a.kategori_id WHERE a.tahun = ? AND k.tipe = 'keluar'");
$st->execute([$tahun]);
$anggaranKeluar = (float)$st->fetchColumn();
$serapan = $anggaranKeluar > 0 ? $thn['keluar'] / $anggaranKeluar * 100 : 0;

// Grafik arus kas bulanan
$bulanMasuk = array_fill(1, 12, 0);
$bulanKeluar = array_fill(1, 12, 0);
$st = $pdo->prepare("SELECT MONTH(tanggal) b, tipe, SUM(jumlah) t FROM transaksi WHERE YEAR(tanggal) = ? AND tipe IN ('masuk','keluar') GROUP BY MONTH(tanggal), tipe");
$st->execute([$tahun]);
foreach ($st as $r) {
    if ($r['tipe'] === 'masuk') $bulanMasuk[(int)$r['b']] = (float)$r['t'];
    else $bulanKeluar[(int)$r['b']] = (float)$r['t'];
}

// Pengeluaran per kategori
$st = $pdo->prepare("SELECT k.nama, SUM(t.jumlah) t FROM transaksi t JOIN kategori k ON k.id = t.kategori_id
    WHERE t.tipe = 'keluar' AND YEAR(t.tanggal) = ? GROUP BY k.id ORDER BY t DESC");
$st->execute([$tahun]);
$perKategori = $st->fetchAll();
if (count($perKategori) > 6) {
    $lain = array_sum(array_column(array_slice($perKategori, 5), 't'));
    $perKategori = array_slice($perKategori, 0, 5);
    $perKategori[] = ['nama' => 'Lainnya', 't' => $lain];
}

// Realisasi anggaran teratas (pengeluaran)
$st = $pdo->prepare("SELECT k.nama, a.jumlah anggaran,
        COALESCE((SELECT SUM(jumlah) FROM transaksi t WHERE t.kategori_id = k.id AND YEAR(t.tanggal) = a.tahun),0) realisasi
    FROM anggaran a JOIN kategori k ON k.id = a.kategori_id
    WHERE a.tahun = ? AND k.tipe = 'keluar' AND a.jumlah > 0 ORDER BY realisasi / a.jumlah DESC LIMIT 5");
$st->execute([$tahun]);
$realisasi = $st->fetchAll();

// Transaksi terakhir
$terakhir = $pdo->query("SELECT t.*, k.nama kategori, a.nama akun, at.nama akun_tujuan
    FROM transaksi t JOIN akun_kas a ON a.id = t.akun_id LEFT JOIN akun_kas at ON at.id = t.akun_tujuan_id LEFT JOIN kategori k ON k.id = t.kategori_id
    ORDER BY t.tanggal DESC, t.id DESC LIMIT 8")->fetchAll();

// Pengajuan menunggu
$menunggu = $pdo->query("SELECT p.*, u.nama unit FROM pengajuan p JOIN unit u ON u.id = p.unit_id WHERE p.status IN ('diajukan','disetujui') ORDER BY p.tanggal LIMIT 5")->fetchAll();

$labels = array_map(fn($m) => nama_bulan($m, true), range(1, 12));
$useChart = true;
require ROOT_PATH . '/includes/header.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="me-auto text-muted">Ringkasan keuangan <?= e(setting('nama_lembaga', '')) ?></div>
    <form method="get" class="d-flex gap-2">
        <input type="hidden" name="page" value="dashboard">
        <select name="tahun" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Pilih tahun">
            <?php foreach ($tahunList as $y): ?>
                <option value="<?= $y ?>" <?= selected($y, $tahun) ?>>Tahun <?= $y ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php if (can('transaksi.write')): ?>
        <a href="<?= url('pemasukan', ['act' => 'form']) ?>" class="btn btn-sm btn-light"><i class="bi bi-plus-lg me-1 text-in"></i>Pemasukan</a>
        <a href="<?= url('pengeluaran', ['act' => 'form']) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Pengeluaran</a>
    <?php endif; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-4">
        <div class="card saldo-hero h-100">
            <div class="stat flex-column gap-1">
                <div class="stat-label"><i class="bi bi-wallet2 me-1"></i>Total Saldo Kas &amp; Bank</div>
                <div class="stat-value" style="font-size:1.75rem"><?= rupiah($totalSaldo) ?></div>
                <div class="stat-sub"><?= count($saldo) ?> akun &middot; per <?= tgl_indo(date('Y-m-d')) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card h-100"><div class="stat flex-column gap-1">
            <div class="stat-icon ic-in"><i class="bi bi-arrow-down-left"></i></div>
            <div class="stat-label">Pemasukan bulan ini</div>
            <div class="stat-value text-in"><?= rupiah($bln['masuk']) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card h-100"><div class="stat flex-column gap-1">
            <div class="stat-icon ic-red"><i class="bi bi-arrow-up-right"></i></div>
            <div class="stat-label">Pengeluaran bulan ini</div>
            <div class="stat-value text-out"><?= rupiah($bln['keluar']) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card h-100"><div class="stat flex-column gap-1">
            <div class="stat-icon ic-blue"><i class="bi bi-graph-up"></i></div>
            <div class="stat-label">Surplus / defisit <?= $tahun ?></div>
            <div class="stat-value <?= $thn['masuk'] - $thn['keluar'] >= 0 ? 'text-in' : 'text-out' ?>"><?= rupiah($thn['masuk'] - $thn['keluar']) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card h-100"><div class="stat flex-column gap-1">
            <div class="stat-icon ic-gold"><i class="bi bi-pie-chart"></i></div>
            <div class="stat-label">Serapan anggaran <?= $tahun ?></div>
            <div class="stat-value"><?= angka($serapan, 1) ?>%</div>
            <div class="progress w-100 mt-1"><div class="progress-bar <?= $serapan > 100 ? 'over' : ($serapan > 85 ? 'warn' : '') ?>" style="width: <?= min(100, $serapan) ?>%"></div></div>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php foreach ($saldo as $a): if (!$a['aktif'] && abs($a['saldo']) < 1) continue; ?>
        <div class="col-md-4">
            <div class="akun-card d-flex gap-3 align-items-center">
                <div class="stat-icon <?= $a['jenis'] === 'bank' ? 'ic-blue' : 'ic-gold' ?>"><i class="bi bi-<?= $a['jenis'] === 'bank' ? 'bank' : 'cash-stack' ?>"></i></div>
                <div class="min-w-0">
                    <div class="fw-semibold text-truncate"><?= e($a['nama']) ?></div>
                    <div class="akun-meta"><?= $a['jenis'] === 'bank' ? e($a['nama_bank'] . ' · ' . $a['no_rekening']) : 'Kas tunai' ?></div>
                    <div class="akun-saldo"><?= rupiah($a['saldo']) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><h2>Arus Kas Bulanan <?= $tahun ?></h2><span class="ms-auto small text-muted">Masuk <?= rupiah($thn['masuk']) ?> &middot; Keluar <?= rupiah($thn['keluar']) ?></span></div>
            <div class="card-body"><div style="height:290px"><canvas id="chartBulanan" aria-label="Grafik arus kas bulanan"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h2>Komposisi Pengeluaran</h2></div>
            <div class="card-body">
                <?php if ($perKategori): ?>
                    <div style="height:290px"><canvas id="chartKategori" aria-label="Grafik pengeluaran per kategori"></canvas></div>
                <?php else: ?>
                    <div class="empty-state"><i class="bi bi-pie-chart"></i>Belum ada pengeluaran pada tahun ini.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><h2>Transaksi Terakhir</h2><a href="<?= url('laporan', ['tab' => 'jurnal']) ?>" class="ms-auto small">Lihat semua</a></div>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Tanggal</th><th>Keterangan</th><th>Akun</th><th class="text-end">Jumlah</th></tr></thead>
                <tbody>
                <?php foreach ($terakhir as $t): ?>
                    <tr>
                        <td class="text-nowrap"><?= tgl_indo($t['tanggal']) ?><div class="small text-muted"><?= e($t['no_transaksi']) ?></div></td>
                        <td><div class="fw-semibold"><?= e($t['keterangan']) ?></div><div class="small text-muted"><?= e($t['kategori'] ?? 'Transfer antar akun') ?></div></td>
                        <td class="small"><?= e($t['akun']) ?><?= $t['tipe'] === 'transfer' ? ' <i class="bi bi-arrow-right"></i> ' . e($t['akun_tujuan']) : '' ?></td>
                        <td class="text-end num fw-semibold text-nowrap <?= $t['tipe'] === 'masuk' ? 'text-in' : ($t['tipe'] === 'keluar' ? 'text-out' : '') ?>">
                            <?= $t['tipe'] === 'masuk' ? '+' : ($t['tipe'] === 'keluar' ? '&minus;' : '') ?><?= rupiah($t['jumlah']) ?>
                        </td>
                    </tr>
                <?php endforeach; if (!$terakhir): ?>
                    <tr><td colspan="4"><div class="empty-state"><i class="bi bi-inbox"></i>Belum ada transaksi.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-lg-4 d-flex flex-column gap-3">
        <div class="card">
            <div class="card-header"><h2>Realisasi Anggaran</h2><a href="<?= url('anggaran', ['tahun' => $tahun]) ?>" class="ms-auto small">Detail</a></div>
            <div class="card-body">
                <?php foreach ($realisasi as $r): $pct = $r['anggaran'] > 0 ? $r['realisasi'] / $r['anggaran'] * 100 : 0; ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1"><span class="fw-semibold text-truncate me-2"><?= e($r['nama']) ?></span><span class="num"><?= angka($pct, 1) ?>%</span></div>
                        <div class="progress"><div class="progress-bar <?= $pct > 100 ? 'over' : ($pct > 85 ? 'warn' : '') ?>" style="width: <?= min(100, $pct) ?>%"></div></div>
                        <div class="small text-muted mt-1 num"><?= rupiah($r['realisasi']) ?> dari <?= rupiah($r['anggaran']) ?></div>
                    </div>
                <?php endforeach; if (!$realisasi): ?>
                    <div class="empty-state py-3"><i class="bi bi-pie-chart"></i>Anggaran tahun <?= $tahun ?> belum disusun.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2>Pengajuan Dana Aktif</h2><a href="<?= url('pengajuan') ?>" class="ms-auto small">Kelola</a></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($menunggu as $p): ?>
                    <li class="list-group-item d-flex gap-2 align-items-start py-2">
                        <div class="flex-grow-1 min-w-0">
                            <a href="<?= url('pengajuan', ['id' => $p['id']]) ?>" class="fw-semibold d-block text-truncate text-decoration-none"><?= e($p['judul']) ?></a>
                            <div class="small text-muted"><?= e($p['unit']) ?> &middot; <?= rupiah($p['jumlah_disetujui'] ?? $p['jumlah']) ?></div>
                        </div>
                        <?= status_badge($p['status']) ?>
                    </li>
                <?php endforeach; if (!$menunggu): ?>
                    <li class="list-group-item text-muted small py-3 text-center">Tidak ada pengajuan yang menunggu.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php
$pageScript = '<script>
document.addEventListener("DOMContentLoaded", function () {
    Chart.defaults.font.family = "Plus Jakarta Sans, sans-serif";
    Chart.defaults.color = "#6B746F";
    var fmt = function (v) { return v >= 1e6 ? (v/1e6).toLocaleString("id-ID") + " jt" : v >= 1e3 ? (v/1e3).toLocaleString("id-ID") + " rb" : v; };
    new Chart(document.getElementById("chartBulanan"), {
        type: "bar",
        data: { labels: ' . json_encode($labels) . ',
            datasets: [
                { label: "Pemasukan", data: ' . json_encode(array_values($bulanMasuk)) . ', backgroundColor: "#1E7A4C", borderRadius: 5, maxBarThickness: 20 },
                { label: "Pengeluaran", data: ' . json_encode(array_values($bulanKeluar)) . ', backgroundColor: "#C8962E", borderRadius: 5, maxBarThickness: 20 }
            ] },
        options: { maintainAspectRatio: false, plugins: { legend: { position: "bottom", labels: { usePointStyle: true, boxWidth: 8 } },
            tooltip: { callbacks: { label: function (c) { return c.dataset.label + ": " + rupiah(c.raw); } } } },
            scales: { y: { ticks: { callback: fmt }, grid: { color: "#ECEBE5" } }, x: { grid: { display: false } } } }
    });
    var ck = document.getElementById("chartKategori");
    if (ck) new Chart(ck, {
        type: "doughnut",
        data: { labels: ' . json_encode(array_column($perKategori, 'nama')) . ',
            datasets: [{ data: ' . json_encode(array_map('floatval', array_column($perKategori, 't'))) . ', backgroundColor: ["#173B57","#3C6E91","#86AFCB","#C8962E","#E2BC6A","#A7ADA9"], borderWidth: 2, borderColor: "#fff" }] },
        options: { maintainAspectRatio: false, cutout: "62%", plugins: { legend: { position: "bottom", labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } } },
            tooltip: { callbacks: { label: function (c) { return c.label + ": " + rupiah(c.raw); } } } } }
    });
});
</script>';
require ROOT_PATH . '/includes/footer.php';
