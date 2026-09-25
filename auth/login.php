<?php
require_once __DIR__ . '/../config/app.php';

if (is_login()) redirect(url());

$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Batasi percobaan login sederhana (5x / 5 menit)
    $_SESSION['login_try'] = array_filter($_SESSION['login_try'] ?? [], fn($t) => $t > time() - 300);
    if (count($_SESSION['login_try']) >= 5) {
        $error = 'Terlalu banyak percobaan. Coba lagi dalam beberapa menit.';
    } else {
        $st = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $st->execute([$username]);
        $u = $st->fetch();
        if ($u && $u['aktif'] && password_verify($password, $u['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => $u['id'], 'nama' => $u['nama'], 'username' => $u['username'], 'role' => $u['role'], 'unit_id' => $u['unit_id']];
            unset($_SESSION['login_try']);
            db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$u['id']]);
            log_aktivitas('login', 'Masuk ke sistem');
            flash('success', 'Assalamu\'alaikum, ' . $u['nama'] . '. Selamat bekerja.');
            redirect(url());
        }
        $_SESSION['login_try'][] = time();
        $error = ($u && !$u['aktif']) ? 'Akun Anda dinonaktifkan. Hubungi administrator.' : 'Username atau password salah.';
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk &middot; <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body class="login-page">
<div class="login-wrap">
    <section class="login-hero">
        <div class="login-pattern" aria-hidden="true"></div>
        <div class="position-relative">
            <div class="d-flex align-items-center gap-2 mb-5 text-white">
                <?= logo_svg(44) ?>
                <div>
                    <div class="fw-bold fs-5 lh-1"><?= APP_NAME ?></div>
                    <small class="opacity-75"><?= APP_DESC ?></small>
                </div>
            </div>
            <p class="ayat" lang="ar" dir="rtl">يَا أَيُّهَا الَّذِينَ آمَنُوا إِذَا تَدَايَنتُم بِدَيْنٍ إِلَىٰ أَجَلٍ مُّسَمًّى فَاكْتُبُوهُ</p>
            <p class="ayat-arti">"Wahai orang-orang yang beriman, apabila kamu bermuamalah tidak secara tunai untuk waktu yang ditentukan, hendaklah kamu menuliskannya." <span class="opacity-75">(QS. Al-Baqarah: 282)</span></p>
            <div class="login-stats">
                <div><strong>Tercatat</strong>setiap rupiah masuk &amp; keluar</div>
                <div><strong>Terencana</strong>anggaran &amp; realisasi</div>
                <div><strong>Transparan</strong>laporan siap cetak</div>
            </div>
        </div>
        <div class="position-relative small opacity-75"><?= e(setting('nama_lembaga')) ?></div>
    </section>
    <section class="login-form">
        <form method="post" class="w-100" style="max-width:360px" autocomplete="off">
            <?= csrf_field() ?>
            <h2 class="fw-bold mb-1">Masuk</h2>
            <p class="text-muted mb-4">Silakan masuk untuk mengelola keuangan lembaga.</p>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="username" name="username" value="<?= e($username) ?>" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password'" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
                </div>
            </div>
            <button class="btn btn-primary w-100 py-2" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i> Masuk</button>
            <p class="text-muted small mt-4 mb-0">Akun demo: <code>admin / admin123</code>, <code>bendahara / bendahara123</code>, <code>pimpinan / pimpinan123</code>, <code>staf / staf123</code></p>
        </form>
    </section>
</div>
</body>
</html>
