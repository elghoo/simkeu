<?php
/** Master unit kerja */
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'save') {
        $d = [
            'kode' => strtoupper(trim($_POST['kode'] ?? '')),
            'nama' => trim($_POST['nama'] ?? ''),
            'penanggung_jawab' => trim($_POST['penanggung_jawab'] ?? '') ?: null,
            'aktif' => isset($_POST['aktif']) ? 1 : 0,
        ];
        if ($d['kode'] === '' || $d['nama'] === '') { flash('danger', 'Kode dan nama unit wajib diisi.'); redirect(url('unit')); }
        $st = $pdo->prepare('SELECT COUNT(*) FROM unit WHERE kode = ? AND id <> ?');
        $st->execute([$d['kode'], $id]);
        if ($st->fetchColumn()) { flash('danger', 'Kode ' . $d['kode'] . ' sudah digunakan.'); redirect(url('unit', $id ? ['id' => $id] : [])); }
        if ($id) {
            $pdo->prepare('UPDATE unit SET kode=:kode, nama=:nama, penanggung_jawab=:penanggung_jawab, aktif=:aktif WHERE id=:id')->execute($d + ['id' => $id]);
            log_aktivitas('ubah_unit', $d['kode'] . ' ' . $d['nama']);
        } else {
            $pdo->prepare('INSERT INTO unit (kode, nama, penanggung_jawab, aktif) VALUES (:kode, :nama, :penanggung_jawab, :aktif)')->execute($d);
            log_aktivitas('tambah_unit', $d['kode'] . ' ' . $d['nama']);
        }
        flash('success', 'Unit kerja berhasil disimpan.');
        redirect(url('unit'));
    }
    if ($action === 'delete') {
        $st = $pdo->prepare('SELECT (SELECT COUNT(*) FROM transaksi WHERE unit_id = ?) + (SELECT COUNT(*) FROM pengajuan WHERE unit_id = ?) + (SELECT COUNT(*) FROM users WHERE unit_id = ?)');
        $st->execute([$id, $id, $id]);
        if ($st->fetchColumn() > 0) {
            flash('warning', 'Unit sudah dipakai transaksi, pengajuan, atau pengguna. Nonaktifkan saja bila tidak digunakan lagi.');
        } else {
            $pdo->prepare('DELETE FROM unit WHERE id = ?')->execute([$id]);
            log_aktivitas('hapus_unit', 'ID ' . $id);
            flash('success', 'Unit kerja dihapus.');
        }
        redirect(url('unit'));
    }
}

$edit = null;
if (!empty($_GET['id'])) { $st = $pdo->prepare('SELECT * FROM unit WHERE id = ?'); $st->execute([(int)$_GET['id']]); $edit = $st->fetch() ?: null; }
$edit = $edit ?: ['id' => 0, 'kode' => '', 'nama' => '', 'penanggung_jawab' => '', 'aktif' => 1];
$yr = (int)date('Y');
$rows = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM users WHERE unit_id = u.id) pengguna,
    (SELECT COUNT(*) FROM pengajuan WHERE unit_id = u.id) pgj,
    (SELECT COALESCE(SUM(jumlah),0) FROM transaksi WHERE unit_id = u.id AND tipe = 'keluar' AND YEAR(tanggal) = $yr) belanja FROM unit u ORDER BY u.kode")->fetchAll();

$title = 'Unit Kerja';
$subtitle = 'Bagian / program studi yang menggunakan anggaran';
require ROOT_PATH . '/includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Kode</th><th>Unit kerja</th><th class="text-end">Belanja <?= $yr ?></th><th class="text-end">Pengajuan</th><th class="text-end">Pengguna</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr class="<?= $r['aktif'] ? '' : 'opacity-50' ?>">
                <td><span class="badge-soft bs-blue"><?= e($r['kode']) ?></span></td>
                <td><div class="fw-semibold"><?= e($r['nama']) ?><?= $r['aktif'] ? '' : ' <span class="badge-soft bs-gray">Nonaktif</span>' ?></div><div class="small text-muted"><?= $r['penanggung_jawab'] ? 'PJ: ' . e($r['penanggung_jawab']) : '' ?></div></td>
                <td class="text-end num"><?= rupiah($r['belanja']) ?></td>
                <td class="text-end num"><?= $r['pgj'] ?></td>
                <td class="text-end num"><?= $r['pengguna'] ?></td>
                <td class="table-actions">
                    <a href="<?= url('unit', ['id' => $r['id']]) ?>" class="btn btn-light btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>
                    <form method="post" class="d-inline" data-confirm="Hapus unit <?= e($r['nama']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-light btn-sm" title="Hapus"><i class="bi bi-trash text-danger"></i></button></form>
                </td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div></div></div>
    <div class="col-lg-4"><div class="card">
        <div class="card-header"><h2><?= $edit['id'] ? 'Edit Unit' : 'Tambah Unit' ?></h2></div>
        <form method="post" class="card-body">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
            <div class="mb-3"><label class="form-label" for="kode">Kode</label><input id="kode" name="kode" class="form-control" maxlength="10" value="<?= e($edit['kode']) ?>" required></div>
            <div class="mb-3"><label class="form-label" for="nama">Nama unit</label><input id="nama" name="nama" class="form-control" maxlength="100" value="<?= e($edit['nama']) ?>" required></div>
            <div class="mb-3"><label class="form-label" for="pj">Penanggung jawab</label><input id="pj" name="penanggung_jawab" class="form-control" maxlength="100" value="<?= e($edit['penanggung_jawab']) ?>"></div>
            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="aktif" name="aktif" <?= $edit['aktif'] ? 'checked' : '' ?>><label class="form-check-label" for="aktif">Aktif</label></div>
            <div class="d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button><?php if ($edit['id']): ?><a href="<?= url('unit') ?>" class="btn btn-light">Batal</a><?php endif; ?></div>
        </form>
    </div></div>
</div>
<?php require ROOT_PATH . '/includes/footer.php';
