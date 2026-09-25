<?php
/**
 * Laporan keuangan: jurnal, buku kas, arus kas (penerimaan & pengeluaran), realisasi anggaran, per unit.
 * Setiap laporan bisa ditampilkan, dicetak (print=1), atau diekspor ke CSV (export=csv).
 */
$pdo = db();
$tabs = [
    'aruskas'  => ['Penerimaan & Pengeluaran', 'bar-chart-steps'],
    'bukukas'  => ['Buku Kas', 'journal-text'],
    'jurnal'   => ['Jurnal Transaksi', 'list-ul'],
    'anggaran' => ['Realisasi Anggaran', 'pie-chart'],
    'unit'     => ['Per Unit Kerja', 'diagram-3'],
];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'aruskas';
$dari   = valid_date($_GET['dari'] ?? '', date('Y-01-01'));
$sampai = valid_date($_GET['sampai'] ?? '', date('Y-m-d'));
if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];
$tahun  = (int)($_GET['tahun'] ?? date('Y'));
$akunList = akun_all(false);
$fAkun  = (int)($_GET['akun'] ?? 0);
$fTipe  = in_array($_GET['tipe'] ?? '', ['masuk', 'keluar', 'transfer'], true) ? $_GET['tipe'] : '';
$print  = !empty($_GET['print']);
$export = ($_GET['export'] ?? '') === 'csv';
if ($tab === 'bukukas' && !$fAkun && $akunList) $fAkun = (int)$akunList[0]['id'];

$periodeText = $tab === 'anggaran' ? 'Tahun Anggaran ' . $tahun : 'Periode ' . tgl_indo($dari) . ' s.d. ' . tgl_indo($sampai);
$cols = [];   // [label, numeric?]
function pct($a, $b): string { return $b > 0 ? angka($a / $b * 100, 1) . '%' : '-'; }
$nowrap = ['Tanggal', 'No. Bukti'];
$rows = [];   // ['c' => [...], 'cls' => '']
$foot = null;
$notes = [];

/* ---------------- Penyusunan data ---------------- */
if ($tab === 'aruskas') {
    $judul = 'Laporan Penerimaan dan Pengeluaran Kas';
    $awal = saldo_akun(date('Y-m-d', strtotime($dari . ' -1 day')));
    $akhir = saldo_akun($sampai);
    $st = $pdo->prepare("SELECT k.kode, k.nama, k.tipe, COALESCE(SUM(t.jumlah),0) total, COUNT(t.id) n
        FROM kategori k LEFT JOIN transaksi t ON t.kategori_id = k.id AND t.tanggal BETWEEN ? AND ?
        GROUP BY k.id HAVING total > 0 OR MAX(k.aktif) = 1 ORDER BY k.tipe DESC, k.kode");
    $st->execute([$dari, $sampai]);
    $kat = ['masuk' => [], 'keluar' => []];
    foreach ($st as $r) $kat[$r['tipe']][] = $r;
    $cols = [['Kode', false], ['Uraian', false], ['Jml Trx', true], ['Jumlah (Rp)', true]];
    $tAwal = array_sum(array_column($awal, 'saldo'));
    $rows[] = ['c' => ['', 'SALDO AWAL KAS & BANK per ' . tgl_indo(date('Y-m-d', strtotime($dari . ' -1 day'))), '', $tAwal], 'cls' => 'sub'];
    $tot = [];
    foreach (['masuk' => 'PENERIMAAN', 'keluar' => 'PENGELUARAN'] as $t => $lbl) {
        $rows[] = ['c' => ['', $lbl, '', ''], 'cls' => 'section'];
        $tot[$t] = 0;
        foreach ($kat[$t] as $r) {
            $rows[] = ['c' => [$r['kode'], $r['nama'], (int)$r['n'], (float)$r['total']]];
            $tot[$t] += $r['total'];
        }
        $rows[] = ['c' => ['', 'Jumlah ' . strtolower($lbl), '', $tot[$t]], 'cls' => 'sub'];
    }
    $rows[] = ['c' => ['', 'SURPLUS / (DEFISIT) PERIODE INI', '', $tot['masuk'] - $tot['keluar']], 'cls' => 'sub'];
    $foot = ['', 'SALDO AKHIR KAS & BANK per ' . tgl_indo($sampai), '', array_sum(array_column($akhir, 'saldo'))];
    foreach ($akhir as $id => $a) $notes[] = [$a['nama'], $awal[$id]['saldo'], $a['saldo']];
}

if ($tab === 'bukukas') {
    $akun = null;
    foreach ($akunList as $a) if ($a['id'] == $fAkun) $akun = $a;
    $judul = 'Buku Kas' . ($akun ? ' — ' . $akun['nama'] : '');
    $saldoAwal = saldo_akun(date('Y-m-d', strtotime($dari . ' -1 day')))[$fAkun]['saldo'] ?? 0;
    $st = $pdo->prepare("SELECT t.*, k.nama kategori, a.nama akun_asal, b.nama akun_tujuan FROM transaksi t
        LEFT JOIN kategori k ON k.id = t.kategori_id JOIN akun_kas a ON a.id = t.akun_id LEFT JOIN akun_kas b ON b.id = t.akun_tujuan_id
        WHERE (t.akun_id = ? OR t.akun_tujuan_id = ?) AND t.tanggal BETWEEN ? AND ? ORDER BY t.tanggal, t.id");
    $st->execute([$fAkun, $fAkun, $dari, $sampai]);
    $cols = [['Tanggal', false], ['No. Bukti', false], ['Uraian', false], ['Debet / Masuk', true], ['Kredit / Keluar', true], ['Saldo', true]];
    $run = $saldoAwal;
    $rows[] = ['c' => [tgl_indo($dari), '', 'Saldo awal', '', '', $run], 'cls' => 'sub'];
    $tm = $tk = 0;
    foreach ($st as $r) {
        $in = ($r['tipe'] === 'masuk' || ($r['tipe'] === 'transfer' && $r['akun_tujuan_id'] == $fAkun)) ? (float)$r['jumlah'] : 0;
        $out = $in ? 0 : (float)$r['jumlah'];
        $run += $in - $out;
        $tm += $in; $tk += $out;
        $ur = $r['keterangan'] . ($r['tipe'] === 'transfer' ? ($in ? ' (dari ' . $r['akun_asal'] . ')' : ' (ke ' . $r['akun_tujuan'] . ')') : ' [' . $r['kategori'] . ']');
        $rows[] = ['c' => [tgl_indo($r['tanggal']), $r['no_transaksi'], $ur, $in ?: '', $out ?: '', $run]];
    }
    $foot = ['', '', 'Jumlah / saldo akhir', $tm, $tk, $run];
}

if ($tab === 'jurnal') {
    $judul = 'Jurnal Transaksi Kas' . ($fTipe ? ' — ' . tipe_label($fTipe) : '');
    $w = ['t.tanggal BETWEEN ? AND ?'];
    $p = [$dari, $sampai];
    if ($fTipe) { $w[] = 't.tipe = ?'; $p[] = $fTipe; }
    if ($fAkun) { $w[] = '(t.akun_id = ? OR t.akun_tujuan_id = ?)'; $p[] = $fAkun; $p[] = $fAkun; }
    $st = $pdo->prepare('SELECT t.*, k.nama kategori, a.nama akun, b.nama akun_tujuan, u.nama unit FROM transaksi t JOIN akun_kas a ON a.id = t.akun_id
        LEFT JOIN akun_kas b ON b.id = t.akun_tujuan_id LEFT JOIN kategori k ON k.id = t.kategori_id LEFT JOIN unit u ON u.id = t.unit_id
        WHERE ' . implode(' AND ', $w) . ' ORDER BY t.tanggal, t.id');
    $st->execute($p);
    $cols = [['Tanggal', false], ['No. Bukti', false], ['Uraian', false], ['Kategori', false], ['Akun', false], ['Masuk', true], ['Keluar', true]];
    $tm = $tk = 0;
    foreach ($st as $r) {
        $in = $r['tipe'] === 'masuk' ? (float)$r['jumlah'] : 0;
        $out = $r['tipe'] === 'keluar' ? (float)$r['jumlah'] : 0;
        $tm += $in; $tk += $out;
        $rows[] = ['c' => [tgl_indo($r['tanggal']), $r['no_transaksi'], $r['keterangan'] . ($r['pihak'] ? ' — ' . $r['pihak'] : ''),
            $r['tipe'] === 'transfer' ? 'Transfer' : $r['kategori'], $r['tipe'] === 'transfer' ? $r['akun'] . ' → ' . $r['akun_tujuan'] : $r['akun'],
            $r['tipe'] === 'transfer' ? (float)$r['jumlah'] : ($in ?: ''), $r['tipe'] === 'transfer' ? (float)$r['jumlah'] : ($out ?: '')],
            'cls' => $r['tipe'] === 'transfer' ? 'muted' : ''];
    }
    $foot = ['', '', 'Jumlah (tidak termasuk transfer)', '', '', $tm, $tk];
}

if ($tab === 'anggaran') {
    $judul = 'Laporan Realisasi Anggaran';
    $st = $pdo->prepare("SELECT k.kode, k.nama, k.tipe, COALESCE(a.jumlah,0) anggaran,
            COALESCE((SELECT SUM(jumlah) FROM transaksi t WHERE t.kategori_id = k.id AND YEAR(t.tanggal) = ?),0) realisasi
        FROM kategori k LEFT JOIN anggaran a ON a.kategori_id = k.id AND a.tahun = ? WHERE k.aktif = 1 OR a.jumlah > 0 ORDER BY k.tipe DESC, k.kode");
    $st->execute([$tahun, $tahun]);
    $cols = [['Kode', false], ['Uraian', false], ['Anggaran', true], ['Realisasi', true], ['Selisih', true], ['%', true]];
    $g = ['masuk' => [0, 0], 'keluar' => [0, 0]];
    $data = $st->fetchAll();
    foreach (['masuk' => 'PENDAPATAN', 'keluar' => 'BELANJA'] as $t => $lbl) {
        $rows[] = ['c' => ['', $lbl, '', '', '', ''], 'cls' => 'section'];
        foreach ($data as $r) {
            if ($r['tipe'] !== $t) continue;
            $g[$t][0] += $r['anggaran']; $g[$t][1] += $r['realisasi'];
            $rows[] = ['c' => [$r['kode'], $r['nama'], (float)$r['anggaran'], (float)$r['realisasi'], $r['anggaran'] - $r['realisasi'], pct($r['realisasi'], $r['anggaran'])]];
        }
        $rows[] = ['c' => ['', 'Jumlah ' . strtolower($lbl), $g[$t][0], $g[$t][1], $g[$t][0] - $g[$t][1], pct($g[$t][1], $g[$t][0])], 'cls' => 'sub'];
    }
    $foot = ['', 'SURPLUS / (DEFISIT)', $g['masuk'][0] - $g['keluar'][0], $g['masuk'][1] - $g['keluar'][1], '', ''];
}

if ($tab === 'unit') {
    $judul = 'Laporan Pengeluaran per Unit Kerja';
    $st = $pdo->prepare("SELECT COALESCE(u.nama, 'Tanpa unit') nama, u.kode,
            SUM(CASE WHEN t.tipe='masuk' THEN t.jumlah ELSE 0 END) masuk, SUM(CASE WHEN t.tipe='keluar' THEN t.jumlah ELSE 0 END) keluar, COUNT(*) n
        FROM transaksi t LEFT JOIN unit u ON u.id = t.unit_id WHERE t.tipe <> 'transfer' AND t.tanggal BETWEEN ? AND ? GROUP BY u.id ORDER BY keluar DESC");
    $st->execute([$dari, $sampai]);
    $cols = [['Kode', false], ['Unit Kerja', false], ['Jml Trx', true], ['Pemasukan', true], ['Pengeluaran', true], ['Porsi Belanja', true]];
    $data = $st->fetchAll();
    $tk = array_sum(array_column($data, 'keluar'));
    $tm = array_sum(array_column($data, 'masuk'));
    foreach ($data as $r) $rows[] = ['c' => [$r['kode'] ?? '-', $r['nama'], (int)$r['n'], (float)$r['masuk'], (float)$r['keluar'], pct($r['keluar'], $tk)]];
    $foot = ['', 'Jumlah', array_sum(array_column($data, 'n')), $tm, $tk, '100%'];
}

/* ---------------- Export CSV ---------------- */
if ($export) {
    log_aktivitas('export_laporan', $judul . ' - ' . $periodeText);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan-' . $tab . '-' . ($tab === 'anggaran' ? $tahun : $dari . '_' . $sampai) . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $sep = ';';
    $fmt = fn($v) => is_float($v) || is_int($v) ? str_replace('.', ',', (string)$v) : $v;
    fputcsv($out, [setting('nama_lembaga')], $sep, '"', '\\');
    fputcsv($out, [$judul, $periodeText], $sep, '"', '\\');
    fputcsv($out, [], $sep, '"', '\\');
    fputcsv($out, array_column($cols, 0), $sep, '"', '\\');
    foreach ($rows as $r) fputcsv($out, array_map($fmt, $r['c']), $sep, '"', '\\');
    if ($foot) fputcsv($out, array_map($fmt, $foot), $sep, '"', '\\');
    fclose($out);
    exit;
}

/** Render sel sesuai tipe kolom */
$cell = function ($v, bool $num) {
    if ($num && (is_float($v) || is_int($v))) {
        return $v < 0 ? '(' . rupiah(abs($v), false) . ')' : rupiah($v, false);
    }
    return e($v);
};
$qs = ['tab' => $tab, 'dari' => $dari, 'sampai' => $sampai, 'tahun' => $tahun, 'akun' => $fAkun ?: null, 'tipe' => $fTipe ?: null];

/* ---------------- Tampilan cetak ---------------- */
if ($print) {
    ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title><?= e($judul) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body class="print-page">
<div class="print-sheet">
    <div class="no-print d-flex gap-2"><button onclick="window.print()" class="btn btn-primary btn-sm">Cetak</button><button onclick="window.close()" class="btn btn-light btn-sm">Tutup</button></div>
    <div class="kop"><?= logo_svg(52) ?><div><h1><?= e(setting('nama_lembaga')) ?></h1><p><?= e(setting('alamat')) ?><?= setting('email') ? ' &middot; ' . e(setting('email')) : '' ?></p></div></div>
    <h2 class="text-center fs-6 fw-bold text-uppercase mb-0"><?= e($judul) ?></h2>
    <p class="text-center mb-3"><?= e($periodeText) ?></p>
    <table class="tbl">
        <thead><tr><?php foreach ($cols as [$l, $n]): ?><th class="<?= $n ? 'num' : '' ?>"><?= e($l) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr class="<?= in_array($r['cls'] ?? '', ['sub', 'section']) ? 'sub' : '' ?>"><?php foreach ($r['c'] as $i => $v): ?><td class="<?= $cols[$i][1] ? 'num' : '' ?>"<?= in_array($cols[$i][0], $nowrap) ? ' style="white-space:nowrap"' : '' ?>><?= $cell($v, $cols[$i][1]) ?></td><?php endforeach; ?></tr>
        <?php endforeach; if (!$rows): ?><tr><td colspan="<?= count($cols) ?>" class="text-center">Tidak ada data.</td></tr><?php endif; ?>
        </tbody>
        <?php if ($foot): ?><tfoot><tr class="sub"><?php foreach ($foot as $i => $v): ?><td class="<?= $cols[$i][1] ? 'num' : '' ?>"><b><?= $cell($v, $cols[$i][1]) ?></b></td><?php endforeach; ?></tr></tfoot><?php endif; ?>
    </table>
    <?php if ($notes): ?>
        <p class="mt-3 mb-1 fw-bold">Rincian saldo per akun</p>
        <table class="tbl" style="max-width:560px"><thead><tr><th>Akun</th><th class="num">Saldo Awal</th><th class="num">Saldo Akhir</th></tr></thead>
            <tbody><?php foreach ($notes as [$n, $a, $b]): ?><tr><td><?= e($n) ?></td><td class="num"><?= rupiah($a, false) ?></td><td class="num"><?= rupiah($b, false) ?></td></tr><?php endforeach; ?></tbody></table>
    <?php endif; ?>
    <div class="ttd">
        <div><div>Mengetahui,</div><div><?= e(setting('jabatan_pimpinan', 'Pimpinan')) ?></div><div class="space"></div><b><?= e(setting('nama_pimpinan', '....................')) ?></b></div>
        <div><div><?= e(setting('kota', '')) ?>, <?= tgl_indo(date('Y-m-d')) ?></div><div><?= e(setting('jabatan_bendahara', 'Bendahara')) ?>,</div><div class="space"></div><b><?= e(setting('nama_bendahara', '....................')) ?></b></div>
    </div>
    <p class="small text-muted mt-4">Dicetak <?= date('d/m/Y H:i') ?> oleh <?= e(user('nama')) ?> &middot; <?= APP_NAME ?></p>
</div>
<script>setTimeout(function () { window.print(); }, 500);</script>
</body>
</html>
    <?php
    return;
}

/* ---------------- Tampilan layar ---------------- */
$title = 'Laporan Keuangan';
$subtitle = $judul . ' · ' . $periodeText;
require ROOT_PATH . '/includes/header.php';
?>
<ul class="nav nav-tabs-line mb-3 flex-nowrap overflow-auto">
    <?php foreach ($tabs as $k => [$l, $ic]): ?>
        <li class="nav-item"><a class="nav-link text-nowrap <?= $tab === $k ? 'active' : '' ?>" href="<?= url('laporan', array_merge($qs, ['tab' => $k, 'akun' => null, 'tipe' => null])) ?>"><i class="bi bi-<?= $ic ?> me-1"></i><?= $l ?></a></li>
    <?php endforeach; ?>
</ul>
<div class="card">
    <div class="card-header">
        <form method="get" class="toolbar flex-grow-1">
            <input type="hidden" name="page" value="laporan"><input type="hidden" name="tab" value="<?= $tab ?>">
            <?php if ($tab === 'anggaran'): ?>
                <select name="tahun" class="form-select form-select-sm" aria-label="Tahun"><?php for ($y = (int)date('Y') + 1; $y >= (int)date('Y') - 4; $y--): ?><option <?= selected($y, $tahun) ?>><?= $y ?></option><?php endfor; ?></select>
            <?php else: ?>
                <input type="date" name="dari" value="<?= e($dari) ?>" class="form-control form-control-sm" aria-label="Dari tanggal">
                <span class="small text-muted">s.d.</span>
                <input type="date" name="sampai" value="<?= e($sampai) ?>" class="form-control form-control-sm" aria-label="Sampai tanggal">
            <?php endif; ?>
            <?php if (in_array($tab, ['bukukas', 'jurnal'])): ?>
                <select name="akun" class="form-select form-select-sm" aria-label="Akun">
                    <?php if ($tab === 'jurnal'): ?><option value="">Semua akun</option><?php endif; ?>
                    <?php foreach ($akunList as $a): ?><option value="<?= $a['id'] ?>" <?= selected($a['id'], $fAkun) ?>><?= e($a['nama']) ?></option><?php endforeach; ?></select>
            <?php endif; ?>
            <?php if ($tab === 'jurnal'): ?>
                <select name="tipe" class="form-select form-select-sm" aria-label="Jenis transaksi"><option value="">Semua jenis</option>
                    <?php foreach (['masuk', 'keluar', 'transfer'] as $t): ?><option value="<?= $t ?>" <?= selected($t, $fTipe) ?>><?= tipe_label($t) ?></option><?php endforeach; ?></select>
            <?php endif; ?>
            <button class="btn btn-sm btn-light"><i class="bi bi-funnel"></i> Tampilkan</button>
        </form>
        <div class="d-flex gap-2">
            <a href="<?= e(url('laporan', $qs + ['print' => 1])) ?>" target="_blank" class="btn btn-sm btn-light"><i class="bi bi-printer me-1"></i>Cetak</a>
            <a href="<?= e(url('laporan', $qs + ['export' => 'csv'])) ?>" class="btn btn-sm btn-light"><i class="bi bi-filetype-csv me-1"></i>Excel (CSV)</a>
        </div>
    </div>
    <div class="table-responsive"><table class="table table-hover table-sm-rows">
        <thead><tr><?php foreach ($cols as [$l, $n]): ?><th class="<?= $n ? 'text-end' : '' ?>"><?= e($l) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $cls = $r['cls'] ?? ''; ?>
            <tr class="<?= $cls === 'sub' ? 'fw-semibold table-light' : ($cls === 'section' ? 'fw-bold' : ($cls === 'muted' ? 'text-muted' : '')) ?>">
                <?php foreach ($r['c'] as $i => $v): ?><td class="<?= $cols[$i][1] ? 'text-end num text-nowrap' : (in_array($cols[$i][0], $nowrap) ? 'text-nowrap' : '') ?>"><?= $cell($v, $cols[$i][1]) ?></td><?php endforeach; ?>
            </tr>
        <?php endforeach; if (!$rows): ?>
            <tr><td colspan="<?= count($cols) ?>"><div class="empty-state"><i class="bi bi-inbox"></i>Tidak ada data pada periode ini.</div></td></tr>
        <?php endif; ?>
        </tbody>
        <?php if ($foot): ?><tfoot><tr><?php foreach ($foot as $i => $v): ?><td class="<?= $cols[$i][1] ? 'text-end num text-nowrap' : '' ?>"><?= $cell($v, $cols[$i][1]) ?></td><?php endforeach; ?></tr></tfoot><?php endif; ?>
    </table></div>
</div>
<?php if ($notes): ?>
<div class="card mt-3">
    <div class="card-header"><h2>Rincian Saldo per Akun</h2></div>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Akun</th><th class="text-end">Saldo Awal</th><th class="text-end">Perubahan</th><th class="text-end">Saldo Akhir</th></tr></thead>
        <tbody><?php foreach ($notes as [$n, $a, $b]): ?><tr><td class="fw-semibold"><?= e($n) ?></td><td class="text-end num"><?= rupiah($a) ?></td><td class="text-end num <?= $b - $a >= 0 ? 'text-in' : 'text-out' ?>"><?= ($b - $a >= 0 ? '+' : '&minus;') . rupiah(abs($b - $a)) ?></td><td class="text-end num fw-semibold"><?= rupiah($b) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
</div>
<?php endif; ?>
<p class="small text-muted mt-2">Angka dalam kurung menunjukkan nilai negatif (defisit). Pada jurnal, baris transfer ditampilkan abu-abu dan tidak dihitung sebagai pemasukan/pengeluaran.</p>
<?php require ROOT_PATH . '/includes/footer.php';
