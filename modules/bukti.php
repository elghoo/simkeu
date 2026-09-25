<?php
/** Cetak Bukti Kas Masuk (BKM), Bukti Kas Keluar (BKK), dan Bukti Pemindahbukuan (tanpa layout) */
$pdo = db();
$st = $pdo->prepare('SELECT t.*, a.nama akun, a.kode akun_kode, a.nama_bank, a.no_rekening, at.nama akun_tujuan, at.kode akun_tujuan_kode,
        k.nama kategori, k.kode kategori_kode, u.nama unit, us.nama petugas
    FROM transaksi t JOIN akun_kas a ON a.id = t.akun_id LEFT JOIN akun_kas at ON at.id = t.akun_tujuan_id
    LEFT JOIN kategori k ON k.id = t.kategori_id LEFT JOIN unit u ON u.id = t.unit_id LEFT JOIN users us ON us.id = t.user_id WHERE t.id = ?');
$st->execute([(int)($_GET['id'] ?? 0)]);
$r = $st->fetch();
if (!$r) { flash('danger', 'Transaksi tidak ditemukan.'); redirect(url()); }

$judul = ['masuk' => 'Bukti Kas Masuk', 'keluar' => 'Bukti Kas Keluar', 'transfer' => 'Bukti Pemindahbukuan'][$r['tipe']];
$kota = setting('kota', '');
$pimpinan = setting('nama_pimpinan', '....................');
$bendahara = setting('nama_bendahara', $r['petugas'] ?: '....................');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title><?= e($judul . ' ' . $r['no_transaksi']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body class="print-page">
<div class="print-sheet" style="max-width:800px">
    <div class="no-print d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary btn-sm">Cetak</button>
        <button onclick="window.close()" class="btn btn-light btn-sm">Tutup</button>
    </div>
    <div class="kwitansi">
        <div class="kop">
            <?= logo_svg(52) ?>
            <div>
                <h1><?= e(setting('nama_lembaga')) ?></h1>
                <p><?= e(setting('alamat')) ?><?= setting('telepon') ? ' &middot; Telp. ' . e(setting('telepon')) : '' ?><?= setting('email') ? ' &middot; ' . e(setting('email')) : '' ?></p>
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="fs-5 fw-bold mb-0 text-uppercase" style="letter-spacing:.08em"><?= $judul ?></h2>
            <div class="text-end small">No: <b><?= e($r['no_transaksi']) ?></b><br><?= tgl_indo($r['tanggal'], true) ?></div>
        </div>
        <table>
            <?php if ($r['tipe'] === 'transfer'): ?>
                <tr><td style="width:170px">Dari akun</td><td style="width:10px">:</td><td><b><?= e($r['akun_kode'] . ' — ' . $r['akun']) ?></b></td></tr>
                <tr><td>Ke akun</td><td>:</td><td><b><?= e($r['akun_tujuan_kode'] . ' — ' . $r['akun_tujuan']) ?></b></td></tr>
            <?php else: ?>
                <tr><td style="width:170px"><?= $r['tipe'] === 'masuk' ? 'Telah terima dari' : 'Dibayarkan kepada' ?></td><td style="width:10px">:</td><td><b><?= e($r['pihak'] ?: '-') ?></b></td></tr>
                <tr><td>Kategori / pos</td><td>:</td><td><?= e($r['kategori_kode'] . ' — ' . $r['kategori']) ?><?= $r['unit'] ? ' &middot; ' . e($r['unit']) : '' ?></td></tr>
                <tr><td><?= $r['tipe'] === 'masuk' ? 'Disetor ke' : 'Dibayar dari' ?></td><td>:</td><td><?= e($r['akun']) ?><?= $r['no_rekening'] ? ' (' . e($r['nama_bank'] . ' ' . $r['no_rekening']) . ')' : '' ?></td></tr>
            <?php endif; ?>
            <tr><td>Uraian</td><td>:</td><td><?= e($r['keterangan']) ?></td></tr>
            <?php if ($r['no_referensi']): ?><tr><td>No. referensi</td><td>:</td><td><?= e($r['no_referensi']) ?></td></tr><?php endif; ?>
            <tr><td>Terbilang</td><td>:</td><td><i><?= e(ucwords(terbilang($r['jumlah']))) ?> Rupiah</i></td></tr>
        </table>
        <div class="mt-3"><div class="nominal"><?= rupiah($r['jumlah']) ?></div></div>

        <div class="ttd" style="margin-top:28px">
            <?php if ($r['tipe'] === 'masuk'): ?>
                <div><div>Penyetor,</div><div class="space"></div><b><?= e($r['pihak'] ?: '....................') ?></b></div>
                <div><div><?= e($kota ? $kota . ', ' : '') . tgl_indo($r['tanggal']) ?></div><div><?= e(setting('jabatan_bendahara', 'Bendahara')) ?>,</div><div class="space" style="height:46px"></div><b><?= e($bendahara) ?></b></div>
            <?php elseif ($r['tipe'] === 'keluar'): ?>
                <div><div>Menyetujui,</div><div><?= e(setting('jabatan_pimpinan', 'Pimpinan')) ?></div><div class="space" style="height:46px"></div><b><?= e($pimpinan) ?></b></div>
                <div><div>&nbsp;</div><div><?= e(setting('jabatan_bendahara', 'Bendahara')) ?>,</div><div class="space" style="height:46px"></div><b><?= e($bendahara) ?></b></div>
                <div><div><?= e($kota ? $kota . ', ' : '') . tgl_indo($r['tanggal']) ?></div><div>Penerima,</div><div class="space" style="height:46px"></div><b><?= e($r['pihak'] ?: '....................') ?></b></div>
            <?php else: ?>
                <div><div>Mengetahui,</div><div><?= e(setting('jabatan_pimpinan', 'Pimpinan')) ?></div><div class="space" style="height:46px"></div><b><?= e($pimpinan) ?></b></div>
                <div><div><?= e($kota ? $kota . ', ' : '') . tgl_indo($r['tanggal']) ?></div><div><?= e(setting('jabatan_bendahara', 'Bendahara')) ?>,</div><div class="space" style="height:46px"></div><b><?= e($bendahara) ?></b></div>
            <?php endif; ?>
        </div>
    </div>
    <p class="small text-muted mt-2">Dicetak <?= date('d/m/Y H:i') ?> oleh <?= e(user('nama')) ?> &middot; <?= APP_NAME ?></p>
</div>
<script>if (location.hash !== '#noprint') setTimeout(function () { window.print(); }, 400);</script>
</body>
</html>
