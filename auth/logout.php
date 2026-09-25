<?php
require_once __DIR__ . '/../config/app.php';
if (is_login()) log_aktivitas('logout', 'Keluar dari sistem');
$_SESSION = [];
session_destroy();
redirect(BASE_URL . '/auth/login.php');
