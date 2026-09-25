<?php
/**
 * Konfigurasi umum aplikasi
 */
define('APP_NAME', 'SIMKEU');
define('APP_DESC', 'Sistem Informasi Manajemen Keuangan');
define('APP_VERSION', '1.0.0');
define('ROOT_PATH', dirname(__DIR__));

date_default_timezone_set('Asia/Jakarta');

// Base URL otomatis (bisa diisi manual, contoh: '/simkeu')
$__base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (substr($__base, -5) === '/auth') {
    $__base = substr($__base, 0, -5);
}
define('BASE_URL', $__base);

if (session_status() === PHP_SESSION_NONE) {
    session_name('SIMKEUSESS');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

require_once __DIR__ . '/db.php';
require_once ROOT_PATH . '/includes/functions.php';
