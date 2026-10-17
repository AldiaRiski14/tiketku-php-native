<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'tiketku7');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// App Config
define('APP_NAME', 'TiketKu');
define('APP_URL', 'http://localhost:8080/tiketku7'); // sesuaikan dengan path Anda
define('APP_VERSION', '2.0.0');

define('EMAILJS_PUBLIC_KEY', 'L18LkOizI6KtmYM9m');
define('EMAILJS_SERVICE_ID', 'service_d6robai');
define('EMAILJS_TEMPLATE_TICKET', 'template_vqxep0q');
define('EMAILJS_TEMPLATE_WARNING', 'template_opnnq0d');


// Timezone
date_default_timezone_set('Asia/Jakarta');

// Output buffering (mencegah error "headers already sent")
if (ob_get_level() === 0) {
    ob_start();
}

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage() . "<br><br>
         Pastikan MySQL sudah running dan database <b>tiketku</b> sudah diimport dari file database.sql");
}
?>
