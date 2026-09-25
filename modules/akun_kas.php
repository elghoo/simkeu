<?php
/** Master akun kas & rekening bank */
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'save') {
        $d = [
            'kode'        => strtoupper(trim($_POST['kode'] ?? '')),
            'nama'        => trim($_POST['nama'] ?? ''),
            'jenis'       => ($_POST['jenis'] ?? '') === 'bank' ? 'bank' : 'kas',
            'nama_bank'   => trim($_POST['nama_bank'] ?? '') ?: null,
            'no_rekening' => trim($_POST['no_rekening'] ?? '') ?: null,
            'atas_nama'   => trim($_POST['atas_nama'] ?? '') ?: null,
            'saldo_awal'  => round(parse_angka($_POST['saldo_awal'] ?? '0'), 2),
            'aktif'       => isset($_POST['aktif']) ? 1 : 0,
        ];
        if ($d['jenis'] === 'kas') $d['nama_bank'] = $d['no_rekening'] = $d['atas_nama'] = null;
        if ($d['kode'] === '' || $d['nama'] === '') { flash('danger', 'Kode dan nama akun wajib diisi.'); redirect(url('akun_kas', $id ? ['id' => $id] : [])); }
        $st = $pdo->prepare('SELECT COUNT(*) FROM akun_kas WHERE kode = ? AND id <> ?');
        $st->execute([$d['kode'], $id]);
        if ($st->fetchColumn()) { flash('danger', 'Kode ' . $d['kode'] . ' sudah digunakan.'); redirect(url('akun_kas', $id ? ['id' => $id] : [])); }
        if ($id) {
            // Pastikan perubahan saldo awal tidak membuat saldo harian negatif
            $st = $pdo->prepare('SELECT saldo_awal FROM akun_kas WHERE id = ?');
            $st->execute([$id]);
            $lama = (float)$st->fetchColumn();
            if ($d['saldo_awal'] < $lama && saldo_min_sejak($id, '1970-01-01') - ($lama - $d['saldo_awal']) < 0) {
                flash('danger', 'Saldo awal terlalu kecil: saldo akun akan menjadi negatif pada riwayat transaksi.');
                redirect(url('akun_kas', ['id' => $id]));
            }
            $pdo->prepare('UPDATE akun_kas SET kode=:kode, nama=:nama, jenis=:jenis, nama_bank=:nama_bank, no_rekening=:no_rekening, atas_nama=:atas_nama, saldo_awal=:saldo_awal, aktif=:aktif WHERE id=:id')->execute($d + ['id' => $id]);
            log_aktivitas('ubah_akun', $d['kode'] . ' ' . $d['nama']);
        } else {
            $pdo->prepare('INSERT INTO akun_kas (kode, nama, jenis, nama_bank, no_rekening, atas_nama, saldo_awal, aktif) VALUES (:kode, :nama, :jenis, :nama_bank, :no_rekening, :atas_nama, :saldo_awal, :aktif)')->execute($d);
            log_aktivitas('tambah_akun', $d['kode'] . ' ' . $d['nama']);
        }
        flash('success', 'Akun ' . $d['nama'] . ' berhasil disimpan.');
        redirect(url('akun_kas'));
    }
    if ($action === 'delete') {
        $st = $pdo->prepare('SELECT COUNT(*) FROM transaksi WHERE akun_id = ? OR akun_tujuan_id = ?');
        $st->execute([$id, $id]);
        if ($st->fetchColumn() > 0) {
            flash('warning', 'Akun sudah memiliki transaksi sehingga tidak dapat dihapus. Nonaktifkan saja bila tidak digunakan lagi.');
        } else {
            $pdo->prepare('DELETE FROM akun_kas WHERE id = ?')->execute([$id]);
            log_aktivitas('hapus_akun', 'ID ' . $id);
            flash('success', 'Akun dihapus.');
        }
        redirect(url('akun_kas'));
    }
}

$edit = null;
if (!empty($_GET['id'])) { $st = $pdo->prepare('SELECT * FROM akun_kas WHERE id = ?'); $st->execute([(int)$_GET['id']]); $edit = $st->fetch() ?: null; }
$edit = $edit ?: ['id' => 0, 'kode' => '', 'nama' => '', 'jenis' => 'kas', 'nama_bank' => '', 'no_rekening' => '', 'atas_nama' => '', 'saldo_awal' => 0, 'aktif' => 1];
$rows = $pdo->query('SELECT a.*, (SELECT COUNT(*) FROM transaksi WHERE akun_id = a.id OR akun_tujuan_id = a.id) trx FROM akun_kas a ORDER BY a.kode')->fetchAll();
$saldo = saldo_akun();

$title = 'Akun Kas & Bank';
$subtitle = 'Kas tunai dan rekening bank lembaga';
require ROOT_PATH . '/includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Kode</th><th>Akun</th><th class="text-end">Saldo Awal</th><th class="text-end">Saldo Saat Ini</th><th class="text-end">Trx</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr class="<?= $r['aktif'] ? '' : 'opacity-50' ?>">
                <td><span class="badge-soft <?= $r['jenis'] === 'bank' ? 'bs-blue' : 'bs-gold' ?>"><?= e($r['kode']) ?></span></td>
                <td><div class="fw-semibold"><i class="bi bi-<?= $r['jenis'] === 'bank' ? 'bank' : 'cash-stack' ?> me-1 text-muted"></i><?= e($r['nama']) ?><?= $r['aktif'] ? '' : ' <span class="badge-soft bs-gray">Nonaktif</span>' ?></div>
                    <?php if ($r['jenis'] === 'bank'): ?><div class="small text-muted"><?= e(trim($r['nama_bank'] . ' ' . $r['no_rekening'])) ?><?= $r['atas_nama'] ? ' a.n. ' . e($r['atas_nama']) : '' ?></div><?php endif; ?></td>
                <td class="text-end num"><?= rupiah($r['saldo_awal']) ?></td>
                <td class="text-end num fw-semibold"><?= rupiah($saldo[$r['id']]['saldo'] ?? $r['saldo_awal']) ?></td>
                <td class="text-end num"><?= $r['trx'] ?></td>
                <td class="table-actions">
                    <a href="<?= url('laporan', ['tab' => 'bukukas', 'akun' => $r['id']]) ?>" class="btn btn-light btn-sm" title="Buku kas"><i class="bi bi-journal-text"></i></a>
                    <a href="<?= url('akun_kas', ['id' => $r['id']]) ?>" class="btn btn-light btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>
                    <form method="post" class="d-inline" data-confirm="Hapus akun <?= e($r['nama']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-light btn-sm" title="Hapus"><i class="bi bi-trash text-danger"></i></button></form>
                </td>
            </tr>
        <?php endforeach; ?></tbody>
        <tfoot><tr><td colspan="3">Total saldo akun aktif</td><td class="text-end num"><?= rupiah(array_sum(array_column($saldo, 'saldo'))) ?></td><td colspan="2"></td></tr></tfoot>
    </table></div></div></div>
    <div class="col-lg-4"><div class="card">
        <div class="card-header"><h2><?= $edit['id'] ? 'Edit Akun' : 'Tambah Akun' ?></h2></div>
        <form method="post" class="card-body">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
            <div class="row g-2 mb-3">
                <div class="col-5"><label class="form-label" for="kode">Kode</label><input id="kode" name="kode" class="form-control" maxlength="10" value="<?= e($edit['kode']) ?>" required placeholder="BNK-03"></div>
                <div class="col-7"><label class="form-label" for="jenis">Jenis</label><select id="jenis" name="jenis" class="form-select" onchange="document.getElementById('bankFields').hidden = this.value !== 'bank'"><option value="kas" <?= selected('kas', $edit['jenis']) ?>>Kas tunai</option><option value="bank" <?= selected('bank', $edit['jenis']) ?>>Rekening bank</option></select></div>
            </div>
            <div class="mb-3"><label class="form-label" for="nama">Nama akun</label><input id="nama" name="nama" class="form-control" maxlength="100" value="<?= e($edit['nama']) ?>" required></div>
            <div id="bankFields" <?= $edit['jenis'] === 'bank' ? '' : 'hidden' ?>>
                <div class="mb-3"><label class="form-label" for="nb">Nama bank</label><input id="nb" name="nama_bank" class="form-control" maxlength="100" value="<?= e($edit['nama_bank']) ?>"></div>
                <div class="mb-3"><label class="form-label" for="nr">No. rekening</label><input id="nr" name="no_rekening" class="form-control" maxlength="50" value="<?= e($edit['no_rekening']) ?>"></div>
                <div class="mb-3"><label class="form-label" for="an">Atas nama</label><input id="an" name="atas_nama" class="form-control" maxlength="100" value="<?= e($edit['atas_nama']) ?>"></div>
            </div>
            <div class="mb-3"><label class="form-label" for="sa">Saldo awal</label><div class="input-group"><span class="input-group-text">Rp</span><input id="sa" name="saldo_awal" class="form-control input-rupiah" inputmode="numeric" value="<?= (int)$edit['saldo_awal'] ?>"></div><div class="form-text">Saldo saat akun mulai dicatat di sistem.</div></div>
            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="aktif" name="aktif" <?= $edit['aktif'] ? 'checked' : '' ?>><label class="form-check-label" for="aktif">Aktif</label></div>
            <div class="d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button><?php if ($edit['id']): ?><a href="<?= url('akun_kas') ?>" class="btn btn-light">Batal</a><?php endif; ?></div>
        </form>
    </div></div>
</div>
<?php require ROOT_PATH . '/includes/footer.php';
