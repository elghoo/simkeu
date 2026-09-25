<?php
/** Master kategori / pos pemasukan & pengeluaran */
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'save') {
        $d = [
            'kode'       => strtoupper(trim($_POST['kode'] ?? '')),
            'nama'       => trim($_POST['nama'] ?? ''),
            'tipe'       => ($_POST['tipe'] ?? '') === 'keluar' ? 'keluar' : 'masuk',
            'keterangan' => trim($_POST['keterangan'] ?? '') ?: null,
            'aktif'      => isset($_POST['aktif']) ? 1 : 0,
        ];
        if ($d['kode'] === '' || $d['nama'] === '') { flash('danger', 'Kode dan nama wajib diisi.'); redirect(url('kategori')); }
        $st = $pdo->prepare('SELECT COUNT(*) FROM kategori WHERE kode = ? AND id <> ?');
        $st->execute([$d['kode'], $id]);
        if ($st->fetchColumn()) { flash('danger', 'Kode ' . $d['kode'] . ' sudah digunakan.'); redirect(url('kategori', $id ? ['id' => $id] : [])); }
        if ($id) {
            $st = $pdo->prepare('SELECT tipe, (SELECT COUNT(*) FROM transaksi WHERE kategori_id = k.id) + (SELECT COUNT(*) FROM pengajuan WHERE kategori_id = k.id) n FROM kategori k WHERE id = ?');
            $st->execute([$id]);
            $old = $st->fetch();
            if ($old && $old['tipe'] !== $d['tipe'] && $old['n'] > 0) { flash('danger', 'Tipe kategori tidak dapat diubah karena sudah dipakai transaksi.'); redirect(url('kategori', ['id' => $id])); }
            $pdo->prepare('UPDATE kategori SET kode=:kode, nama=:nama, tipe=:tipe, keterangan=:keterangan, aktif=:aktif WHERE id=:id')->execute($d + ['id' => $id]);
            log_aktivitas('ubah_kategori', $d['kode'] . ' ' . $d['nama']);
        } else {
            $pdo->prepare('INSERT INTO kategori (kode, nama, tipe, keterangan, aktif) VALUES (:kode, :nama, :tipe, :keterangan, :aktif)')->execute($d);
            log_aktivitas('tambah_kategori', $d['kode'] . ' ' . $d['nama']);
        }
        flash('success', 'Kategori berhasil disimpan.');
        redirect(url('kategori'));
    }
    if ($action === 'delete') {
        $st = $pdo->prepare('SELECT (SELECT COUNT(*) FROM transaksi WHERE kategori_id = ?) + (SELECT COUNT(*) FROM pengajuan WHERE kategori_id = ?)');
        $st->execute([$id, $id]);
        if ($st->fetchColumn() > 0) {
            flash('warning', 'Kategori sudah dipakai transaksi atau pengajuan. Nonaktifkan saja bila tidak digunakan lagi.');
        } else {
            $pdo->prepare('DELETE FROM anggaran WHERE kategori_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM kategori WHERE id = ?')->execute([$id]);
            log_aktivitas('hapus_kategori', 'ID ' . $id);
            flash('success', 'Kategori dihapus.');
        }
        redirect(url('kategori'));
    }
}

$edit = null;
if (!empty($_GET['id'])) { $st = $pdo->prepare('SELECT * FROM kategori WHERE id = ?'); $st->execute([(int)$_GET['id']]); $edit = $st->fetch() ?: null; }
$edit = $edit ?: ['id' => 0, 'kode' => '', 'nama' => '', 'tipe' => $_GET['tipe'] ?? 'keluar', 'keterangan' => '', 'aktif' => 1];
$yr = (int)date('Y');
$rows = $pdo->query("SELECT k.*, (SELECT COUNT(*) FROM transaksi WHERE kategori_id = k.id) trx,
    (SELECT COALESCE(SUM(jumlah),0) FROM transaksi WHERE kategori_id = k.id AND YEAR(tanggal) = $yr) total FROM kategori k ORDER BY k.tipe DESC, k.kode")->fetchAll();

$title = 'Kategori / Pos';
$subtitle = 'Pos pemasukan dan pengeluaran (mata anggaran)';
require ROOT_PATH . '/includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Kode</th><th>Nama pos</th><th>Tipe</th><th class="text-end">Total <?= $yr ?></th><th class="text-end">Trx</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr class="<?= $r['aktif'] ? '' : 'opacity-50' ?>">
                <td><span class="badge-soft bs-gold"><?= e($r['kode']) ?></span></td>
                <td><div class="fw-semibold"><?= e($r['nama']) ?><?= $r['aktif'] ? '' : ' <span class="badge-soft bs-gray">Nonaktif</span>' ?></div><div class="small text-muted"><?= e($r['keterangan'] ?: '') ?></div></td>
                <td><span class="badge-soft <?= $r['tipe'] === 'masuk' ? 'bs-in' : 'bs-red' ?>"><?= tipe_label($r['tipe']) ?></span></td>
                <td class="text-end num"><?= rupiah($r['total']) ?></td>
                <td class="text-end num"><?= $r['trx'] ?></td>
                <td class="table-actions">
                    <a href="<?= url('kategori', ['id' => $r['id']]) ?>" class="btn btn-light btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>
                    <form method="post" class="d-inline" data-confirm="Hapus kategori <?= e($r['nama']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-light btn-sm" title="Hapus"><i class="bi bi-trash text-danger"></i></button></form>
                </td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div></div></div>
    <div class="col-lg-4"><div class="card">
        <div class="card-header"><h2><?= $edit['id'] ? 'Edit Kategori' : 'Tambah Kategori' ?></h2></div>
        <form method="post" class="card-body">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
            <div class="mb-3"><label class="form-label" for="tipe">Tipe</label><select id="tipe" name="tipe" class="form-select"><option value="masuk" <?= selected('masuk', $edit['tipe']) ?>>Pemasukan</option><option value="keluar" <?= selected('keluar', $edit['tipe']) ?>>Pengeluaran</option></select></div>
            <div class="mb-3"><label class="form-label" for="kode">Kode</label><input id="kode" name="kode" class="form-control" maxlength="10" value="<?= e($edit['kode']) ?>" required placeholder="PK-11"></div>
            <div class="mb-3"><label class="form-label" for="nama">Nama pos</label><input id="nama" name="nama" class="form-control" maxlength="100" value="<?= e($edit['nama']) ?>" required></div>
            <div class="mb-3"><label class="form-label" for="ket">Keterangan</label><input id="ket" name="keterangan" class="form-control" maxlength="255" value="<?= e($edit['keterangan']) ?>"></div>
            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="aktif" name="aktif" <?= $edit['aktif'] ? 'checked' : '' ?>><label class="form-check-label" for="aktif">Aktif</label></div>
            <div class="d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button><?php if ($edit['id']): ?><a href="<?= url('kategori') ?>" class="btn btn-light">Batal</a><?php endif; ?></div>
        </form>
    </div></div>
</div>
<?php require ROOT_PATH . '/includes/footer.php';
