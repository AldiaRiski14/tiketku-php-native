<?php
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

// Stats
$stats = [
    'total_tiket' => $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'active'")->fetchColumn(),
    'boarded' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'boarded'")->fetchColumn(),
    'void' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'void'")->fetchColumn(),
    'today_schedules' => $pdo->query("SELECT COUNT(*) FROM schedules WHERE tanggal_berangkat = CURDATE()")->fetchColumn(),
    'users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
];

// Tiket active yang mepet
$warningMinutes = (int)getSetting('warning_minutes_before', 30);
$stmt = $pdo->prepare("
    SELECT t.*, s.kode_kendaraan, s.jam_berangkat, s.tanggal_berangkat, u.nama_lengkap, u.no_hp, r.asal, r.tujuan
    FROM tickets t
    JOIN schedules s ON t.schedule_id = s.id
    JOIN users u ON t.user_id = u.id
    JOIN routes r ON s.route_id = r.id
    WHERE t.status = 'active' AND t.payment_status = 'paid'
      AND TIMESTAMP(s.tanggal_berangkat, s.jam_berangkat) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? MINUTE)
    ORDER BY s.jam_berangkat
");
$stmt->execute([$warningMinutes]);
$urgent = $stmt->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-slate-800">Admin Dashboard</h1>
            <p class="text-slate-500 text-sm mt-1">Kelola sistem pemesanan tiket</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="<?= APP_URL ?>/admin/scan.php" class="bg-primary-600 hover:bg-primary-700 text-white font-semibold px-5 py-2.5 rounded-xl shadow-md transition">
                Scan Boarding
            </a>
            <a href="<?= APP_URL ?>/admin/pesan-langsung.php" class="bg-green-600 hover:bg-green-700 text-white font-semibold px-5 py-2.5 rounded-xl shadow-md transition">
                Pesan Langsung
            </a>
            <a href="<?= APP_URL ?>/admin/jadwal.php" class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold px-5 py-2.5 rounded-xl transition">Kelola Jadwal</a>
            <a href="<?= APP_URL ?>/admin/tiket.php" class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold px-5 py-2.5 rounded-xl transition">Semua Tiket</a>
            <a href="<?= APP_URL ?>/admin/users.php" class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold px-5 py-2.5 rounded-xl transition">Kelola User</a>
        </div>
    </div>
    
    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="text-2xl font-bold text-slate-800"><?= $stats['total_tiket'] ?></div>
            <div class="text-xs text-slate-500 mt-1">Total Tiket</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="text-2xl font-bold text-green-600"><?= $stats['active'] ?></div>
            <div class="text-xs text-slate-500 mt-1">Active</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="text-2xl font-bold text-blue-600"><?= $stats['boarded'] ?></div>
            <div class="text-xs text-slate-500 mt-1">Boarded</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="text-2xl font-bold text-red-600"><?= $stats['void'] ?></div>
            <div class="text-xs text-slate-500 mt-1">Hangus</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="text-2xl font-bold text-indigo-600"><?= $stats['today_schedules'] ?></div>
            <div class="text-xs text-slate-500 mt-1">Jadwal Hari Ini</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="text-2xl font-bold text-slate-800"><?= $stats['users'] ?></div>
            <div class="text-xs text-slate-500 mt-1">Pengguna</div>
        </div>
    </div>
    
    <!-- Urgent Boarding -->
    <?php if (!empty($urgent)): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mb-8">
        <h2 class="font-bold text-amber-900 mb-3 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            Tiket Active yang Hampir Berangkat (belum boarding)
        </h2>
        <div class="space-y-2">
            <?php foreach ($urgent as $u): ?>
            <div class="bg-white rounded-xl p-3 flex flex-wrap items-center justify-between gap-3 text-sm">
                <div>
                    <span class="font-bold text-slate-800"><?= e($u['kode_tiket']) ?></span>
                    <span class="text-slate-500">• <?= e($u['nama_lengkap']) ?> (<?= e($u['no_hp']) ?>)</span>
                    <div class="text-xs text-slate-400"><?= e($u['asal']) ?> → <?= e($u['tujuan']) ?> • <?= e($u['kode_kendaraan']) ?> • <?= date('H:i', strtotime($u['jam_berangkat'])) ?></div>
                </div>
                <a href="<?= APP_URL ?>/admin/scan.php?kode=<?= urlencode($u['kode_tiket']) ?>" class="bg-primary-600 text-white text-xs font-semibold px-3 py-1.5 rounded-lg hover:bg-primary-700">Scan Sekarang</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Quick Links -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <a href="<?= APP_URL ?>/admin/scan.php" class="bg-white rounded-2xl border border-slate-200 p-6 hover:shadow-md transition card-hover">
            <div class="w-12 h-12 bg-primary-100 text-primary-600 rounded-xl flex items-center justify-center mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
            </div>
            <h3 class="font-bold text-slate-800">Scan Boarding</h3>
            <p class="text-sm text-slate-500 mt-1">Scan barcode tiket penumpang untuk konfirmasi naik.</p>
        </a>
        <a href="<?= APP_URL ?>/admin/jadwal.php" class="bg-white rounded-2xl border border-slate-200 p-6 hover:shadow-md transition card-hover">
            <div class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <h3 class="font-bold text-slate-800">Kelola Jadwal</h3>
            <p class="text-sm text-slate-500 mt-1">Lihat & update status jadwal (boarding / departed).</p>
        </a>
        <a href="<?= APP_URL ?>/admin/tiket.php" class="bg-white rounded-2xl border border-slate-200 p-6 hover:shadow-md transition card-hover">
            <div class="w-12 h-12 bg-green-100 text-green-600 rounded-xl flex items-center justify-center mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
            </div>
            <h3 class="font-bold text-slate-800">Manajemen Tiket</h3>
            <p class="text-sm text-slate-500 mt-1">Lihat semua tiket, kirim peringatan manual.</p>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
