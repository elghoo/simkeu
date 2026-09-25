<?php
/** Log aktivitas pengguna */
$pdo = db();
$q = trim($_GET['q'] ?? '');
$fUser = (int)($_GET['user'] ?? 0);
$dari = valid_date($_GET['dari'] ?? '', date('Y-m-d', strtotime('-30 days')));
$sampai = valid_date($_GET['sampai'] ?? '', date('Y-m-d'));
$w = ['DATE(l.created_at) BETWEEN ? AND ?'];
$p = [$dari, $sampai];
if ($q !== '') { $w[] = '(l.aksi LIKE ? OR l.detail LIKE ?)'; $p[] = "%$q%"; $p[] = "%$q%"; }
if ($fUser) { $w[] = 'l.user_id = ?'; $p[] = $fUser; }
$ws = implode(' AND ', $w);
$st = $pdo->prepare("SELECT COUNT(*) FROM log_aktivitas l WHERE $ws");
$st->execute($p);
$total = (int)$st->fetchColumn();
$pg = paginate($total, 30, (int)($_GET['p'] ?? 1), 'log', ['q' => $q ?: null, 'user' => $fUser ?: null, 'dari' => $dari, 'sampai' => $sampai]);
$st = $pdo->prepare("SELECT l.*, u.nama, u.username FROM log_aktivitas l LEFT JOIN users u ON u.id = l.user_id WHERE $ws ORDER BY l.id DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}");
$st->execute($p);
$rows = $st->fetchAll();
$users = $pdo->query('SELECT id, nama FROM users ORDER BY nama')->fetchAll();

$title = 'Log Aktivitas';
$subtitle = 'Jejak audit seluruh aktivitas pengguna';
require ROOT_PATH . '/includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <form method="get" class="toolbar flex-grow-1"><input type="hidden" name="page" value="log">
            <input name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Cari aksi / detail" aria-label="Cari">
            <select name="user" class="form-select form-select-sm" aria-label="Pengguna"><option value="">Semua pengguna</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= selected($u['id'], $fUser) ?>><?= e($u['nama']) ?></option><?php endforeach; ?></select>
            <input type="date" name="dari" value="<?= e($dari) ?>" class="form-control form-control-sm" aria-label="Dari">
            <input type="date" name="sampai" value="<?= e($sampai) ?>" class="form-control form-control-sm" aria-label="Sampai">
            <button class="btn btn-sm btn-light"><i class="bi bi-funnel"></i> Filter</button>
        </form>
        <span class="small text-muted"><?= angka($total) ?> entri</span>
    </div>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Detail</th><th>IP</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr>
                <td class="small text-nowrap num"><?= date('d/m/Y H:i:s', strtotime($r['created_at'])) ?></td>
                <td><?= $r['nama'] ? e($r['nama']) . ' <span class="small text-muted">@' . e($r['username']) . '</span>' : '<span class="text-muted">(dihapus)</span>' ?></td>
                <td><span class="badge-soft <?= str_starts_with($r['aksi'], 'hapus') || str_starts_with($r['aksi'], 'tolak') ? 'bs-red' : (str_starts_with($r['aksi'], 'log') ? 'bs-gray' : 'bs-blue') ?>"><?= e(str_replace('_', ' ', $r['aksi'])) ?></span></td>
                <td class="small"><?= e($r['detail'] ?? '') ?></td>
                <td class="small text-muted num"><?= e($r['ip'] ?? '') ?></td>
            </tr>
        <?php endforeach; if (!$rows): ?>
            <tr><td colspan="5"><div class="empty-state"><i class="bi bi-clock-history"></i>Belum ada aktivitas pada rentang ini.</div></td></tr>
        <?php endif; ?></tbody>
    </table></div>
    <?php if ($pg['html'] !== ''): ?><div class="card-body border-top"><?= $pg['html'] ?></div><?php endif; ?>
</div>
<?php require ROOT_PATH . '/includes/footer.php';
