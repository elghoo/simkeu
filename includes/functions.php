<?php
/**
 * Kumpulan fungsi bantu (helper)
 */

/* ---------- Output & URL ---------- */
function e($s): string
{
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function url(string $page = '', array $params = []): string
{
    if ($page === '') $page = is_login() ? home_page() : 'dashboard';
    $q = array_merge(['page' => $page], $params);
    return BASE_URL . '/index.php?' . http_build_query($q);
}

function asset(string $path): string
{
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

/* ---------- Flash message ---------- */
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function show_flash(): string
{
    if (empty($_SESSION['flash'])) return '';
    $html = '';
    $icons = ['success' => 'check-circle', 'danger' => 'x-circle', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'];
    foreach ($_SESSION['flash'] as [$type, $msg]) {
        $icon = $icons[$type] ?? 'info-circle';
        $html .= '<div class="alert alert-' . e($type) . ' alert-dismissible fade show d-flex align-items-center gap-2" role="alert">'
            . '<i class="bi bi-' . $icon . '"></i><div>' . e($msg) . '</div>'
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    $t = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        die('Sesi formulir kedaluwarsa. Silakan muat ulang halaman.');
    }
}

/* ---------- Auth & hak akses ---------- */
function user(?string $key = null)
{
    $u = $_SESSION['user'] ?? null;
    if ($key === null) return $u;
    return $u[$key] ?? null;
}

function is_login(): bool
{
    return !empty($_SESSION['user']);
}

function is_admin(): bool
{
    return user('role') === 'admin';
}

function role_list(): array
{
    return [
        'admin'     => 'Administrator',
        'bendahara' => 'Bendahara',
        'pimpinan'  => 'Pimpinan',
        'staf'      => 'Staf Unit',
    ];
}

/**
 * Matriks hak akses. Tambahkan / ubah di sini bila perlu.
 */
function can(string $perm): bool
{
    $map = [
        'dashboard'         => ['admin', 'bendahara', 'pimpinan'],
        'transaksi.view'    => ['admin', 'bendahara', 'pimpinan'],
        'transaksi.write'   => ['admin', 'bendahara'],
        'transaksi.delete'  => ['admin'],
        'master'            => ['admin', 'bendahara'],
        'laporan'           => ['admin', 'bendahara', 'pimpinan'],
        'pengajuan.view'    => ['admin', 'bendahara', 'pimpinan', 'staf'],
        'pengajuan.create'  => ['admin', 'bendahara', 'staf'],
        'pengajuan.approve' => ['admin', 'pimpinan'],
        'pengajuan.cair'    => ['admin', 'bendahara'],
        'system'            => ['admin'],
    ];
    return in_array(user('role'), $map[$perm] ?? [], true);
}

function require_login(): void
{
    if (!is_login()) {
        redirect(BASE_URL . '/auth/login.php');
    }
}

function require_perm(string $perm): void
{
    if (!can($perm)) {
        flash('danger', 'Anda tidak memiliki hak akses untuk halaman atau tindakan tersebut.');
        redirect(url(home_page()));
    }
}

function require_admin(): void
{
    require_perm('system');
}

/** Halaman awal sesuai peran */
function home_page(): string
{
    return can('dashboard') ? 'dashboard' : 'pengajuan';
}

/* ---------- Log aktivitas ---------- */
function log_aktivitas(string $aksi, string $detail = ''): void
{
    try {
        db()->prepare('INSERT INTO log_aktivitas (user_id, aksi, detail, ip) VALUES (?, ?, ?, ?)')
            ->execute([user('id'), $aksi, (function_exists('mb_substr') ? mb_substr($detail, 0, 255) : substr($detail, 0, 255)), $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) {
        // log tidak boleh menggagalkan proses utama
    }
}

/* ---------- Format angka & tanggal ---------- */
function rupiah($n, bool $prefix = true): string
{
    return ($prefix ? 'Rp ' : '') . number_format((float)$n, 0, ',', '.');
}

function angka($n, int $dec = 0): string
{
    $s = number_format((float)$n, $dec, ',', '.');
    if ($dec > 0) $s = rtrim(rtrim($s, '0'), ',');
    return $s;
}

/** Mengubah "1.500.000" / "2,5" menjadi float */
function parse_angka($s): float
{
    $s = trim((string)$s);
    if ($s === '') return 0;
    $s = preg_replace('/[^0-9,\.\-]/', '', $s);
    // Format Indonesia: titik = ribuan, koma = desimal
    if (strpos($s, ',') !== false) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (substr_count($s, '.') > 1 || preg_match('/\.\d{3}$/', $s)) {
        $s = str_replace('.', '', $s);
    }
    return (float)$s;
}

function tgl_indo(?string $date, bool $withDay = false): string
{
    if (!$date || $date === '0000-00-00') return '-';
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $hari  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', "Jum'at", 'Sabtu'];
    $ts = strtotime($date);
    $s = date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
    return $withDay ? $hari[(int)date('w', $ts)] . ', ' . $s : $s;
}

function nama_bulan(int $m, bool $short = false): string
{
    $b = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $short ? substr($b[$m], 0, 3) : $b[$m];
}

/** Terbilang (untuk kwitansi) */
function terbilang($n): string
{
    $n = abs((int)floor($n));
    $h = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    if ($n < 12) return $h[$n];
    if ($n < 20) return terbilang($n - 10) . ' belas';
    if ($n < 100) return trim(terbilang(intdiv($n, 10)) . ' puluh ' . terbilang($n % 10));
    if ($n < 200) return trim('seratus ' . terbilang($n - 100));
    if ($n < 1000) return trim(terbilang(intdiv($n, 100)) . ' ratus ' . terbilang($n % 100));
    if ($n < 2000) return trim('seribu ' . terbilang($n - 1000));
    if ($n < 1000000) return trim(terbilang(intdiv($n, 1000)) . ' ribu ' . terbilang($n % 1000));
    if ($n < 1000000000) return trim(terbilang(intdiv($n, 1000000)) . ' juta ' . terbilang($n % 1000000));
    if ($n < 1000000000000) return trim(terbilang(intdiv($n, 1000000000)) . ' miliar ' . terbilang($n % 1000000000));
    return trim(terbilang(intdiv($n, 1000000000000)) . ' triliun ' . terbilang($n % 1000000000000));
}

/* ---------- Pengaturan ---------- */
function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT kunci, nilai FROM pengaturan') as $r) {
            $cache[$r['kunci']] = $r['nilai'];
        }
    }
    return ($cache[$key] ?? '') !== '' ? $cache[$key] : $default;
}

/* ---------- Referensi ---------- */
function tipe_label(string $t): string
{
    return ['masuk' => 'Pemasukan', 'keluar' => 'Pengeluaran', 'transfer' => 'Transfer'][$t] ?? $t;
}

function status_pengajuan_list(): array
{
    return [
        'diajukan'  => ['Menunggu', 'bs-gold'],
        'disetujui' => ['Disetujui', 'bs-blue'],
        'ditolak'   => ['Ditolak', 'bs-red'],
        'dicairkan' => ['Dicairkan', 'bs-green'],
    ];
}

function status_badge(string $s): string
{
    $l = status_pengajuan_list()[$s] ?? [$s, 'bs-gray'];
    return '<span class="badge-soft ' . $l[1] . '">' . e($l[0]) . '</span>';
}

function akun_all(bool $onlyActive = true): array
{
    return db()->query('SELECT * FROM akun_kas' . ($onlyActive ? ' WHERE aktif = 1' : '') . ' ORDER BY kode')->fetchAll();
}

function kategori_all(?string $tipe = null, bool $onlyActive = true): array
{
    $w = [];
    $p = [];
    if ($tipe) { $w[] = 'tipe = ?'; $p[] = $tipe; }
    if ($onlyActive) $w[] = 'aktif = 1';
    $st = db()->prepare('SELECT * FROM kategori' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY kode');
    $st->execute($p);
    return $st->fetchAll();
}

function unit_all(bool $onlyActive = true): array
{
    return db()->query('SELECT * FROM unit' . ($onlyActive ? ' WHERE aktif = 1' : '') . ' ORDER BY kode')->fetchAll();
}

/* ---------- Nomor otomatis ---------- */
function generate_nomor(string $prefix, string $table, string $col, string $tanggal): string
{
    $tgl = date('Ymd', strtotime($tanggal));
    $st = db()->prepare("SELECT {$col} FROM {$table} WHERE {$col} LIKE ? ORDER BY {$col} DESC LIMIT 1");
    $st->execute([$prefix . '-' . $tgl . '-%']);
    $last = $st->fetchColumn();
    $n = $last ? ((int)substr($last, -4)) + 1 : 1;
    return sprintf('%s-%s-%04d', $prefix, $tgl, $n);
}

function prefix_transaksi(string $tipe): string
{
    return ['masuk' => 'BKM', 'keluar' => 'BKK', 'transfer' => 'TRF'][$tipe];
}

/* ---------- Saldo ---------- */
/**
 * Saldo setiap akun kas/bank sampai tanggal tertentu (inklusif).
 * $excludeId: abaikan transaksi tertentu (dipakai saat edit).
 * Hasil: [akun_id => row + masuk, keluar, trf_masuk, trf_keluar, saldo]
 */
function saldo_akun(?string $sampai = null, int $excludeId = 0): array
{
    $w = ' AND id <> ' . (int)$excludeId;
    $p = [];
    if ($sampai) { $w .= ' AND tanggal <= ?'; }
    $sql = "SELECT a.*,
        COALESCE((SELECT SUM(jumlah) FROM transaksi WHERE tipe='masuk'    AND akun_id = a.id $w),0) AS masuk,
        COALESCE((SELECT SUM(jumlah) FROM transaksi WHERE tipe='keluar'   AND akun_id = a.id $w),0) AS keluar,
        COALESCE((SELECT SUM(jumlah) FROM transaksi WHERE tipe='transfer' AND akun_tujuan_id = a.id $w),0) AS trf_masuk,
        COALESCE((SELECT SUM(jumlah) FROM transaksi WHERE tipe='transfer' AND akun_id = a.id $w),0) AS trf_keluar
        FROM akun_kas a ORDER BY a.kode";
    if ($sampai) $p = [$sampai, $sampai, $sampai, $sampai];
    $st = db()->prepare($sql);
    $st->execute($p);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $r['saldo'] = $r['saldo_awal'] + $r['masuk'] + $r['trf_masuk'] - $r['keluar'] - $r['trf_keluar'];
        $out[$r['id']] = $r;
    }
    return $out;
}

/** Saldo terkecil sebuah akun dari tanggal $dari ke depan (mencegah saldo minus di masa lalu) */
function saldo_min_sejak(int $akunId, string $dari, int $excludeId = 0): float
{
    $awal = saldo_akun(date('Y-m-d', strtotime($dari . ' -1 day')), $excludeId)[$akunId]['saldo'] ?? 0;
    $st = db()->prepare("SELECT tanggal,
            SUM(CASE WHEN (tipe='masuk' AND akun_id=:a) OR (tipe='transfer' AND akun_tujuan_id=:a2) THEN jumlah ELSE 0 END)
          - SUM(CASE WHEN (tipe IN ('keluar','transfer') AND akun_id=:a3) THEN jumlah ELSE 0 END) AS net
        FROM transaksi WHERE tanggal >= :d AND id <> :x AND (akun_id=:a4 OR akun_tujuan_id=:a5)
        GROUP BY tanggal ORDER BY tanggal");
    $st->execute(['a' => $akunId, 'a2' => $akunId, 'a3' => $akunId, 'a4' => $akunId, 'a5' => $akunId, 'd' => $dari, 'x' => $excludeId]);
    // Saldo akhir hari untuk setiap tanggal >= $dari. Bila tanggal $dari belum ada transaksi, saldo hari itu = $awal.
    $run = $awal;
    $min = null;
    foreach ($st as $r) {
        if ($min === null && $r['tanggal'] > $dari) $min = $awal;
        $run += $r['net'];
        $min = $min === null ? $run : min($min, $run);
    }
    if ($min === null) $min = $awal;
    return $min;
}

/* ---------- Unggah bukti ---------- */
function upload_bukti(string $field): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Gagal mengunggah file bukti.');
    if ($f['size'] > 2 * 1024 * 1024) throw new RuntimeException('Ukuran file bukti maksimal 2 MB.');
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];
    if (!isset($allowed[$ext])) throw new RuntimeException('Format bukti harus JPG, PNG, atau PDF.');
    if (function_exists('finfo_open')) {
        $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $f['tmp_name']);
        if (!in_array($mime, array_values($allowed), true)) throw new RuntimeException('Isi file bukti tidak valid.');
    }
    $dir = ROOT_PATH . '/uploads/bukti';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = date('Ym') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Gagal menyimpan file bukti.');
    return $name;
}

function hapus_bukti(?string $name): void
{
    if ($name && preg_match('/^[\w\-]+\.(jpg|jpeg|png|pdf)$/i', $name)) {
        @unlink(ROOT_PATH . '/uploads/bukti/' . $name);
    }
}

function bukti_url(string $name): string
{
    return BASE_URL . '/uploads/bukti/' . rawurlencode($name);
}

/* ---------- Paginasi ---------- */
function paginate(int $total, int $perPage, int $current, string $page, array $params = []): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $current = max(1, min($current, $pages));
    $offset = ($current - 1) * $perPage;
    $html = '';
    if ($pages > 1) {
        $html .= '<nav aria-label="Navigasi halaman"><ul class="pagination pagination-sm mb-0">';
        $mk = function ($p, $label, $disabled = false, $active = false) use ($page, $params) {
            $cls = 'page-item' . ($disabled ? ' disabled' : '') . ($active ? ' active' : '');
            return '<li class="' . $cls . '"><a class="page-link" href="' . e(url($page, array_merge($params, ['p' => $p]))) . '">' . $label . '</a></li>';
        };
        $html .= $mk($current - 1, '&laquo;', $current <= 1);
        $start = max(1, $current - 2);
        $end = min($pages, $current + 2);
        for ($i = $start; $i <= $end; $i++) {
            $html .= $mk($i, (string)$i, false, $i === $current);
        }
        $html .= $mk($current + 1, '&raquo;', $current >= $pages);
        $html .= '</ul></nav>';
    }
    return ['offset' => $offset, 'limit' => $perPage, 'html' => $html, 'total' => $total, 'page' => $current];
}

function selected($a, $b): string
{
    return (string)$a === (string)$b ? 'selected' : '';
}

function valid_date(?string $d, string $default): string
{
    return ($d && DateTime::createFromFormat('Y-m-d', $d) && date('Y-m-d', strtotime($d)) === $d) ? $d : $default;
}

/** Logo SVG aplikasi (buku besar + grafik naik) */
function logo_svg(int $size = 32): string
{
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 40 40" fill="none" aria-label="Logo ' . APP_NAME . '" role="img">'
        . '<rect x="1" y="1" width="38" height="38" rx="11" fill="currentColor" opacity=".14"/>'
        . '<rect x="9" y="22" width="5" height="9" rx="1.5" fill="currentColor"/>'
        . '<rect x="17.5" y="16" width="5" height="15" rx="1.5" fill="currentColor"/>'
        . '<rect x="26" y="10" width="5" height="21" rx="1.5" fill="#D4A63A"/>'
        . '<path d="M8 18.5 16 12l5 3.5 9-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity=".55"/>'
        . '</svg>';
}

function inisial(?string $s): string
{
    $s = trim((string)$s);
    if ($s === '') return '?';
    return strtoupper(function_exists('mb_substr') ? mb_substr($s, 0, 1) : substr($s, 0, 1));
}
