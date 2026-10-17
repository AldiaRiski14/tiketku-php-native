<?php
$pageTitle = 'Tiket Saya';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$stmt = $pdo->prepare("
    SELECT t.*, s.kode_kendaraan, s.nama_kendaraan, s.tanggal_berangkat, s.jam_berangkat,
           r.asal, r.tujuan, r.jenis
    FROM tickets t
    JOIN schedules s ON t.schedule_id = s.id
    JOIN routes r ON s.route_id = r.id
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$tickets = $stmt->fetchAll();
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl md:text-3xl font-bold text-slate-800 mb-6">Tiket Saya</h1>
    
    <?php if (empty($tickets)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
        <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
        <p class="text-slate-500 font-medium">Belum ada tiket</p>
        <a href="<?= APP_URL ?>/user/cari.php" class="inline-block mt-4 text-primary-600 font-semibold hover:underline">Cari tiket sekarang →</a>
    </div>
    <?php else: ?>
    <div class="space-y-4">
        <?php foreach ($tickets as $t): ?>
        <a href="<?= APP_URL ?>/user/detail-tiket.php?id=<?= $t['id'] ?>" 
           class="block bg-white rounded-2xl border border-slate-200 p-5 hover:shadow-md transition card-hover">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-primary-600"><?= transportIcon($t['jenis']) ?></span>
                        <span class="font-bold text-slate-800"><?= e($t['kode_tiket']) ?></span>
                        <?= statusBadge($t['status']) ?>
                        <?php if ($t['payment_status'] === 'unpaid'): ?>
                            <?= statusBadge('unpaid') ?>
                        <?php endif; ?>
                    </div>
                    <div class="text-sm text-slate-600">
                        <span class="font-medium"><?= e($t['asal']) ?></span>
                        <span class="text-slate-400 mx-1">→</span>
                        <span class="font-medium"><?= e($t['tujuan']) ?></span>
                    </div>
                    <div class="text-xs text-slate-400 mt-1">
                        <?= e($t['kode_kendaraan']) ?> • <?= date('d M Y H:i', strtotime($t['tanggal_berangkat'] . ' ' . $t['jam_berangkat'])) ?>
                        • <?= $t['jumlah_penumpang'] ?> penumpang
                    </div>
                </div>
                <div class="text-right">
                    <div class="font-bold text-primary-700">
                        <?php
                        $conv = convertFromIdr($t['total_harga_idr'], $t['currency_code']);
                        echo formatCurrency($conv['amount'], $t['currency_code'], $conv['symbol']);
                        ?>
                    </div>
                    <div class="text-xs text-slate-400 mt-1 uppercase"><?= e($t['metode_pembayaran'] ?? '-') ?></div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
