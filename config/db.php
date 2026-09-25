<?php
/**
 * Konfigurasi koneksi database MySQL
 * Sesuaikan dengan server Anda.
 */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'simkeu_db');
define('DB_USER', 'root');
define('DB_PASS', '');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="font-family:sans-serif;padding:40px;color:#7f1d1d">
                <h3>Koneksi database gagal</h3>
                <p>Periksa pengaturan di <code>config/db.php</code> dan pastikan database <b>' . DB_NAME . '</b> sudah di-import dari <code>database/simkeu_db.sql</code>.</p>
                <small>' . htmlspecialchars($e->getMessage()) . '</small></div>');
        }
    }
    return $pdo;
}
