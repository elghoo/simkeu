<?php
/** Transfer / pemindahbukuan antar akun kas & bank */
$pdo = db();

function get_transfer(int $id): ?array
{
    $st = db()->prepare("SELECT * FROM transaksi WHERE id = ? AND tipe = 'transfer'");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        require_perm('transaksi.write');
        $id  = (int)($_POST['id'] ?? 0);
        $old = $id ? get_transfer($id) : null;
        $tanggal = valid_date($_POST['tanggal'] ?? '', '');
        $dari    = (int)($_POST['akun_id'] ?? 0);
        $ke      = (int)($_POST['akun_tujuan_id'] ?? 0);
        $jumlah  = round(parse_angka($_POST['jumlah'] ?? '0'), 2);
        $ket     = trim($_POST['keterangan'] ?? '') ?: 'Pemindahbukuan antar akun';
        $ref     = trim($_POST['no_referensi'] ?? '') ?: null;
        $back    = url('transfer', $id ? ['id' => $id] : []);

        $akun = array_column(akun_all(false), null, 'id');
        $err = null;
        if ($tanggal === '') $err = 'Tanggal tidak valid.';
        elseif (!isset($akun[$dari], $akun[$ke])) $err = 'Pilih akun asal dan akun tujuan.';
        elseif ($dari === $ke) $err = 'Akun asal dan tujuan tidak boleh sama.';
        elseif ($jumlah <= 0) $err = 'Jumlah harus lebih dari nol.';
        elseif (saldo_min_sejak($dari, $tanggal, $id) - $jumlah < 0) $err = 'Saldo ' . $akun[$dari]['nama'] . ' tidak mencukupi per ' . tgl_indo($tanggal) . '.';
        elseif ($old && saldo_min_sejak((int)$old['akun_tujuan_id'], $old['tanggal'], $id) + (($ke == $old['akun_tujuan_id'] && $tanggal <= $old['tanggal']) ? $jumlah : 0) < 0)
            $err = 'Perubahan ini membuat saldo ' . $akun[$old['akun_tujuan_id']]['nama'] . ' menjadi minus.';
        if ($err) { flash('danger', $err); redirect($back); }

        if ($old) {
            $pdo->prepare('UPDATE transaksi SET tanggal=?, akun_id=?, akun_tujuan_id=?, jumlah=?, keterangan=?, no_referensi=? WHERE id=?')
                ->execute([$tanggal, $dari, $ke, $jumlah, $ket, $ref, $id]);
            log_aktivitas('ubah_transfer', $old['no_transaksi'] . ' - ' . rupiah($jumlah));
            flash('success', 'Transfer ' . $old['no_transaksi'] . ' diperbarui.');
        } else {
            $no = generate_nomor('TRF', 'transaksi', 'no_transaksi', $tanggal);
            $pdo->prepare("INSERT INTO transaksi (no_transaksi, tanggal, tipe, akun_id, akun_tujuan_id, jumlah, keterangan, no_referensi, user_id) VALUES (?,?,'transfer',?,?,?,?,?,?)")
                ->execute([$no, $tanggal, $dari, $ke, $jumlah, $ket, $ref, user('id')]);
            log_aktivitas('tambah_transfer', $no . ' - ' . rupiah($jumlah));
            flash('success', 'Transfer ' . $no . ' berhasil dicatat.');
        }
        redirect(url('transfer'));
    }

    if ($action === 'delete') {
        require_perm('transaksi.delete');
        $id = (int)($_POST['id'] ?? 0);
        $old = get_transfer($id);
        if ($old) {
            if (saldo_min_sejak((int)$old['akun_tujuan_id'], $old['tanggal'], $id) < 0) {
                flash('warning', 'Transfer tidak bisa dihapus karena dana di akun tujuan sudah terpakai.');
            } else {
                $pdo->prepare('DELETE FROM transaksi WHERE id = ?')->execute([$id]);
                log_aktivitas('hapus_transfer', $old['no_transaksi'] . ' - ' . rupiah($old['jumlah']));
                flash('success', 'Transfer ' . $old['no_transaksi'] . ' dihapus.');
            }
        }
        redirect(url('transfer'));
    }
}

$edit = !empty($_GET['id']) && can('transaksi.write') ? get_transfer((int)$_GET['id']) : null;
$edit = $edit ?: ['id' => 0, 'tanggal' => date('Y-m-d'), 'akun_id' => '', 'akun_tujuan_id' => '', 'jumlah' => '', 'keterangan' => '', 'no_referensi' => ''];
$akunList = akun_all();
$saldo = saldo_akun();

$tahun = (int)($_GET['tahun'] ?? date('Y'));
$st = $pdo->prepare("SELECT t.*, a.nama dari, b.nama ke FROM transaksi t JOIN akun_kas a ON a.id = t.akun_id JOIN akun_kas b ON b.id = t.akun_tujuan_id
    WHERE t.tipe = 'transfer' AND YEAR(t.tanggal) = ? ORDER BY t.tanggal DESC, t.id DESC");
$st->execute([$tahun]);
$rows = $st->fetchAll();

$title = 'Transfer Kas';
$subtitle = 'Pemindahbukuan antar kas tunai dan rekening bank';
require ROOT_PATH . '/includes/header.php';
?>
<div class="row g-3">
    <div class="<?= can('transaksi.write') ? 'col-lg-8' : 'col-12' ?>">
        <div class="card">
            <div class="card-header"><h2>Riwayat Transfer <?= $tahun ?></h2>
                <form method="get" class="ms-auto"><input type="hidden" name="page" value="transfer">
                    <select name="tahun" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Tahun">
                        <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 4; $y--): ?><option <?= selected($y, $tahun) ?>><?= $y ?></option><?php endfor; ?>
                    </select></form>
            </div>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>No. / Tanggal</th><th>Dari</th><th>Ke</th><th>Keterangan</th><th class="text-end">Jumlah</th><th></th></tr></thead>
                <tbody><?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="text-nowrap"><span class="fw-semibold"><?= e($r['no_transaksi']) ?></span><div class="small text-muted"><?= tgl_indo($r['tanggal']) ?></div></td>
                        <td class="small"><?= e($r['dari']) ?></td>
                        <td class="small"><i class="bi bi-arrow-right text-muted me-1"></i><?= e($r['ke']) ?></td>
                        <td class="small"><?= e($r['keterangan']) ?></td>
                        <td class="text-end num fw-semibold text-nowrap"><?= rupiah($r['jumlah']) ?></td>
                        <td class="table-actions">
                            <a href="<?= url('bukti', ['id' => $r['id']]) ?>" target="_blank" class="btn btn-light btn-sm" title="Cetak"><i class="bi bi-printer"></i></a>
                            <?php if (can('transaksi.write')): ?><a href="<?= url('transfer', ['id' => $r['id']]) ?>" class="btn btn-light btn-sm" title="Edit"><i class="bi bi-pencil"></i></a><?php endif; ?>
                            <?php if (can('transaksi.delete')): ?><form method="post" class="d-inline" data-confirm="Hapus transfer <?= e($r['no_transaksi']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-light btn-sm" title="Hapus"><i class="bi bi-trash text-danger"></i></button></form><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; if (!$rows): ?>
                    <tr><td colspan="6"><div class="empty-state"><i class="bi bi-arrow-left-right"></i>Belum ada transfer pada tahun ini.</div></td></tr>
                <?php endif; ?></tbody>
            </table></div>
        </div>
    </div>
    <?php if (can('transaksi.write')): ?>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h2><?= $edit['id'] ? 'Edit Transfer' : 'Transfer Baru' ?></h2></div>
            <form method="post" class="card-body">
                <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
                <div class="mb-3"><label class="form-label" for="tgl">Tanggal</label><input type="date" id="tgl" name="tanggal" class="form-control" value="<?= e($edit['tanggal']) ?>" required></div>
                <div class="mb-3"><label class="form-label" for="dari">Dari akun</label>
                    <select name="akun_id" id="dari" class="form-select" required><option value="">— Pilih —</option>
                        <?php foreach ($akunList as $a): ?><option value="<?= $a['id'] ?>" <?= selected($a['id'], $edit['akun_id']) ?>><?= e($a['nama']) ?> (<?= rupiah($saldo[$a['id']]['saldo']) ?>)</option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label" for="ke">Ke akun</label>
                    <select name="akun_tujuan_id" id="ke" class="form-select" required><option value="">— Pilih —</option>
                        <?php foreach ($akunList as $a): ?><option value="<?= $a['id'] ?>" <?= selected($a['id'], $edit['akun_tujuan_id']) ?>><?= e($a['nama']) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label" for="jml">Jumlah</label><div class="input-group"><span class="input-group-text">Rp</span><input id="jml" name="jumlah" class="form-control input-rupiah" inputmode="numeric" value="<?= $edit['jumlah'] !== '' ? (int)$edit['jumlah'] : '' ?>" required></div></div>
                <div class="mb-3"><label class="form-label" for="ket">Keterangan</label><input id="ket" name="keterangan" class="form-control" maxlength="255" value="<?= e($edit['keterangan']) ?>" placeholder="Contoh: Pengisian kas kecil"></div>
                <div class="mb-3"><label class="form-label" for="ref">No. referensi</label><input id="ref" name="no_referensi" class="form-control" maxlength="50" value="<?= e($edit['no_referensi']) ?>" placeholder="No. slip / cek"></div>
                <div class="d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button><?php if ($edit['id']): ?><a href="<?= url('transfer') ?>" class="btn btn-light">Batal</a><?php endif; ?></div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require ROOT_PATH . '/includes/footer.php';
