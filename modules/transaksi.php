<?php
/**
 * Modul Pemasukan & Pengeluaran (satu modul, dibedakan oleh $page)
 */
$pdo  = db();
$tipe = $page === 'pemasukan' ? 'masuk' : 'keluar';
$isIn = $tipe === 'masuk';
$label = $isIn ? 'Pemasukan' : 'Pengeluaran';
$pihakLabel = $isIn ? 'Diterima dari' : 'Dibayarkan kepada';
$act = $_GET['act'] ?? 'list';

function get_trx(int $id, string $tipe): ?array
{
    $st = db()->prepare("SELECT t.*, a.nama akun, a.kode akun_kode, k.nama kategori, k.kode kategori_kode, u.nama unit, us.nama petugas
        FROM transaksi t JOIN akun_kas a ON a.id = t.akun_id LEFT JOIN kategori k ON k.id = t.kategori_id
        LEFT JOIN unit u ON u.id = t.unit_id LEFT JOIN users us ON us.id = t.user_id WHERE t.id = ? AND t.tipe = ?");
    $st->execute([$id, $tipe]);
    return $st->fetch() ?: null;
}

/* ================= Proses POST ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        require_perm('transaksi.write');
        $id  = (int)($_POST['id'] ?? 0);
        $old = $id ? get_trx($id, $tipe) : null;
        if ($id && !$old) { flash('danger', 'Data tidak ditemukan.'); redirect(url($page)); }

        $d = [
            'tanggal'      => valid_date($_POST['tanggal'] ?? '', ''),
            'akun_id'      => (int)($_POST['akun_id'] ?? 0),
            'kategori_id'  => (int)($_POST['kategori_id'] ?? 0),
            'unit_id'      => (int)($_POST['unit_id'] ?? 0) ?: null,
            'jumlah'       => round(parse_angka($_POST['jumlah'] ?? '0'), 2),
            'pihak'        => trim($_POST['pihak'] ?? '') ?: null,
            'keterangan'   => trim($_POST['keterangan'] ?? ''),
            'no_referensi' => trim($_POST['no_referensi'] ?? '') ?: null,
        ];
        $_SESSION['old_input'] = $_POST;
        $back = url($page, ['act' => 'form'] + ($id ? ['id' => $id] : []));

        // Validasi
        $errors = [];
        if ($d['tanggal'] === '') $errors[] = 'Tanggal tidak valid.';
        if ($d['jumlah'] <= 0) $errors[] = 'Jumlah harus lebih dari nol.';
        if ($d['keterangan'] === '') $errors[] = 'Keterangan wajib diisi.';
        $st = $pdo->prepare('SELECT COUNT(*) FROM akun_kas WHERE id = ?');
        $st->execute([$d['akun_id']]);
        if (!$st->fetchColumn()) $errors[] = 'Pilih akun kas / bank.';
        $st = $pdo->prepare('SELECT COUNT(*) FROM kategori WHERE id = ? AND tipe = ?');
        $st->execute([$d['kategori_id'], $tipe]);
        if (!$st->fetchColumn()) $errors[] = 'Pilih kategori ' . strtolower($label) . '.';

        if (!$errors) {
            if (!$isIn) {
                // Pengeluaran tidak boleh membuat saldo akun minus pada tanggal tersebut maupun sesudahnya
                $min = saldo_min_sejak($d['akun_id'], $d['tanggal'], $id);
                if ($min - $d['jumlah'] < 0) {
                    $errors[] = 'Saldo akun tidak mencukupi. Saldo tersedia per ' . tgl_indo($d['tanggal']) . ': ' . rupiah(max(0, $min)) . '.';
                }
            } elseif ($old) {
                // Mengubah pemasukan tidak boleh membuat saldo akun lama menjadi minus
                $min = saldo_min_sejak((int)$old['akun_id'], $old['tanggal'], $id);
                if ($d['akun_id'] == $old['akun_id'] && $d['tanggal'] <= $old['tanggal']) $min += $d['jumlah'];
                if ($min < 0) $errors[] = 'Perubahan ini membuat saldo akun ' . $old['akun'] . ' menjadi minus, karena dananya sudah terpakai.';
            }
        }

        if (!$errors) {
            try {
                $bukti = upload_bukti('bukti');
            } catch (RuntimeException $ex) {
                $errors[] = $ex->getMessage();
            }
        }
        if ($errors) {
            foreach ($errors as $er) flash('danger', $er);
            redirect($back);
        }

        if ($old) {
            $newBukti = $old['bukti'];
            if ($bukti || !empty($_POST['hapus_bukti'])) {
                hapus_bukti($old['bukti']);
                $newBukti = $bukti;
            }
            $pdo->prepare('UPDATE transaksi SET tanggal=?, akun_id=?, kategori_id=?, unit_id=?, jumlah=?, pihak=?, keterangan=?, no_referensi=?, bukti=? WHERE id=?')
                ->execute([$d['tanggal'], $d['akun_id'], $d['kategori_id'], $d['unit_id'], $d['jumlah'], $d['pihak'], $d['keterangan'], $d['no_referensi'], $newBukti, $id]);
            log_aktivitas('ubah_' . $tipe, $old['no_transaksi'] . ' - ' . rupiah($d['jumlah']));
            flash('success', $label . ' ' . $old['no_transaksi'] . ' berhasil diperbarui.');
        } else {
            $no = generate_nomor(prefix_transaksi($tipe), 'transaksi', 'no_transaksi', $d['tanggal']);
            $pdo->prepare('INSERT INTO transaksi (no_transaksi, tanggal, tipe, akun_id, kategori_id, unit_id, jumlah, pihak, keterangan, no_referensi, bukti, user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$no, $d['tanggal'], $tipe, $d['akun_id'], $d['kategori_id'], $d['unit_id'], $d['jumlah'], $d['pihak'], $d['keterangan'], $d['no_referensi'], $bukti, user('id')]);
            $id = (int)$pdo->lastInsertId();
            log_aktivitas('tambah_' . $tipe, $no . ' - ' . rupiah($d['jumlah']));
            flash('success', $label . ' ' . $no . ' berhasil disimpan.');
        }
        unset($_SESSION['old_input']);
        redirect(!empty($_POST['lagi']) ? url($page, ['act' => 'form']) : url($page, ['act' => 'view', 'id' => $id]));
    }

    if ($action === 'delete') {
        require_perm('transaksi.delete');
        $id = (int)($_POST['id'] ?? 0);
        $old = get_trx($id, $tipe);
        if ($old) {
            if ($isIn && saldo_min_sejak((int)$old['akun_id'], $old['tanggal'], $id) < 0) {
                flash('warning', 'Pemasukan ini tidak bisa dihapus karena dananya sudah terpakai untuk transaksi lain.');
                redirect(url($page, ['act' => 'view', 'id' => $id]));
            }
            // Kembalikan status pengajuan yang terkait
            $pdo->prepare("UPDATE pengajuan SET status = 'disetujui', transaksi_id = NULL WHERE transaksi_id = ?")->execute([$id]);
            $pdo->prepare('DELETE FROM transaksi WHERE id = ?')->execute([$id]);
            hapus_bukti($old['bukti']);
            log_aktivitas('hapus_' . $tipe, $old['no_transaksi'] . ' - ' . rupiah($old['jumlah']));
            flash('success', $label . ' ' . $old['no_transaksi'] . ' dihapus.');
        }
        redirect(url($page));
    }
}

/* ================= Form tambah / edit ================= */
if ($act === 'form') {
    require_perm('transaksi.write');
    $id = (int)($_GET['id'] ?? 0);
    $row = $id ? get_trx($id, $tipe) : null;
    if ($id && !$row) { flash('danger', 'Data tidak ditemukan.'); redirect(url($page)); }
    $row = $row ?: ['id' => 0, 'tanggal' => date('Y-m-d'), 'akun_id' => '', 'kategori_id' => '', 'unit_id' => '', 'jumlah' => '', 'pihak' => '', 'keterangan' => '', 'no_referensi' => '', 'bukti' => null];
    if (!empty($_SESSION['old_input'])) {
        $row = array_merge($row, array_intersect_key($_SESSION['old_input'], $row));
        unset($_SESSION['old_input']);
    }
    $akunList = akun_all();
    $katList  = kategori_all($tipe);
    $unitList = unit_all();
    $saldoNow = saldo_akun();

    $title = ($row['id'] ? 'Edit ' : 'Catat ') . $label;
    $subtitle = $row['id'] ? ($row['no_transaksi'] ?? '') : ($isIn ? 'Bukti Kas Masuk (BKM)' : 'Bukti Kas Keluar (BKK)');
    require ROOT_PATH . '/includes/header.php';
    ?>
    <form method="post" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h2>Data <?= $label ?></h2></div>
                <div class="card-body row g-3">
                    <div class="col-md-4"><label class="form-label" for="tanggal">Tanggal</label>
                        <input type="date" name="tanggal" id="tanggal" class="form-control" value="<?= e($row['tanggal']) ?>" required></div>
                    <div class="col-md-8"><label class="form-label" for="akun_id"><?= $isIn ? 'Masuk ke akun' : 'Dibayar dari akun' ?></label>
                        <select name="akun_id" id="akun_id" class="form-select" required>
                            <option value="">— Pilih akun kas / bank —</option>
                            <?php foreach ($akunList as $a): ?>
                                <option value="<?= $a['id'] ?>" data-saldo="<?= (float)$saldoNow[$a['id']]['saldo'] ?>" <?= selected($a['id'], $row['akun_id']) ?>><?= e($a['kode'] . ' — ' . $a['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" id="saldoInfo"></div></div>
                    <div class="col-md-6"><label class="form-label" for="kategori_id">Kategori / pos <?= strtolower($label) ?></label>
                        <select name="kategori_id" id="kategori_id" class="form-select" required>
                            <option value="">— Pilih kategori —</option>
                            <?php foreach ($katList as $k): ?><option value="<?= $k['id'] ?>" <?= selected($k['id'], $row['kategori_id']) ?>><?= e($k['kode'] . ' — ' . $k['nama']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-md-6"><label class="form-label" for="unit_id">Unit kerja <span class="text-muted">(opsional)</span></label>
                        <select name="unit_id" id="unit_id" class="form-select">
                            <option value="">— Tanpa unit —</option>
                            <?php foreach ($unitList as $u): ?><option value="<?= $u['id'] ?>" <?= selected($u['id'], $row['unit_id']) ?>><?= e($u['nama']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-md-6"><label class="form-label" for="jumlah">Jumlah</label>
                        <div class="input-group"><span class="input-group-text">Rp</span>
                            <input name="jumlah" id="jumlah" class="form-control input-rupiah fw-semibold" inputmode="numeric" value="<?= $row['jumlah'] !== '' ? e(is_numeric($row['jumlah']) ? (int)round((float)$row['jumlah']) : $row['jumlah']) : '' ?>" required></div>
                        <div class="form-text fst-italic" id="terbilang"></div></div>
                    <div class="col-md-6"><label class="form-label" for="pihak"><?= $pihakLabel ?></label>
                        <input name="pihak" id="pihak" class="form-control" maxlength="150" value="<?= e($row['pihak']) ?>" placeholder="<?= $isIn ? 'Nama penyetor / instansi' : 'Nama penerima / toko / vendor' ?>"></div>
                    <div class="col-12"><label class="form-label" for="keterangan">Keterangan / uraian</label>
                        <input name="keterangan" id="keterangan" class="form-control" maxlength="255" value="<?= e($row['keterangan']) ?>" required placeholder="<?= $isIn ? 'Contoh: Pembayaran UKT semester ganjil' : 'Contoh: Pembelian ATK bulan September' ?>"></div>
                    <div class="col-md-6"><label class="form-label" for="no_referensi">No. referensi <span class="text-muted">(nota, faktur, slip)</span></label>
                        <input name="no_referensi" id="no_referensi" class="form-control" maxlength="50" value="<?= e($row['no_referensi']) ?>"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h2>Bukti Transaksi</h2></div>
                <div class="card-body">
                    <input type="file" name="bukti" class="form-control" accept=".jpg,.jpeg,.png,.pdf" aria-label="Unggah bukti">
                    <div class="form-text">JPG, PNG, atau PDF. Maksimal 2 MB.</div>
                    <?php if (!empty($row['bukti'])): ?>
                        <div class="mt-2 small"><a href="<?= e(bukti_url($row['bukti'])) ?>" target="_blank"><i class="bi bi-paperclip"></i> Bukti saat ini</a></div>
                        <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="hapus_bukti" value="1" id="hb"><label class="form-check-label small" for="hb">Hapus bukti</label></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card"><div class="card-body d-grid gap-2">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                <?php if (!$row['id']): ?><button class="btn btn-light" name="lagi" value="1"><i class="bi bi-plus-lg me-1"></i>Simpan &amp; catat lagi</button><?php endif; ?>
                <a href="<?= url($page) ?>" class="btn btn-light">Batal</a>
            </div></div>
        </div>
    </form>
    <?php
    $pageScript = '<script>
    (function(){
        var sel = document.getElementById("akun_id"), info = document.getElementById("saldoInfo");
        var jml = document.getElementById("jumlah"), tb = document.getElementById("terbilang");
        function upd(){ var o = sel.options[sel.selectedIndex]; info.textContent = o && o.dataset.saldo !== undefined ? "Saldo saat ini: " + rupiah(parseFloat(o.dataset.saldo)) : ""; }
        sel.addEventListener("change", upd); upd();
        function tbl(){ var n = parseRibuan(jml.value); tb.textContent = n ? terbilang(n) + " rupiah" : ""; }
        jml.addEventListener("input", tbl); tbl();
    })();
    </script>';
    require ROOT_PATH . '/includes/footer.php';
    return;
}

/* ================= Detail ================= */
if ($act === 'view') {
    $row = get_trx((int)($_GET['id'] ?? 0), $tipe);
    if (!$row) { flash('danger', 'Data tidak ditemukan.'); redirect(url($page)); }
    $st = $pdo->prepare('SELECT id, no_pengajuan, judul FROM pengajuan WHERE transaksi_id = ?');
    $st->execute([$row['id']]);
    $pgj = $st->fetch();
    $title = 'Detail ' . $label;
    $subtitle = $row['no_transaksi'];
    require ROOT_PATH . '/includes/header.php';
    ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h2><?= e($row['no_transaksi']) ?></h2>
                    <span class="badge-soft <?= $isIn ? 'bs-in' : 'bs-red' ?>"><?= $label ?></span>
                    <div class="ms-auto d-flex gap-1">
                        <a href="<?= url('bukti', ['id' => $row['id']]) ?>" target="_blank" class="btn btn-sm btn-light"><i class="bi bi-printer me-1"></i>Cetak <?= $isIn ? 'BKM' : 'BKK' ?></a>
                        <?php if (can('transaksi.write')): ?><a href="<?= url($page, ['act' => 'form', 'id' => $row['id']]) ?>" class="btn btn-sm btn-light"><i class="bi bi-pencil me-1"></i>Edit</a><?php endif; ?>
                        <?php if (can('transaksi.delete')): ?>
                            <form method="post" data-confirm="Hapus <?= strtolower($label) ?> <?= e($row['no_transaksi']) ?>? Tindakan ini tidak dapat dibatalkan."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $row['id'] ?>"><button class="btn btn-sm btn-light"><i class="bi bi-trash text-danger"></i></button></form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3"><div class="stat-label">Jumlah</div><div class="stat-value <?= $isIn ? 'text-in' : 'text-out' ?>" style="font-size:1.6rem"><?= rupiah($row['jumlah']) ?></div>
                        <div class="small text-muted fst-italic"><?= e(ucfirst(terbilang($row['jumlah']))) ?> rupiah</div></div>
                    <dl class="detail-list">
                        <dt>Tanggal</dt><dd><?= tgl_indo($row['tanggal'], true) ?></dd>
                        <dt>Akun</dt><dd><?= e($row['akun_kode'] . ' — ' . $row['akun']) ?></dd>
                        <dt>Kategori</dt><dd><?= e($row['kategori_kode'] . ' — ' . $row['kategori']) ?></dd>
                        <dt>Unit kerja</dt><dd><?= e($row['unit'] ?? '-') ?></dd>
                        <dt><?= $pihakLabel ?></dt><dd><?= e($row['pihak'] ?? '-') ?></dd>
                        <dt>Keterangan</dt><dd><?= e($row['keterangan']) ?></dd>
                        <dt>No. referensi</dt><dd><?= e($row['no_referensi'] ?? '-') ?></dd>
                        <?php if ($pgj): ?><dt>Pengajuan dana</dt><dd><a href="<?= url('pengajuan', ['id' => $pgj['id']]) ?>"><?= e($pgj['no_pengajuan'] . ' — ' . $pgj['judul']) ?></a></dd><?php endif; ?>
                        <dt>Dicatat oleh</dt><dd><?= e($row['petugas'] ?? '-') ?> <span class="text-muted small">&middot; <?= e(date('d/m/Y H:i', strtotime($row['created_at']))) ?></span></dd>
                    </dl>
                </div>
            </div>
            <a href="<?= url($page) ?>" class="btn btn-light mt-3"><i class="bi bi-arrow-left me-1"></i>Kembali ke daftar</a>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h2>Bukti Transaksi</h2></div>
                <div class="card-body">
                    <?php if ($row['bukti']): $ext = strtolower(pathinfo($row['bukti'], PATHINFO_EXTENSION)); ?>
                        <?php if ($ext === 'pdf'): ?>
                            <a href="<?= e(bukti_url($row['bukti'])) ?>" target="_blank" class="btn btn-light w-100"><i class="bi bi-file-earmark-pdf me-1 text-danger"></i>Buka bukti (PDF)</a>
                        <?php else: ?>
                            <a href="<?= e(bukti_url($row['bukti'])) ?>" target="_blank"><img src="<?= e(bukti_url($row['bukti'])) ?>" alt="Bukti <?= e($row['no_transaksi']) ?>" class="bukti-preview"></a>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state py-4"><i class="bi bi-paperclip"></i>Belum ada file bukti yang diunggah.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    require ROOT_PATH . '/includes/footer.php';
    return;
}

/* ================= Daftar ================= */
$dari   = valid_date($_GET['dari'] ?? '', date('Y-m-01'));
$sampai = valid_date($_GET['sampai'] ?? '', date('Y-m-d'));
if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];
$fAkun = (int)($_GET['akun'] ?? 0);
$fKat  = (int)($_GET['kategori'] ?? 0);
$fUnit = (int)($_GET['unit'] ?? 0);
$q     = trim($_GET['q'] ?? '');

$where = ['t.tipe = ?', 't.tanggal BETWEEN ? AND ?'];
$params = [$tipe, $dari, $sampai];
if ($fAkun) { $where[] = 't.akun_id = ?'; $params[] = $fAkun; }
if ($fKat)  { $where[] = 't.kategori_id = ?'; $params[] = $fKat; }
if ($fUnit) { $where[] = 't.unit_id = ?'; $params[] = $fUnit; }
if ($q !== '') {
    $where[] = '(t.no_transaksi LIKE ? OR t.keterangan LIKE ? OR t.pihak LIKE ? OR t.no_referensi LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
$ws = implode(' AND ', $where);
$st = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(t.jumlah),0) FROM transaksi t WHERE $ws");
$st->execute($params);
[$total, $sum] = $st->fetch(PDO::FETCH_NUM);

$filter = ['dari' => $dari, 'sampai' => $sampai, 'akun' => $fAkun ?: null, 'kategori' => $fKat ?: null, 'unit' => $fUnit ?: null, 'q' => $q ?: null];
$pg = paginate((int)$total, 20, (int)($_GET['p'] ?? 1), $page, $filter);
$st = $pdo->prepare("SELECT t.*, a.nama akun, k.nama kategori, u.nama unit FROM transaksi t
    JOIN akun_kas a ON a.id = t.akun_id LEFT JOIN kategori k ON k.id = t.kategori_id LEFT JOIN unit u ON u.id = t.unit_id
    WHERE $ws ORDER BY t.tanggal DESC, t.id DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}");
$st->execute($params);
$rows = $st->fetchAll();

$title = $label;
$subtitle = $isIn ? 'Pencatatan kas masuk lembaga' : 'Pencatatan kas keluar lembaga';
require ROOT_PATH . '/includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <form method="get" class="toolbar flex-grow-1">
            <input type="hidden" name="page" value="<?= e($page) ?>">
            <input type="date" name="dari" value="<?= e($dari) ?>" class="form-control form-control-sm" aria-label="Dari tanggal">
            <span class="text-muted small">s.d.</span>
            <input type="date" name="sampai" value="<?= e($sampai) ?>" class="form-control form-control-sm" aria-label="Sampai tanggal">
            <select name="akun" class="form-select form-select-sm" aria-label="Filter akun"><option value="">Semua akun</option>
                <?php foreach (akun_all(false) as $a): ?><option value="<?= $a['id'] ?>" <?= selected($a['id'], $fAkun) ?>><?= e($a['nama']) ?></option><?php endforeach; ?></select>
            <select name="kategori" class="form-select form-select-sm" aria-label="Filter kategori"><option value="">Semua kategori</option>
                <?php foreach (kategori_all($tipe, false) as $k): ?><option value="<?= $k['id'] ?>" <?= selected($k['id'], $fKat) ?>><?= e($k['nama']) ?></option><?php endforeach; ?></select>
            <select name="unit" class="form-select form-select-sm" aria-label="Filter unit"><option value="">Semua unit</option>
                <?php foreach (unit_all(false) as $u): ?><option value="<?= $u['id'] ?>" <?= selected($u['id'], $fUnit) ?>><?= e($u['nama']) ?></option><?php endforeach; ?></select>
            <input type="search" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Cari no., uraian, pihak…" aria-label="Cari">
            <button class="btn btn-sm btn-light"><i class="bi bi-funnel"></i> Terapkan</button>
        </form>
        <?php if (can('transaksi.write')): ?>
            <a href="<?= url($page, ['act' => 'form']) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Catat <?= $label ?></a>
        <?php endif; ?>
    </div>
    <div class="px-3 py-2 border-bottom d-flex flex-wrap gap-3 small">
        <span class="text-muted"><?= angka($total) ?> transaksi</span>
        <span>Total: <strong class="<?= $isIn ? 'text-in' : 'text-out' ?> num"><?= rupiah($sum) ?></strong></span>
    </div>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>No. / Tanggal</th><th>Uraian</th><th>Kategori</th><th>Akun</th><th class="text-end">Jumlah</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="text-nowrap"><a href="<?= url($page, ['act' => 'view', 'id' => $r['id']]) ?>" class="fw-semibold text-decoration-none"><?= e($r['no_transaksi']) ?></a><div class="small text-muted"><?= tgl_indo($r['tanggal']) ?></div></td>
                <td><div><?= e($r['keterangan']) ?><?= $r['bukti'] ? ' <i class="bi bi-paperclip text-muted" title="Ada bukti"></i>' : '' ?></div><div class="small text-muted"><?= e($r['pihak'] ?? '') ?><?= $r['unit'] ? ' &middot; ' . e($r['unit']) : '' ?></div></td>
                <td class="small"><?= e($r['kategori']) ?></td>
                <td class="small"><?= e($r['akun']) ?></td>
                <td class="text-end num fw-semibold text-nowrap <?= $isIn ? 'text-in' : 'text-out' ?>"><?= rupiah($r['jumlah']) ?></td>
                <td class="table-actions">
                    <a href="<?= url('bukti', ['id' => $r['id']]) ?>" target="_blank" class="btn btn-light btn-sm" title="Cetak bukti"><i class="bi bi-printer"></i></a>
                    <?php if (can('transaksi.write')): ?><a href="<?= url($page, ['act' => 'form', 'id' => $r['id']]) ?>" class="btn btn-light btn-sm" title="Edit"><i class="bi bi-pencil"></i></a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; if (!$rows): ?>
            <tr><td colspan="6"><div class="empty-state"><i class="bi bi-inbox"></i>Tidak ada <?= strtolower($label) ?> pada filter ini.</div></td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
    <?php if ($pg['html']): ?><div class="card-body py-2 d-flex justify-content-end"><?= $pg['html'] ?></div><?php endif; ?>
</div>
<?php require ROOT_PATH . '/includes/footer.php';
