<?php
// =====================================================
// TIKETKU - Helper Functions (v2 - kompleks, tanpa tumpang tindih)
// =====================================================

require_once __DIR__ . '/../config/database.php';

/**
 * Escape output
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect (aman, anti "headers already sent")
 */
function redirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    }
    // Fallback jika header sudah terkirim (sudah ada output HTML)
    echo '<script>window.location.href = ' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '"></noscript>';
    exit;
}

/**
 * Flash message
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Check login
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('warning', 'Silakan login terlebih dahulu.');
        redirect(APP_URL . '/user/login.php');
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        setFlash('danger', 'Akses ditolak. Hanya admin.');
        redirect(APP_URL . '/index.php');
    }
}

/**
 * Generate kode tiket unik
 */
function generateTicketCode() {
    return 'TK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Generate barcode data
 */
function generateBarcodeData($ticketCode) {
    return 'TIKETKU|' . $ticketCode . '|' . time();
}

/**
 * Format rupiah
 */
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

/**
 * Format currency
 */
function formatCurrency($amount, $code = 'IDR', $symbol = 'Rp') {
    if ($code === 'IDR') {
        return $symbol . ' ' . number_format($amount, 0, ',', '.');
    }
    return $symbol . ' ' . number_format($amount, 2, '.', ',');
}

/**
 * Get all currencies
 */
function getCurrencies() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY code");
    return $stmt->fetchAll();
}

/**
 * Convert IDR to other currency
 */
function convertFromIdr($amountIdr, $currencyCode) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT rate_to_idr, symbol FROM currencies WHERE code = ?");
    $stmt->execute([$currencyCode]);
    $curr = $stmt->fetch();
    if (!$curr || $curr['rate_to_idr'] == 0) return ['amount' => $amountIdr, 'symbol' => 'Rp'];
    $converted = $amountIdr / $curr['rate_to_idr'];
    return ['amount' => $converted, 'symbol' => $curr['symbol']];
}

/**
 * Get setting
 */
function getSetting($key, $default = null) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : $default;
}

/**
 * Create notification (hindari duplikat dengan cek sederhana)
 */
function createNotification($userId, $judul, $pesan, $tipe = 'info', $ticketId = null) {
    global $pdo;
    // Cegah notifikasi duplikat dalam 5 menit terakhir untuk judul + ticket yang sama
    if ($ticketId) {
        $check = $pdo->prepare("SELECT id FROM notifications WHERE user_id = ? AND ticket_id = ? AND judul = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $check->execute([$userId, $ticketId, $judul]);
        if ($check->fetch()) return; // sudah ada, skip
    }
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, ticket_id, judul, pesan, tipe) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $ticketId, $judul, $pesan, $tipe]);
}

/**
 * Log email
 */
function logEmail($userId, $emailTo, $type, $subject, $ticketId = null, $status = 'pending', $response = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO email_logs (ticket_id, user_id, email_to, email_type, subject, status, response_message) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$ticketId, $userId, $emailTo, $type, $subject, $status, $response]);
}

/**
 * Cek & update status tiket yang sudah mepet / hangus
 * - Peringatan 10 menit sebelum berangkat (in-app)
 * - Flag untuk email warning (akan dikirim via EmailJS di frontend)
 * - Void otomatis jika sudah lewat
 * Tidak tumpang tindih: satu peringatan saja per tiket
 */
function checkTicketWarningsAndVoid() {
    global $pdo;
    
    $warningMinutes = (int) getSetting('warning_minutes_before', 10);
    
    // 1. Kirim peringatan in-app + flag email jika mendekati waktu berangkat & masih active
    $sqlWarning = "
        SELECT t.id, t.user_id, t.kode_tiket, t.email_warning_sent,
               s.jam_berangkat, s.tanggal_berangkat, s.kode_kendaraan, s.nama_kendaraan,
               u.email, u.nama_lengkap
        FROM tickets t
        JOIN schedules s ON t.schedule_id = s.id
        JOIN users u ON t.user_id = u.id
        WHERE t.status = 'active'
          AND t.warning_sent = 0
          AND t.payment_status = 'paid'
          AND TIMESTAMP(s.tanggal_berangkat, s.jam_berangkat) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? MINUTE)
    ";
    $stmt = $pdo->prepare($sqlWarning);
    $stmt->execute([$warningMinutes]);
    $tickets = $stmt->fetchAll();
    
    foreach ($tickets as $t) {
        $pesan = "PERINGATAN PENTING! Tiket {$t['kode_tiket']} ({$t['kode_kendaraan']} - {$t['nama_kendaraan']}) akan berangkat dalam kurang dari {$warningMinutes} menit. Segera ke area boarding dan scan barcode tiket agar tidak hangus!";
        createNotification($t['user_id'], 'Peringatan Keberangkatan', $pesan, 'warning', $t['id']);
        
        $upd = $pdo->prepare("UPDATE tickets SET warning_sent = 1 WHERE id = ?");
        $upd->execute([$t['id']]);
        
        // Flag untuk email (akan diproses di halaman yang relevan via EmailJS)
        // Tidak langsung kirim di sini karena EmailJS client-side
    }
    
    // 2. Void tiket HANYA jika:
    //    - Admin sudah set status jadwal = 'departed', ATAU
    //    - Waktu keberangkatan sudah lewat lebih dari 15 menit (grace period)
    // Tidak langsung hangus begitu jam berangkat lewat, supaya tidak ke-void saat baru bayar.
    $sqlVoid = "
        SELECT t.id, t.user_id, t.kode_tiket, s.kode_kendaraan
        FROM tickets t
        JOIN schedules s ON t.schedule_id = s.id
        WHERE t.status = 'active'
          AND t.payment_status = 'paid'
          AND (
                s.status = 'departed'
                OR TIMESTAMP(s.tanggal_berangkat, s.jam_berangkat) < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
              )
    ";
    $stmt = $pdo->query($sqlVoid);
    $voidTickets = $stmt->fetchAll();
    
    foreach ($voidTickets as $t) {
        $upd = $pdo->prepare("UPDATE tickets SET status = 'void' WHERE id = ?");
        $upd->execute([$t['id']]);
        
        $pesan = "Tiket {$t['kode_tiket']} telah HANGUS karena Anda tidak melakukan boarding sebelum keberangkatan. Hubungi customer service jika ada kendala.";
        createNotification($t['user_id'], 'Tiket Hangus', $pesan, 'danger', $t['id']);
    }
}

/**
 * Ambil data tiket lengkap untuk email
 */
function getTicketFullData($ticketId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT t.*, 
               s.kode_kendaraan, s.nama_kendaraan, s.tanggal_berangkat, s.jam_berangkat, s.jam_tiba,
               r.asal, r.tujuan, r.jenis, r.stasiun_bandara_asal, r.stasiun_bandara_tujuan,
               u.nama_lengkap, u.email, u.no_hp
        FROM tickets t
        JOIN schedules s ON t.schedule_id = s.id
        JOIN routes r ON s.route_id = r.id
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) return null;
    
    $stmtP = $pdo->prepare("SELECT * FROM passengers WHERE ticket_id = ?");
    $stmtP->execute([$ticketId]);
    $ticket['passengers'] = $stmtP->fetchAll();
    return $ticket;
}

/**
 * Status badge HTML
 */
function statusBadge($status) {
    $map = [
        'pending'   => 'bg-yellow-100 text-yellow-800',
        'active'    => 'bg-green-100 text-green-800',
        'boarded'   => 'bg-blue-100 text-blue-800',
        'expired'   => 'bg-gray-100 text-gray-800',
        'cancelled' => 'bg-red-100 text-red-800',
        'void'      => 'bg-red-200 text-red-900',
        'scheduled' => 'bg-indigo-100 text-indigo-800',
        'boarding'  => 'bg-orange-100 text-orange-800',
        'departed'  => 'bg-purple-100 text-purple-800',
        'completed' => 'bg-gray-100 text-gray-700',
        'unpaid'    => 'bg-yellow-100 text-yellow-800',
        'paid'      => 'bg-green-100 text-green-800',
        'failed'    => 'bg-red-100 text-red-800',
    ];
    $class = $map[$status] ?? 'bg-gray-100 text-gray-800';
    $label = strtoupper(str_replace('_', ' ', $status));
    return "<span class=\"px-2.5 py-0.5 rounded-full text-xs font-semibold $class\">$label</span>";
}

/**
 * Jenis transport icon
 */
function transportIcon($jenis) {
    if ($jenis === 'kereta') {
        return '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2c-4 0-8 .5-8 4v9.5C4 17.43 5.57 19 7.5 19L6 20.5v.5h2l2-2h4l2 2h2v-.5L16.5 19c1.93 0 3.5-1.57 3.5-3.5V6c0-3.5-4-4-8-4zM7.5 17c-.83 0-1.5-.67-1.5-1.5S6.67 14 7.5 14s1.5.67 1.5 1.5S8.33 17 7.5 17zm3.5-7H6V6h5v4zm2 0V6h5v4h-5zm3.5 7c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>';
    }
    return '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/></svg>';
}
?>
