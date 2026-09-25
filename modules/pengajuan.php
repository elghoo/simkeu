<?php
/**
 * Pengajuan dana unit kerja
 * Alur: Staf/Unit mengajukan -> Pimpinan menyetujui/menolak -> Bendahara mencairkan (otomatis jadi transaksi pengeluaran)
 */
$pdo = db();
$isStaf = user('role') === 'staf';
$myUnit = (int)user('unit_id');

function get_pengajuan(int $id): ?array
{
    $st = db()->prepare('SELECT p.*, u.nama unit, u.penanggung_jawab, k.nama kategori, k.kode kategori_kode, us.nama pengaju, pm.nama pemutus, t.no_transaksi
        FROM pengajuan p JOIN unit u ON u.id = p.unit_id JOIN kategori k ON k.id = p.kategori_id
        LEFT JOIN users us ON us.id = p.user_id LEFT JOIN users pm ON pm.id = p.diputuskan_oleh LEFT JOIN transaksi t ON t.id = p.transaksi_id
        WHERE p.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function boleh_lihat(array $p): bool
{
    return user('role') !== 'staf' || (int)$p['unit_id'] === (int)user('unit_id') || (int)$p['user_id'] === (int)user('id');
}

function boleh_ubah(array $p): bool
{
    return $p['status'] === 'diajukan' && (is_admin() || (int)$p['user_id'] === (int)user('id'));
}

/* ================= POST ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $p = $id ? get_pengajuan($id) : null;
    if ($id && (!$p || !boleh_lihat($p))) { flash('danger', 'Pengajuan tidak ditemukan.'); redirect(url('pengajuan')); }

    if ($action === 'save') {
        require_perm('pengajuan.create');
        if ($p && !boleh_ubah($p)) { flash('warning', 'Pengajuan yang sudah diproses tidak dapat diubah.'); redirect(url('pengajuan', ['id' => $id])); }
        $unit = $isStaf ? $myUnit : (int)($_POST['unit_id'] ?? 0);
        $d = [
            'tanggal'     => valid_date($_POST['tanggal'] ?? '', date('Y-m-d')),
            'unit_id'     => $unit,
            'kategori_id' => (int)($_POST['kategori_id'] ?? 0),
            'judul'       => trim($_POST['judul'] ?? ''),
            'rincian'     => trim($_POST['rincian'] ?? '') ?: null,
            'jumlah'      => round(parse_angka($_POST['jumlah'] ?? '0'), 2),
        ];
        $st = $pdo->prepare("SELECT COUNT(*) FROM kategori WHERE id = ? AND tipe = 'keluar'");
        $st->execute([$d['kategori_id']]);
        $okKat = (bool)$st->fetchColumn();
        $st = $pdo->prepare('SELECT COUNT(*) FROM unit WHERE id = ?');
        $st->execute([$d['unit_id']]);
        $okUnit = (bool)$st->fetchColumn();
        if (!$okUnit) { flash('danger', $isStaf ? 'Akun Anda belum terhubung ke unit kerja. Hubungi administrator.' : 'Pilih unit kerja.'); redirect(url('pengajuan', ['act' => 'form'])); }
        if (!$okKat || $d['judul'] === '' || $d['jumlah'] <= 0) { flash('danger', 'Judul, kategori, dan jumlah wajib diisi dengan benar.'); redirect(url('pengajuan', ['act' => 'form'] + ($id ? ['id' => $id] : []))); }

        if ($p) {
            $pdo->prepare('UPDATE pengajuan SET tanggal=:tanggal, unit_id=:unit_id, kategori_id=:kategori_id, judul=:judul, rincian=:rincian, jumlah=:jumlah WHERE id=:id')->execute($d + ['id' => $id]);
            log_aktivitas('ubah_pengajuan', $p['no_pengajuan']);
            flash('success', 'Pengajuan diperbarui.');
        } else {
            $d['no_pengajuan'] = generate_nomor('PGJ', 'pengajuan', 'no_pengajuan', $d['tanggal']);
            $d['user_id'] = user('id');
            $pdo->prepare('INSERT INTO pengajuan (no_pengajuan, tanggal, unit_id, kategori_id, judul, rincian, jumlah, user_id) VALUES (:no_pengajuan, :tanggal, :unit_id, :kategori_id, :judul, :rincian, :jumlah, :user_id)')->execute($d);
            $id = (int)$pdo->lastInsertId();
            log_aktivitas('tambah_pengajuan', $d['no_pengajuan'] . ' - ' . rupiah($d['jumlah']));
            flash('success', 'Pengajuan ' . $d['no_pengajuan'] . ' berhasil dikirim dan menunggu persetujuan pimpinan.');
        }
        redirect(url('pengajuan', ['id' => $id]));
    }

    if ($action === 'delete' && $p) {
        if (!boleh_ubah($p)) { flash('warning', 'Pengajuan yang sudah diproses tidak dapat dihapus.'); redirect(url('pengajuan', ['id' => $id])); }
        $pdo->prepare('DELETE FROM pengajuan WHERE id = ?')->execute([$id]);
        log_aktivitas('hapus_pengajuan', $p['no_pengajuan']);
        flash('success', 'Pengajuan ' . $p['no_pengajuan'] . ' dihapus.');
        redirect(url('pengajuan'));
    }

    if (in_array($action, ['setujui', 'tolak'], true) && $p) {
        require_perm('pengajuan.approve');
        if ($p['status'] !== 'diajukan') { flash('warning', 'Pengajuan ini sudah diproses.'); redirect(url('pengajuan', ['id' => $id])); }
        $catatan = trim($_POST['catatan'] ?? '') ?: null;
        if ($action === 'setujui') {
            $jml = round(parse_angka($_POST['jumlah_disetujui'] ?? '0'), 2);
            if ($jml <= 0) { flash('danger', 'Jumlah yang disetujui harus lebih dari nol.'); redirect(url('pengajuan', ['id' => $id])); }
            $pdo->prepare("UPDATE pengajuan SET status='disetujui', jumlah_disetujui=?, catatan=?, diputuskan_oleh=?, tgl_keputusan=NOW() WHERE id=?")->execute([$jml, $catatan, user('id'), $id]);
            log_aktivitas('setujui_pengajuan', $p['no_pengajuan'] . ' - ' . rupiah($jml));
            flash('success', 'Pengajuan disetujui sebesar ' . rupiah($jml) . '. Bendahara dapat melakukan pencairan.');
        } else {
            if (!$catatan) { flash('danger', 'Tuliskan alasan penolakan pada kolom catatan.'); redirect(url('pengajuan', ['id' => $id])); }
            $pdo->prepare("UPDATE pengajuan SET status='ditolak', catatan=?, diputuskan_oleh=?, tgl_keputusan=NOW() WHERE id=?")->execute([$catatan, user('id'), $id]);
            log_aktivitas('tolak_pengajuan', $p['no_pengajuan']);
            flash('success', 'Pengajuan ditolak.');
        }
        redirect(url('pengajuan', ['id' => $id]));
    }

    if ($action === 'cairkan' && $p) {
        require_perm('pengajuan.cair');
        if ($p['status'] !== 'disetujui') { flash('warning', 'Hanya pengajuan berstatus disetujui yang dapat dicairkan.'); redirect(url('pengajuan', ['id' => $id])); }
        $akun = (int)($_POST['akun_id'] ?? 0);
        $tgl  = valid_date($_POST['tanggal'] ?? '', date('Y-m-d'));
        $jml  = (float)$p['jumlah_disetujui'];
        $st = $pdo->prepare('SELECT nama FROM akun_kas WHERE id = ? AND aktif = 1');
        $st->execute([$akun]);
        $namaAkun = $st->fetchColumn();
        if (!$namaAkun) { flash('danger', 'Pilih akun sumber dana.'); redirect(url('pengajuan', ['id' => $id])); }
        if (saldo_min_sejak($akun, $tgl) - $jml < 0) { flash('danger', 'Saldo ' . $namaAkun . ' tidak mencukupi untuk pencairan ' . rupiah($jml) . '.'); redirect(url('pengajuan', ['id' => $id])); }

        $pdo->beginTransaction();
        try {
            $no = generate_nomor('BKK', 'transaksi', 'no_transaksi', $tgl);
            $pdo->prepare("INSERT INTO transaksi (no_transaksi, tanggal, tipe, akun_id, kategori_id, unit_id, jumlah, pihak, keterangan, no_referensi, user_id) VALUES (?,?, 'keluar', ?,?,?,?,?,?,?,?)")
                ->execute([$no, $tgl, $akun, $p['kategori_id'], $p['unit_id'], $jml, $p['penanggung_jawab'] ?: $p['unit'], 'Pencairan: ' . $p['judul'], $p['no_pengajuan'], user('id')]);
            $trxId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE pengajuan SET status='dicairkan', transaksi_id=? WHERE id=?")->execute([$trxId, $id]);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            flash('danger', 'Pencairan gagal: ' . $ex->getMessage());
            redirect(url('pengajuan', ['id' => $id]));
        }
        log_aktivitas('cairkan_pengajuan', $p['no_pengajuan'] . ' -> ' . $no);
        flash('success', 'Dana dicairkan dan tercatat sebagai pengeluaran ' . $no . '.');
        redirect(url('pengajuan', ['id' => $id]));
    }
    redirect(url('pengajuan'));
}

$statusList = status_pengajuan_list();

/* ================= Form ================= */
if (($_GET['act'] ?? '') === 'form') {
    require_perm('pengajuan.create');
    $row = !empty($_GET['id']) ? get_pengajuan((int)$_GET['id']) : null;
    if ($row && (!boleh_lihat($row) || !boleh_ubah($row))) { flash('warning', 'Pengajuan ini tidak dapat diubah.'); redirect(url('pengajuan')); }
    $row = $row ?: ['id' => 0, 'tanggal' => date('Y-m-d'), 'unit_id' => $myUnit, 'kategori_id' => '', 'judul' => '', 'rincian' => '', 'jumlah' => ''];
    $title = $row['id'] ? 'Edit Pengajuan Dana' : 'Pengajuan Dana Baru';
    $subtitle = 'Ajukan kebutuhan dana kegiatan unit kerja';
    require ROOT_PATH . '/includes/header.php';
    ?>
    <div class="row justify-content-center"><div class="col-lg-8">
    <form method="post" class="card">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <div class="card-header"><h2>Formulir Pengajuan</h2></div>
        <div class="card-body row g-3">
            <div class="col-md-4"><label class="form-label" for="tgl">Tanggal</label><input type="date" id="tgl" name="tanggal" class="form-control" value="<?= e($row['tanggal']) ?>" required></div>
            <div class="col-md-8"><label class="form-label" for="unit">Unit kerja</label>
                <?php if ($isStaf): $st = $pdo->prepare('SELECT nama FROM unit WHERE id = ?'); $st->execute([$myUnit]); ?>
                    <input class="form-control" id="unit" value="<?= e($st->fetchColumn() ?: 'Belum terhubung ke unit') ?>" disabled>
                <?php else: ?>
                    <select name="unit_id" id="unit" class="form-select" required><option value="">— Pilih unit —</option>
                        <?php foreach (unit_all() as $u): ?><option value="<?= $u['id'] ?>" <?= selected($u['id'], $row['unit_id']) ?>><?= e($u['nama']) ?></option><?php endforeach; ?></select>
                <?php endif; ?></div>
            <div class="col-12"><label class="form-label" for="judul">Nama kegiatan / keperluan</label><input id="judul" name="judul" class="form-control" maxlength="150" value="<?= e($row['judul']) ?>" required placeholder="Contoh: Seminar nasional pendidikan Islam"></div>
            <div class="col-md-6"><label class="form-label" for="kat">Pos anggaran</label>
                <select name="kategori_id" id="kat" class="form-select" required><option value="">— Pilih pos pengeluaran —</option>
                    <?php foreach (kategori_all('keluar') as $k): ?><option value="<?= $k['id'] ?>" <?= selected($k['id'], $row['kategori_id']) ?>><?= e($k['kode'] . ' — ' . $k['nama']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label" for="jml">Jumlah diajukan</label><div class="input-group"><span class="input-group-text">Rp</span><input id="jml" name="jumlah" class="form-control input-rupiah" inputmode="numeric" value="<?= $row['jumlah'] !== '' ? (int)$row['jumlah'] : '' ?>" required></div></div>
            <div class="col-12"><label class="form-label" for="rinc">Rincian kebutuhan (RAB singkat)</label><textarea id="rinc" name="rincian" rows="5" class="form-control" placeholder="Contoh:&#10;- Honor narasumber 2 x Rp 1.500.000&#10;- Konsumsi 80 peserta x Rp 35.000"><?= e($row['rincian']) ?></textarea></div>
        </div>
        <div class="card-body border-top d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-send me-1"></i><?= $row['id'] ? 'Simpan Perubahan' : 'Kirim Pengajuan' ?></button><a href="<?= url('pengajuan') ?>" class="btn btn-light">Batal</a></div>
    </form>
    </div></div>
    <?php
    require ROOT_PATH . '/includes/footer.php';
    return;
}

/* ================= Detail ================= */
if (!empty($_GET['id'])) {
    $p = get_pengajuan((int)$_GET['id']);
    if (!$p || !boleh_lihat($p)) { flash('danger', 'Pengajuan tidak ditemukan.'); redirect(url('pengajuan')); }
    $tahun = (int)date('Y', strtotime($p['tanggal']));
    $st = $pdo->prepare('SELECT jumlah FROM anggaran WHERE tahun = ? AND kategori_id = ?');
    $st->execute([$tahun, $p['kategori_id']]);
    $ang = (float)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM transaksi WHERE tipe='keluar' AND kategori_id = ? AND YEAR(tanggal) = ?");
    $st->execute([$p['kategori_id'], $tahun]);
    $real = (float)$st->fetchColumn();
    $saldo = saldo_akun();

    $title = 'Detail Pengajuan';
    $subtitle = $p['no_pengajuan'];
    require ROOT_PATH . '/includes/header.php';
    ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h2><?= e($p['judul']) ?></h2><?= status_badge($p['status']) ?>
                    <?php if (boleh_ubah($p) && can('pengajuan.create')): ?>
                        <div class="ms-auto d-flex gap-1">
                            <a href="<?= url('pengajuan', ['act' => 'form', 'id' => $p['id']]) ?>" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i> Edit</a>
                            <form method="post" data-confirm="Hapus pengajuan ini?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn btn-sm btn-light"><i class="bi bi-trash text-danger"></i></button></form>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <dl class="detail-list">
                        <dt>No. pengajuan</dt><dd class="fw-semibold"><?= e($p['no_pengajuan']) ?></dd>
                        <dt>Tanggal</dt><dd><?= tgl_indo($p['tanggal'], true) ?></dd>
                        <dt>Unit kerja</dt><dd><?= e($p['unit']) ?></dd>
                        <dt>Pos anggaran</dt><dd><?= e($p['kategori_kode'] . ' — ' . $p['kategori']) ?></dd>
                        <dt>Jumlah diajukan</dt><dd class="fw-semibold num"><?= rupiah($p['jumlah']) ?></dd>
                        <?php if ($p['jumlah_disetujui'] !== null): ?><dt>Jumlah disetujui</dt><dd class="fw-semibold num text-in"><?= rupiah($p['jumlah_disetujui']) ?></dd><?php endif; ?>
                        <dt>Diajukan oleh</dt><dd><?= e($p['pengaju'] ?? '-') ?></dd>
                        <?php if ($p['no_transaksi']): ?><dt>Bukti pencairan</dt><dd><a href="<?= url('pengeluaran', ['act' => 'view', 'id' => $p['transaksi_id']]) ?>"><?= e($p['no_transaksi']) ?></a></dd><?php endif; ?>
                    </dl>
                    <?php if ($p['rincian']): ?>
                        <div class="mt-3"><div class="stat-label mb-1">Rincian kebutuhan</div><div class="p-3 rounded border bg-light small" style="white-space:pre-line"><?= e($p['rincian']) ?></div></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h2>Riwayat Proses</h2></div>
                <div class="card-body"><ul class="timeline">
                    <li><b>Diajukan</b> oleh <?= e($p['pengaju'] ?? '-') ?><div class="text-muted small"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></div></li>
                    <?php if ($p['tgl_keputusan']): ?>
                        <li><b><?= $p['status'] === 'ditolak' ? 'Ditolak' : 'Disetujui' ?></b> oleh <?= e($p['pemutus'] ?? '-') ?><?= $p['catatan'] ? ' — "' . e($p['catatan']) . '"' : '' ?><div class="text-muted small"><?= date('d/m/Y H:i', strtotime($p['tgl_keputusan'])) ?></div></li>
                    <?php endif; ?>
                    <?php if ($p['status'] === 'dicairkan'): ?><li><b>Dicairkan</b> melalui <?= e($p['no_transaksi'] ?? '-') ?></li><?php endif; ?>
                </ul></div>
            </div>
            <a href="<?= url('pengajuan') ?>" class="btn btn-light mt-3"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        </div>

        <div class="col-lg-5 d-flex flex-column gap-3">
            <?php if (!$isStaf): ?>
            <div class="card"><div class="card-body">
                <div class="stat-label mb-2">Posisi anggaran <?= e($p['kategori']) ?> tahun <?= $tahun ?></div>
                <?php $pct = $ang > 0 ? $real / $ang * 100 : 0; ?>
                <div class="d-flex justify-content-between small"><span>Realisasi <?= rupiah($real) ?></span><span><?= angka($pct, 1) ?>%</span></div>
                <div class="progress my-1"><div class="progress-bar <?= $pct > 100 ? 'over' : ($pct > 85 ? 'warn' : '') ?>" style="width:<?= min(100, $pct) ?>%"></div></div>
                <div class="small text-muted">Anggaran <?= rupiah($ang) ?> &middot; Sisa <b class="<?= $ang - $real < ($p['jumlah_disetujui'] ?? $p['jumlah']) ? 'text-out' : '' ?>"><?= rupiah($ang - $real) ?></b></div>
                <?php if ($ang == 0): ?><div class="small text-out mt-1">Pos ini belum memiliki anggaran pada tahun <?= $tahun ?>.</div><?php endif; ?>
            </div></div>
            <?php endif; ?>

            <?php if ($p['status'] === 'diajukan' && can('pengajuan.approve')): ?>
            <form method="post" class="card">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
                <div class="card-header"><h2>Keputusan Pimpinan</h2></div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label" for="js">Jumlah disetujui</label><div class="input-group"><span class="input-group-text">Rp</span><input id="js" name="jumlah_disetujui" class="form-control input-rupiah" inputmode="numeric" value="<?= (int)$p['jumlah'] ?>"></div></div>
                    <div class="mb-3"><label class="form-label" for="cat">Catatan</label><textarea id="cat" name="catatan" rows="2" class="form-control" placeholder="Wajib diisi bila menolak"></textarea></div>
                    <div class="d-flex gap-2"><button name="action" value="setujui" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Setujui</button><button name="action" value="tolak" class="btn btn-light text-danger"><i class="bi bi-x-circle me-1"></i>Tolak</button></div>
                </div>
            </form>
            <?php endif; ?>

            <?php if ($p['status'] === 'disetujui' && can('pengajuan.cair')): ?>
            <form method="post" class="card" data-confirm="Cairkan dana <?= rupiah($p['jumlah_disetujui']) ?>? Sistem akan mencatatnya sebagai pengeluaran.">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="cairkan">
                <div class="card-header"><h2>Pencairan Dana</h2></div>
                <div class="card-body">
                    <p class="small text-muted">Dana sebesar <b class="text-body"><?= rupiah($p['jumlah_disetujui']) ?></b> akan dicatat otomatis sebagai pengeluaran pada pos <?= e($p['kategori']) ?>.</p>
                    <div class="mb-3"><label class="form-label" for="tc">Tanggal pencairan</label><input type="date" id="tc" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    <div class="mb-3"><label class="form-label" for="ak">Sumber dana</label>
                        <select id="ak" name="akun_id" class="form-select" required><option value="">— Pilih akun —</option>
                            <?php foreach (akun_all() as $a): ?><option value="<?= $a['id'] ?>"><?= e($a['nama']) ?> (<?= rupiah($saldo[$a['id']]['saldo']) ?>)</option><?php endforeach; ?></select></div>
                    <button class="btn btn-gold"><i class="bi bi-cash-coin me-1"></i>Cairkan Dana</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
    require ROOT_PATH . '/includes/footer.php';
    return;
}

/* ================= Daftar ================= */
$fStatus = isset($statusList[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$where = ['1=1'];
$params = [];
if ($isStaf) { $where[] = '(p.unit_id = ? OR p.user_id = ?)'; $params[] = $myUnit; $params[] = user('id'); }
$ws = implode(' AND ', $where);
$st = $pdo->prepare("SELECT status, COUNT(*) n FROM pengajuan p WHERE $ws GROUP BY status");
$st->execute($params);
$counts = $st->fetchAll(PDO::FETCH_KEY_PAIR);
if ($fStatus) { $ws .= ' AND p.status = ?'; $params[] = $fStatus; }
$st = $pdo->prepare("SELECT p.*, u.nama unit, k.nama kategori FROM pengajuan p JOIN unit u ON u.id = p.unit_id JOIN kategori k ON k.id = p.kategori_id WHERE $ws
    ORDER BY FIELD(p.status, 'diajukan', 'disetujui', 'dicairkan', 'ditolak'), p.tanggal DESC, p.id DESC");
$st->execute($params);
$rows = $st->fetchAll();

$title = 'Pengajuan Dana';
$subtitle = $isStaf ? 'Pengajuan dana unit Anda' : 'Persetujuan dan pencairan dana unit kerja';
require ROOT_PATH . '/includes/header.php';
?>
<div class="card">
    <div class="card-header pb-0 border-0 flex-wrap">
        <ul class="nav nav-tabs-line flex-grow-1 flex-nowrap overflow-auto">
            <li class="nav-item"><a class="nav-link text-nowrap <?= $fStatus === '' ? 'active' : '' ?>" href="<?= url('pengajuan') ?>">Semua <span class="text-muted">(<?= array_sum($counts) ?>)</span></a></li>
            <?php foreach ($statusList as $k => [$lbl]): ?>
                <li class="nav-item"><a class="nav-link text-nowrap <?= $fStatus === $k ? 'active' : '' ?>" href="<?= url('pengajuan', ['status' => $k]) ?>"><?= $lbl ?> <span class="text-muted">(<?= (int)($counts[$k] ?? 0) ?>)</span></a></li>
            <?php endforeach; ?>
        </ul>
        <?php if (can('pengajuan.create')): ?><a href="<?= url('pengajuan', ['act' => 'form']) ?>" class="btn btn-sm btn-primary mb-2"><i class="bi bi-plus-lg me-1"></i>Ajukan Dana</a><?php endif; ?>
    </div>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Pengajuan</th><th class="d-none d-md-table-cell">Unit</th><th class="text-end">Diajukan</th><th class="text-end d-none d-sm-table-cell">Disetujui</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr style="cursor:pointer" onclick="location.href='<?= e(url('pengajuan', ['id' => $r['id']])) ?>'">
                <td><a href="<?= url('pengajuan', ['id' => $r['id']]) ?>" class="fw-semibold text-decoration-none"><?= e($r['judul']) ?></a>
                    <div class="small text-muted"><?= e($r['no_pengajuan']) ?> &middot; <?= tgl_indo($r['tanggal']) ?></div>
                    <div class="small text-muted"><?= e($r['kategori']) ?><span class="d-md-none"> &middot; <?= e($r['unit']) ?></span></div></td>
                <td class="small d-none d-md-table-cell"><?= e($r['unit']) ?></td>
                <td class="text-end num text-nowrap"><?= rupiah($r['jumlah']) ?></td>
                <td class="text-end num text-nowrap d-none d-sm-table-cell"><?= $r['jumlah_disetujui'] !== null ? rupiah($r['jumlah_disetujui']) : '<span class="text-muted">-</span>' ?></td>
                <td><?= status_badge($r['status']) ?></td>
            </tr>
        <?php endforeach; if (!$rows): ?>
            <tr><td colspan="5"><div class="empty-state"><i class="bi bi-clipboard"></i>Belum ada pengajuan dana.</div></td></tr>
        <?php endif; ?></tbody>
    </table></div>
</div>
<?php require ROOT_PATH . '/includes/footer.php';
