<?php
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nama = trim($_POST['nama'] ?? '');
    $lama = $_POST['password_lama'] ?? '';
    $baru = $_POST['password_baru'] ?? '';
    $konf = $_POST['password_konfirmasi'] ?? '';
    if ($nama !== '') {
        $pdo->prepare('UPDATE users SET nama = ? WHERE id = ?')->execute([$nama, user('id')]);
        $_SESSION['user']['nama'] = $nama;
    }
    if ($baru !== '') {
        $st = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $st->execute([user('id')]);
        if (!password_verify($lama, $st->fetchColumn())) {
            flash('danger', 'Password lama tidak sesuai.');
        } elseif (strlen($baru) < 6) {
            flash('danger', 'Password baru minimal 6 karakter.');
        } elseif ($baru !== $konf) {
            flash('danger', 'Konfirmasi password tidak cocok.');
        } else {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($baru, PASSWORD_DEFAULT), user('id')]);
            flash('success', 'Password berhasil diubah.');
        }
    } else {
        flash('success', 'Profil diperbarui.');
    }
    redirect(url('profil'));
}
$title = 'Profil Saya';
$subtitle = 'Ubah nama dan password akun';
require ROOT_PATH . '/includes/header.php';
?>
<div class="card" style="max-width:520px">
    <form method="post" class="card-body" autocomplete="off">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">Username</label><input class="form-control" value="<?= e(user('username')) ?>" disabled></div>
        <div class="mb-3"><label class="form-label" for="nama">Nama Lengkap</label><input id="nama" name="nama" class="form-control" value="<?= e(user('nama')) ?>" required></div>
        <hr>
        <p class="small text-muted">Isi bagian di bawah hanya jika ingin mengganti password.</p>
        <div class="mb-3"><label class="form-label" for="pl">Password Lama</label><input type="password" id="pl" name="password_lama" class="form-control" autocomplete="current-password"></div>
        <div class="mb-3"><label class="form-label" for="pb">Password Baru</label><input type="password" id="pb" name="password_baru" class="form-control" minlength="6" autocomplete="new-password"></div>
        <div class="mb-3"><label class="form-label" for="pk">Konfirmasi Password Baru</label><input type="password" id="pk" name="password_konfirmasi" class="form-control" autocomplete="new-password"></div>
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
    </form>
</div>
<?php require ROOT_PATH . '/includes/footer.php';
