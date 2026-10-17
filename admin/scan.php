<?php
$pageTitle = 'Scan Boarding';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$message = '';
$messageType = '';
$ticketData = null;

// Process scan
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['kode'])) {
    $input = trim($_POST['barcode'] ?? $_GET['kode'] ?? '');
    
    if (empty($input)) {
        $message = 'Masukkan kode tiket atau data barcode.';
        $messageType = 'warning';
    } else {
        // Cari berdasarkan kode_tiket atau barcode_data
        $stmt = $pdo->prepare("
            SELECT t.*, s.kode_kendaraan, s.nama_kendaraan, s.tanggal_berangkat, s.jam_berangkat, s.status as schedule_status,
                   u.nama_lengkap, u.email, u.no_hp, r.asal, r.tujuan, r.jenis
            FROM tickets t
            JOIN schedules s ON t.schedule_id = s.id
            JOIN users u ON t.user_id = u.id
            JOIN routes r ON s.route_id = r.id
            WHERE t.kode_tiket = ? OR t.barcode_data = ? OR t.barcode_data LIKE ?
        ");
        $stmt->execute([$input, $input, '%' . $input . '%']);
        $ticketData = $stmt->fetch();
        
        if (!$ticketData) {
            $message = 'Tiket tidak ditemukan.';
            $messageType = 'danger';
        } elseif ($ticketData['payment_status'] !== 'paid') {
            $message = 'Tiket belum dibayar.';
            $messageType = 'danger';
        } elseif ($ticketData['status'] === 'boarded') {
            $message = 'Tiket ini sudah di-scan sebelumnya pada ' . date('d M Y H:i', strtotime($ticketData['boarded_at']));
            $messageType = 'info';
        } elseif ($ticketData['status'] === 'void') {
            $message = 'Tiket sudah HANGUS. Tidak bisa di-boarding.';
            $messageType = 'danger';
        } elseif ($ticketData['status'] !== 'active') {
            $message = 'Status tiket tidak valid untuk boarding: ' . strtoupper($ticketData['status']);
            $messageType = 'danger';
        } else {
            // Lakukan boarding
            if (isset($_POST['confirm_board'])) {
                $stmt = $pdo->prepare("
                    UPDATE tickets SET status = 'boarded', boarded_at = NOW(), boarded_by = ? WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $ticketData['id']]);
                
                createNotification(
                    $ticketData['user_id'],
                    'Boarding Berhasil',
                    "Tiket {$ticketData['kode_tiket']} telah dikonfirmasi naik. Selamat perjalanan!",
                    'success',
                    $ticketData['id']
                );
                
                $message = "Berhasil! Penumpang {$ticketData['nama_lengkap']} telah dikonfirmasi boarding.";
                $messageType = 'success';
                
                // Refresh data
                $stmt = $pdo->prepare("SELECT t.*, s.kode_kendaraan, s.nama_kendaraan, s.tanggal_berangkat, s.jam_berangkat, u.nama_lengkap, u.no_hp, r.asal, r.tujuan FROM tickets t JOIN schedules s ON t.schedule_id = s.id JOIN users u ON t.user_id = u.id JOIN routes r ON s.route_id = r.id WHERE t.id = ?");
                $stmt->execute([$ticketData['id']]);
                $ticketData = $stmt->fetch();
                $ticketData['status'] = 'boarded';
            }
        }
    }
}
?>

<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Scan Boarding</h1>
        <a href="<?= APP_URL ?>/admin/index.php" class="text-sm text-primary-600 hover:underline">← Dashboard</a>
    </div>
    
    <!-- Input Form -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-6">
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Kode Tiket / Barcode Data</label>
                <input type="text" name="barcode" autofocus 
                       value="<?= e($_POST['barcode'] ?? $_GET['kode'] ?? '') ?>"
                       class="w-full border border-slate-300 rounded-xl px-4 py-3 text-lg font-mono focus:ring-2 focus:ring-primary-500 outline-none"
                       placeholder="TK-20260801-XXXX atau scan barcode...">
            </div>
            <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-bold py-3.5 rounded-xl shadow-md transition">
                Cari / Scan Tiket
            </button>
        </form>
        <p class="text-xs text-slate-400 mt-3 text-center">Masukkan kode tiket manual atau tempel data dari QR scanner</p>
    </div>
    
    <?php if ($message): ?>
    <div class="mb-5 rounded-xl p-4 text-sm font-medium
        <?= $messageType === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : '' ?>
        <?= $messageType === 'danger'  ? 'bg-red-50 text-red-800 border border-red-200' : '' ?>
        <?= $messageType === 'warning' ? 'bg-amber-50 text-amber-800 border border-amber-200' : '' ?>
        <?= $messageType === 'info'    ? 'bg-blue-50 text-blue-800 border border-blue-200' : '' ?>
    ">
        <?= e($message) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($ticketData && $ticketData['status'] === 'active'): ?>
    <!-- Confirm Boarding -->
    <div class="bg-white rounded-2xl border-2 border-primary-300 shadow-sm overflow-hidden">
        <div class="bg-primary-50 px-6 py-4 border-b border-primary-100">
            <div class="font-bold text-primary-900">Konfirmasi Boarding</div>
        </div>
        <div class="p-6 space-y-3 text-sm">
            <div class="flex justify-between"><span class="text-slate-500">Kode Tiket</span><span class="font-bold"><?= e($ticketData['kode_tiket']) ?></span></div>
            <div class="flex justify-between"><span class="text-slate-500">Penumpang</span><span class="font-medium"><?= e($ticketData['nama_lengkap']) ?></span></div>
            <div class="flex justify-between"><span class="text-slate-500">No. HP</span><span><?= e($ticketData['no_hp']) ?></span></div>
            <div class="flex justify-between"><span class="text-slate-500">Rute</span><span><?= e($ticketData['asal']) ?> → <?= e($ticketData['tujuan']) ?></span></div>
            <div class="flex justify-between"><span class="text-slate-500">Kendaraan</span><span><?= e($ticketData['kode_kendaraan']) ?> • <?= e($ticketData['nama_kendaraan']) ?></span></div>
            <div class="flex justify-between"><span class="text-slate-500">Berangkat</span><span><?= date('d M Y H:i', strtotime($ticketData['tanggal_berangkat'] . ' ' . $ticketData['jam_berangkat'])) ?></span></div>
            
            <form method="POST" class="pt-4">
                <input type="hidden" name="barcode" value="<?= e($ticketData['kode_tiket']) ?>">
                <button type="submit" name="confirm_board" value="1" 
                        class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-4 rounded-xl shadow-md transition text-lg">
                    ✓ Konfirmasi Penumpang Sudah Naik
                </button>
            </form>
        </div>
    </div>
    <?php elseif ($ticketData && $ticketData['status'] === 'boarded'): ?>
    <div class="bg-green-50 border border-green-200 rounded-2xl p-6 text-center">
        <div class="text-4xl mb-2">✓</div>
        <div class="font-bold text-green-800 text-lg">Sudah Boarded</div>
        <p class="text-sm text-green-700 mt-1"><?= e($ticketData['nama_lengkap']) ?> • <?= e($ticketData['kode_tiket']) ?></p>
        <a href="<?= APP_URL ?>/admin/cetak-tiket.php?id=<?= (int)$ticketData['id'] ?>" target="_blank"
           class="inline-block mt-4 bg-primary-600 hover:bg-primary-700 text-white font-semibold px-6 py-2.5 rounded-xl transition">
            🖨️ Cetak Tiket
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
