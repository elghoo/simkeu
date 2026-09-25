<?php
/** Manajemen pengguna & hak akses */
$pdo = db();
$roles = role_list();
$roleBadge = ['admin' => 'bs-gold', 'bendahara' => 'bs-blue', 'pimpinan' => 'bs-green', 'staf' => 'bs-gray'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $back = url('pengguna', $id ? ['id' => $id] : []);
    if ($action === 'save') {
        $nama = trim($_POST['nama'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $role = isset($roles[$_POST['role'] ?? '']) ? $_POST['role'] : 'staf';
        $unit = (int)($_POST['unit_id'] ?? 0) ?: null;
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        $pass = $_POST['password'] ?? '';
        if ($nama === '' || !preg_match('/^[a-z0-9_.]{3,50}$/', $username)) { flash('danger', 'Nama wajib diisi dan username 3-50 karakter (huruf kecil, angka, titik, garis bawah).'); redirect($back); }
        if ($role === 'staf' && !$unit) { flash('danger', 'Pengguna dengan peran Staf Unit wajib dihubungkan ke unit kerja.'); redirect($back); }
        $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?');
        $st->execute([$username, $id]);
        if ($st->fetchColumn()) { flash('danger', 'Username sudah digunakan.'); redirect($back); }
        if ((!$id || $pass !== '') && strlen($pass) < 6) { flash('danger', 'Password minimal 6 karakter.'); redirect($back); }
        if ($id === (int)user('id')) { $role = 'admin'; $aktif = 1; } // cegah admin mengunci diri sendiri

        if ($id) {
            $pdo->prepare('UPDATE users SET nama=?, username=?, role=?, unit_id=?, aktif=? WHERE id=?')->execute([$nama, $username, $role, $unit, $aktif, $id]);
            if ($pass !== '') $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
            log_aktivitas('ubah_pengguna', $username . ' (' . $role . ')');
        } else {
            $pdo->prepare('INSERT INTO users (nama, username, password, role, unit_id, aktif) VALUES (?,?,?,?,?,?)')->execute([$nama, $username, password_hash($pass, PASSWORD_DEFAULT), $role, $unit, $aktif]);
            log_aktivitas('tambah_pengguna', $username . ' (' . $role . ')');
        }
        flash('success', 'Data pengguna berhasil disimpan.');
        redirect(url('pengguna'));
    }
    if ($action === 'delete') {
        if ($id === (int)user('id')) {
            flash('warning', 'Anda tidak dapat menghapus akun sendiri.');
        } else {
            $st = $pdo->prepare('SELECT username FROM users WHERE id = ?');
            $st->execute([$id]);
            $un = $st->fetchColumn();
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            log_aktivitas('hapus_pengguna', (string)$un);
            flash('success', 'Pengguna dihapus. Riwayat transaksinya tetap tersimpan.');
        }
        redirect(url('pengguna'));
    }
}

$edit = null;
if (!empty($_GET['id'])) { $st = $pdo->prepare('SELECT * FROM users WHERE id = ?'); $st->execute([(int)$_GET['id']]); $edit = $st->fetch() ?: null; }
$edit = $edit ?: ['id' => 0, 'nama' => '', 'username' => '', 'role' => 'staf', 'unit_id' => null, 'aktif' => 1];
$rows = $pdo->query("SELECT us.*, u.nama unit FROM users us LEFT JOIN unit u ON u.id = us.unit_id ORDER BY FIELD(us.role,'admin','bendahara','pimpinan','staf'), us.nama")->fetchAll();
$isSelf = $edit['id'] && $edit['id'] == user('id');

$title = 'Pengguna';
$subtitle = 'Akun dan hak akses aplikasi';
require ROOT_PATH . '/includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card"><div class="table-responsive"><table class="table table-hover">
            <thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th>Status</th><th>Login terakhir</th><th></th></tr></thead>
            <tbody><?php foreach ($rows as $r): ?>
                <tr class="<?= $r['aktif'] ? '' : 'opacity-50' ?>">
                    <td><div class="fw-semibold"><?= e($r['nama']) ?><?= $r['id'] == user('id') ? ' <span class="small text-muted fw-normal">(Anda)</span>' : '' ?></div><div class="small text-muted"><?= e($r['unit'] ?? '') ?></div></td>
                    <td class="num"><?= e($r['username']) ?></td>
                    <td><span class="badge-soft <?= $roleBadge[$r['role']] ?>"><?= e($roles[$r['role']]) ?></span></td>
                    <td><?= $r['aktif'] ? '<span class="badge-soft bs-blue">Aktif</span>' : '<span class="badge-soft bs-gray">Nonaktif</span>' ?></td>
                    <td class="small text-muted text-nowrap"><?= $r['last_login'] ? date('d/m/Y H:i', strtotime($r['last_login'])) : '-' ?></td>
                    <td class="table-actions">
                        <a href="<?= url('pengguna', ['id' => $r['id']]) ?>" class="btn btn-light btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>
                        <?php if ($r['id'] != user('id')): ?>
                        <form method="post" class="d-inline" data-confirm="Hapus pengguna <?= e($r['nama']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-light btn-sm" title="Hapus"><i class="bi bi-trash text-danger"></i></button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div></div>

        <div class="card mt-3">
            <div class="card-header"><h2>Matriks Hak Akses</h2></div>
            <div class="table-responsive"><table class="table table-sm small mb-0">
                <thead><tr><th>Fitur</th><?php foreach ($roles as $l): ?><th class="text-center"><?= e($l) ?></th><?php endforeach; ?></tr></thead>
                <tbody><?php foreach ([
                    'Dashboard & laporan keuangan' => [1, 1, 1, 0],
                    'Catat pemasukan, pengeluaran, transfer' => [1, 1, 0, 0],
                    'Hapus transaksi' => [1, 0, 0, 0],
                    'Data master & susun anggaran' => [1, 1, 0, 0],
                    'Buat pengajuan dana' => [1, 1, 0, 1],
                    'Setujui / tolak pengajuan' => [1, 0, 1, 0],
                    'Cairkan dana pengajuan' => [1, 1, 0, 0],
                    'Pengguna, pengaturan, log' => [1, 0, 0, 0],
                ] as $f => $m): ?>
                    <tr><td><?= e($f) ?></td><?php foreach ($m as $v): ?><td class="text-center"><?= $v ? '<i class="bi bi-check-lg text-success"></i>' : '<span class="text-muted">&ndash;</span>' ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?></tbody>
            </table></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h2><?= $edit['id'] ? 'Edit Pengguna' : 'Tambah Pengguna' ?></h2></div>
            <form method="post" class="card-body">
                <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
                <div class="mb-3"><label class="form-label" for="nama">Nama lengkap</label><input id="nama" name="nama" class="form-control" maxlength="100" value="<?= e($edit['nama']) ?>" required></div>
                <div class="mb-3"><label class="form-label" for="un">Username</label><input id="un" name="username" class="form-control" maxlength="50" value="<?= e($edit['username']) ?>" required autocomplete="off"></div>
                <div class="mb-3"><label class="form-label" for="role">Peran</label>
                    <select id="role" name="role" class="form-select" <?= $isSelf ? 'disabled' : '' ?>><?php foreach ($roles as $k => $l): ?><option value="<?= $k ?>" <?= selected($k, $edit['role']) ?>><?= e($l) ?></option><?php endforeach; ?></select>
                    <?php if ($isSelf): ?><div class="form-text">Peran akun Anda sendiri tidak dapat diubah.</div><?php endif; ?></div>
                <div class="mb-3"><label class="form-label" for="unit">Unit kerja</label>
                    <select id="unit" name="unit_id" class="form-select"><option value="">— Tidak ada —</option><?php foreach (unit_all() as $u): ?><option value="<?= $u['id'] ?>" <?= selected($u['id'], $edit['unit_id']) ?>><?= e($u['nama']) ?></option><?php endforeach; ?></select>
                    <div class="form-text">Wajib untuk Staf Unit: menentukan pengajuan yang dapat dilihat.</div></div>
                <div class="mb-3"><label class="form-label" for="pw">Password <?= $edit['id'] ? '<span class="text-muted fw-normal">(kosongkan bila tidak diubah)</span>' : '' ?></label><input type="password" id="pw" name="password" class="form-control" minlength="6" <?= $edit['id'] ? '' : 'required' ?> autocomplete="new-password"></div>
                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="aktif" name="aktif" <?= $edit['aktif'] ? 'checked' : '' ?> <?= $isSelf ? 'disabled' : '' ?>><label class="form-check-label" for="aktif">Aktif</label></div>
                <div class="d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button><?php if ($edit['id']): ?><a href="<?= url('pengguna') ?>" class="btn btn-light">Batal</a><?php endif; ?></div>
            </form>
        </div>
    </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php';
